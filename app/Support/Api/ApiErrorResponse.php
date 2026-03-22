<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

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

        return response()->json($payload, $status);
    }
}
