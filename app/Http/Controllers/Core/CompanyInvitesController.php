<?php

namespace App\Http\Controllers\Core;

use App\Core\Access\InviteProvisioningService;
use App\Core\Access\Models\Invite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CompanyInviteStoreRequest;
use App\Jobs\SendInviteLinkMail;
use App\Support\Http\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CompanyInvitesController extends Controller
{
    public function __construct(
        private readonly InviteProvisioningService $inviteProvisioningService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeOwnerInviteManagement($request);

        $companyId = $request->user()?->current_company_id;

        $invites = Invite::query()
            ->where('company_id', $companyId)
            ->with('companyRole:id,name')
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
                    'role' => $invite->companyRole?->name ?? $invite->role,
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
        $this->authorizeOwnerInviteManagement($request);

        return Inertia::render('core/invites/create');
    }

    public function store(CompanyInviteStoreRequest $request): RedirectResponse
    {
        $this->authorizeOwnerInviteManagement($request);

        $data = $request->validated();
        $expiresAt = ($data['expires_at'] ?? null)
            ? Carbon::parse($data['expires_at'])->endOfDay()
            : now()->addDays(14);

        $invite = $this->inviteProvisioningService->createOrRefreshPendingInvite(
            email: (string) $data['email'],
            name: $data['name'] ?? null,
            role: 'company_owner',
            companyId: (string) $request->user()?->current_company_id,
            expiresAt: $expiresAt,
            actorId: $request->user()?->id,
        );

        $this->queueDelivery($invite);

        return redirect()
            ->route('core.invites.index')
            ->with('success', Feedback::flash($request, 'Owner invite created and queued for delivery.'));
    }

    public function resend(Request $request, Invite $invite): RedirectResponse
    {
        $this->authorizeOwnerInviteManagement($request);

        if ($invite->company_id !== $request->user()?->current_company_id) {
            abort(403);
        }

        if ($invite->accepted_at) {
            return redirect()
                ->route('core.invites.index')
                ->with('error', Feedback::flash($request, 'Invite already accepted.'));
        }

        $this->queueDelivery($invite);

        return redirect()
            ->route('core.invites.index')
            ->with('success', Feedback::flash($request, 'Invite resend queued.'));
    }

    public function retryDelivery(Request $request, Invite $invite): RedirectResponse
    {
        $this->authorizeOwnerInviteManagement($request);

        if ($invite->company_id !== $request->user()?->current_company_id) {
            abort(403);
        }

        if ($invite->accepted_at) {
            return redirect()
                ->route('core.invites.index')
                ->with('error', Feedback::flash($request, 'Accepted invites do not require delivery retry.'));
        }

        $this->queueDelivery($invite);

        return redirect()
            ->route('core.invites.index')
            ->with('success', Feedback::flash($request, 'Invite delivery retry queued.'));
    }

    public function destroy(Request $request, Invite $invite): RedirectResponse
    {
        $this->authorizeOwnerInviteManagement($request);

        if ($invite->company_id !== $request->user()?->current_company_id) {
            abort(403);
        }

        $invite->delete();

        return redirect()
            ->route('core.invites.index')
            ->with('success', Feedback::flash($request, 'Invite removed.'));
    }

    private function queueDelivery(Invite $invite): void
    {
        $invite->forceFill([
            'delivery_status' => Invite::DELIVERY_PENDING,
            'last_delivery_at' => null,
            'last_delivery_error' => null,
        ])->save();

        SendInviteLinkMail::dispatch($invite->id)->afterCommit();
    }

    private function authorizeOwnerInviteManagement(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->hasPermission('core.users.manage'), 403);
        abort_if($user?->is_super_admin, 403);
        abort_unless($user?->current_company_id, 403);
        abort_unless(
            $user?->memberships()
                ->where('company_id', $user->current_company_id)
                ->where('is_owner', true)
                ->exists(),
            403,
        );
    }
}
