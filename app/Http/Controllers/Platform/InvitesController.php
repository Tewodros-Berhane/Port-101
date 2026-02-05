<?php

namespace App\Http\Controllers\Platform;

use App\Core\Access\Models\Invite;
use App\Core\Company\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\InviteStoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InvitesController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->input('status'),
            'role' => $request->input('role'),
            'search' => $request->input('search'),
        ];

        $invites = Invite::query()
            ->with('company:id,name')
            ->when($filters['status'], function ($query, string $status) {
                if ($status === 'accepted') {
                    $query->whereNotNull('accepted_at');
                }

                if ($status === 'expired') {
                    $query->whereNull('accepted_at')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', now());
                }

                if ($status === 'pending') {
                    $query->whereNull('accepted_at')
                        ->where(function ($pendingQuery) {
                            $pendingQuery
                                ->whereNull('expires_at')
                                ->orWhere('expires_at', '>=', now());
                        });
                }
            })
            ->when($filters['role'], function ($query, string $role) {
                $query->where('role', $role);
            })
            ->when($filters['search'], function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $companyOptions = Company::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $baseUrl = rtrim(config('app.url'), '/');

        return Inertia::render('platform/invites/index', [
            'invites' => $invites->through(function (Invite $invite) use ($baseUrl) {
                $status = 'pending';

                if ($invite->accepted_at) {
                    $status = 'accepted';
                } elseif ($invite->expires_at && $invite->expires_at->isPast()) {
                    $status = 'expired';
                }

                return [
                    'id' => $invite->id,
                    'email' => $invite->email,
                    'name' => $invite->name,
                    'role' => $invite->role,
                    'company' => $invite->company?->name,
                    'token' => $invite->token,
                    'invite_url' => $baseUrl.'/invites/'.$invite->token,
                    'status' => $status,
                    'expires_at' => $invite->expires_at?->toIso8601String(),
                    'accepted_at' => $invite->accepted_at?->toIso8601String(),
                ];
            }),
            'filters' => $filters,
            'companies' => $companyOptions,
        ]);
    }

    public function create(): Response
    {
        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('platform/invites/create', [
            'companies' => $companies,
        ]);
    }

    public function store(InviteStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $expiresAt = $data['expires_at']
            ? Carbon::parse($data['expires_at'])->endOfDay()
            : now()->addDays(14);

        Invite::create([
            'email' => $data['email'],
            'name' => $data['name'] ?? null,
            'role' => $data['role'],
            'company_id' => $data['company_id'] ?? null,
            'token' => Str::random(40),
            'expires_at' => $expiresAt,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('platform.invites.index')
            ->with('success', 'Invite created.');
    }

    public function destroy(Invite $invite): RedirectResponse
    {
        $invite->delete();

        return redirect()
            ->route('platform.invites.index')
            ->with('success', 'Invite removed.');
    }
}
