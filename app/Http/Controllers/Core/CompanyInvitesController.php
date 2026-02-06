<?php

namespace App\Http\Controllers\Core;

use App\Core\Access\Models\Invite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CompanyInviteStoreRequest;
use App\Mail\InviteLinkMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
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
            'created_by' => $request->user()?->id,
        ]);

        Mail::to($invite->email)->send(new InviteLinkMail(
            $invite,
            rtrim(config('app.url'), '/').'/invites/'.$invite->token
        ));

        return redirect()
            ->route('core.invites.index')
            ->with('success', 'Invite created.');
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

        Mail::to($invite->email)->send(new InviteLinkMail(
            $invite,
            rtrim(config('app.url'), '/').'/invites/'.$invite->token
        ));

        return redirect()
            ->route('core.invites.index')
            ->with('success', 'Invite resent.');
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
}
