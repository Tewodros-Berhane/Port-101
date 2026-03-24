<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        string $message,
        int $status,
        array $errors = [],
        array $meta = [],
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        $response = response()->json($payload, $status);

        $request = request();

        if ($request) {
            App::make(ApiVersionPolicy::class)->applyHeadersForRequest($response, $request);
        }

        return $response;
    }
}
