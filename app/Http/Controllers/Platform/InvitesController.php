<?php

namespace App\Http\Controllers\Platform;

use App\Core\Access\Models\Invite;
use App\Http\Controllers\Controller;
use App\Jobs\SendInviteLinkMail;
use App\Support\Http\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitesController extends Controller
{
    public function destroy(Request $request, Invite $invite): RedirectResponse
    {
        $this->ensurePlatformAdminInvite($invite);

        $invite->delete();

        return back(303)
            ->with('success', Feedback::flash($request, 'Invite removed.'));
    }

    public function resend(Request $request, Invite $invite): RedirectResponse
    {
        $this->ensurePlatformAdminInvite($invite);

        if ($invite->accepted_at) {
            return back(303)
                ->with('error', Feedback::flash($request, 'Invite already accepted.'));
        }

        $this->queueDelivery($invite);

        return back(303)
            ->with('success', Feedback::flash($request, 'Invite resend queued.'));
    }

    public function retryDelivery(Request $request, Invite $invite): RedirectResponse
    {
        $this->ensurePlatformAdminInvite($invite);

        if ($invite->accepted_at) {
            return back(303)
                ->with('error', Feedback::flash($request, 'Accepted invites do not require delivery retry.'));
        }

        $this->queueDelivery($invite);

        return back(303)
            ->with('success', Feedback::flash($request, 'Invite delivery retry queued.'));
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
