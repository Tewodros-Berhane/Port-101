<?php

namespace App\Http\Controllers\Core;

use App\Core\Access\Models\Invite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CompanyInviteStoreRequest;
use App\Jobs\SendInviteLinkMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyInvitesController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        $companyId = $request->user()?->current_company_id;

        $invites = Invite::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $baseUrl = rtrim(config('app.url'), '/');

        return Inertia::render('core/invites/index', [
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
                    'status' => $status,
                    'invite_url' => $baseUrl.'/invites/'.$invite->token,
                    'expires_at' => $invite->expires_at?->toIso8601String(),
                    'accepted_at' => $invite->accepted_at?->toIso8601String(),
                    'delivery_status' => $invite->delivery_status,
                    'delivery_attempts' => (int) $invite->delivery_attempts,
                    'last_delivery_at' => $invite->last_delivery_at?->toIso8601String(),
                    'last_delivery_error' => $invite->last_delivery_error,
                ];
            }),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        return Inertia::render('core/invites/create');
    }

    public function store(CompanyInviteStoreRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        $data = $request->validated();
        $expiresAt = $data['expires_at']
            ? Carbon::parse($data['expires_at'])->endOfDay()
            : now()->addDays(14);

        $invite = Invite::create([
            'email' => $data['email'],
            'name' => $data['name'] ?? null,
            'role' => $data['role'],
            'company_id' => $request->user()?->current_company_id,
            'token' => Str::random(40),
            'expires_at' => $expiresAt,
            'delivery_status' => Invite::DELIVERY_PENDING,
            'delivery_attempts' => 0,
            'last_delivery_error' => null,
            'created_by' => $request->user()?->id,
        ]);

        $this->queueDelivery($invite);

        return redirect()
            ->route('core.invites.index')
            ->with('success', 'Invite created and queued for delivery.');
    }

    public function resend(Request $request, Invite $invite): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        if ($invite->company_id !== $request->user()?->current_company_id) {
            abort(403);
        }

        if ($invite->accepted_at) {
            return redirect()
                ->route('core.invites.index')
                ->with('error', 'Invite already accepted.');
        }

        $this->queueDelivery($invite);

        return redirect()
            ->route('core.invites.index')
            ->with('success', 'Invite resend queued.');
    }

    public function retryDelivery(Request $request, Invite $invite): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        if ($invite->company_id !== $request->user()?->current_company_id) {
            abort(403);
        }

        if ($invite->accepted_at) {
            return redirect()
                ->route('core.invites.index')
                ->with('error', 'Accepted invites do not require delivery retry.');
        }

        $this->queueDelivery($invite);

        return redirect()
            ->route('core.invites.index')
            ->with('success', 'Invite delivery retry queued.');
    }

    public function destroy(Request $request, Invite $invite): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        if ($invite->company_id !== $request->user()?->current_company_id) {
            abort(403);
        }

        $invite->delete();

        return redirect()
            ->route('core.invites.index')
            ->with('success', 'Invite removed.');
    }

    private function queueDelivery(Invite $invite): void
    {
        $invite->forceFill([
            'delivery_status' => Invite::DELIVERY_PENDING,
            'last_delivery_at' => null,
            'last_delivery_error' => null,
        ])->save();

        SendInviteLinkMail::dispatch($invite->id);
    }
}
