<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

class Feedback
{
    public const CLIENT_TOAST_HEADER = 'X-Port101-Feedback';

    public const CLIENT_TOAST_MODE = 'client-toast';

    /**
     * @param  array<string, mixed>  $payload
     * @return string|array<string, mixed>
     */
    public static function flash(
        Request $request,
        string $message,
        array $payload = []
    ): string|array {
        if (! self::wantsClientToast($request)) {
            return $message;
        }

        return [
            ...$payload,
            'message' => $message,
            'suppress_global_toast' => true,
        ];
    }

    public static function wantsClientToast(Request $request): bool
    {
        return $request->header(self::CLIENT_TOAST_HEADER) === self::CLIENT_TOAST_MODE;
    }
}
