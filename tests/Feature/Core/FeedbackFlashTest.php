<?php

use App\Support\Http\Feedback;
use Illuminate\Http\Request;

it('returns a plain flash message when client toast headers are absent', function () {
    $request = Request::create('/platform/invites/123/resend', 'POST');

    expect(Feedback::flash($request, 'Invite resend queued.'))
        ->toBe('Invite resend queued.');
});

it('returns a structured flash payload when client toast headers are present', function () {
    $request = Request::create('/platform/invites/123/resend', 'POST', server: [
        'HTTP_X_PORT101_FEEDBACK' => Feedback::CLIENT_TOAST_MODE,
    ]);

    expect(Feedback::flash($request, 'Invite resend queued.', [
        'level' => 'success',
        'dedupe_key' => 'platform-invite-resend',
    ]))->toBe([
        'level' => 'success',
        'dedupe_key' => 'platform-invite-resend',
        'message' => 'Invite resend queued.',
        'suppress_global_toast' => true,
    ]);
});
