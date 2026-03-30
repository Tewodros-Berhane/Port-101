<?php

namespace App\Http\Controllers\Platform;

use App\Core\Access\Models\Invite;
use App\Http\Controllers\Controller;
use App\Jobs\SendInviteLinkMail;
use Illuminate\Http\RedirectResponse;

class InvitesController extends Controller
{
    public function destroy(Invite $invite): RedirectResponse
    {
        $this->ensurePlatformAdminInvite($invite);

        $invite->delete();

        return back(303)
            ->with('success', 'Invite removed.');
    }

    public function resend(Invite $invite): RedirectResponse
    {
        $this->ensurePlatformAdminInvite($invite);

        if ($invite->accepted_at) {
            return back(303)
                ->with('error', 'Invite already accepted.');
        }

        $this->queueDelivery($invite);

        return back(303)
            ->with('success', 'Invite resend queued.');
    }

    public function retryDelivery(Invite $invite): RedirectResponse
    {
        $this->ensurePlatformAdminInvite($invite);

        if ($invite->accepted_at) {
            return back(303)
                ->with('error', 'Accepted invites do not require delivery retry.');
        }

        $this->queueDelivery($invite);

        return back(303)
            ->with('success', 'Invite delivery retry queued.');
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

    private function ensurePlatformAdminInvite(Invite $invite): void
    {
        abort_unless(
            $invite->role === 'platform_admin' && $invite->company_id === null,
            404,
        );
    }
}
