<?php

namespace App\Support\Operations;

use Illuminate\Http\Client\ConnectionException;
use Throwable;

class OperationalFailureSanitizer
{
    public function normalizeReportExportFailure(Throwable|string|null $failure): array
    {
        $rawMessage = is_string($failure)
            ? trim($failure)
            : trim((string) $failure?->getMessage());

        if ($rawMessage === 'Company not found for report export.') {
            return $this->reportFailure(
                code: 'company_context_unavailable',
                message: 'The export could not run because the company record is no longer available.',
                exceptionClass: is_string($failure) ? null : $failure::class,
            );
        }

        if ($rawMessage === 'Requested report could not be generated.') {
            return $this->reportFailure(
                code: 'report_definition_unavailable',
                message: 'The selected report could not be generated with the current data.',
                exceptionClass: is_string($failure) ? null : $failure::class,
            );
        }

        if ($this->isSafeReportMessage($rawMessage)) {
            return $this->reportFailure(
                code: 'report_export_failed',
                message: $rawMessage,
                exceptionClass: is_string($failure) ? null : $failure::class,
            );
        }

        return $this->reportFailure(
            code: 'export_generation_failed',
            message: 'The export failed while generating the output file.',
            exceptionClass: is_string($failure) ? null : $failure::class,
        );
    }

    public function normalizeWebhookFailure(
        ?int $responseStatus,
        ?string $failureMessage = null,
        ?Throwable $exception = null,
    ): array {
        $rawMessage = trim((string) $failureMessage);

        if ($this->isSafeWebhookMessage($rawMessage)) {
            return $this->webhookFailure(
                code: 'delivery_blocked',
                category: 'policy',
                message: $rawMessage,
                responseStatus: $responseStatus,
                exceptionClass: $exception ? $exception::class : null,
            );
        }

        if ($responseStatus !== null) {
            return match (true) {
                $responseStatus === 404 => $this->webhookFailure(
                    code: 'endpoint_not_found',
                    category: 'http_client',
                    message: 'Endpoint could not be reached at the configured path (HTTP 404).',
                    responseStatus: $responseStatus,
                    exceptionClass: $exception ? $exception::class : null,
                ),
                in_array($responseStatus, [401, 403], true) => $this->webhookFailure(
                    code: 'endpoint_authorization_error',
                    category: 'http_client',
                    message: "Endpoint rejected the delivery with an authorization error (HTTP {$responseStatus}).",
                    responseStatus: $responseStatus,
                    exceptionClass: $exception ? $exception::class : null,
                ),
                in_array($responseStatus, [408, 409, 425, 429], true) => $this->webhookFailure(
                    code: 'endpoint_retry_requested',
                    category: 'http_client',
                    message: "Endpoint requested a retry later (HTTP {$responseStatus}).",
                    responseStatus: $responseStatus,
                    exceptionClass: $exception ? $exception::class : null,
                ),
                $responseStatus >= 400 && $responseStatus < 500 => $this->webhookFailure(
                    code: 'endpoint_rejected_request',
                    category: 'http_client',
                    message: "Endpoint rejected the delivery request (HTTP {$responseStatus}).",
                    responseStatus: $responseStatus,
                    exceptionClass: $exception ? $exception::class : null,
                ),
                $responseStatus >= 500 && $responseStatus < 600 => $this->webhookFailure(
                    code: 'endpoint_server_error',
                    category: 'http_server',
                    message: "Endpoint returned a server error (HTTP {$responseStatus}).",
                    responseStatus: $responseStatus,
                    exceptionClass: $exception ? $exception::class : null,
                ),
                default => $this->webhookFailure(
                    code: 'endpoint_unexpected_response',
                    category: 'http_unknown',
                    message: "Endpoint returned an unexpected response (HTTP {$responseStatus}).",
                    responseStatus: $responseStatus,
                    exceptionClass: $exception ? $exception::class : null,
                ),
            };
        }

        if ($exception instanceof ConnectionException || $this->looksLikeConnectionFailure($rawMessage)) {
            return $this->webhookFailure(
                code: 'connection_failed',
                category: 'network',
                message: 'Connection to the webhook endpoint failed before a response was received.',
                responseStatus: $responseStatus,
                exceptionClass: $exception ? $exception::class : null,
            );
        }

        if ($this->isSafeStoredWebhookMessage($rawMessage)) {
            return $this->webhookFailure(
                code: 'delivery_failed',
                category: 'delivery',
                message: $rawMessage,
                responseStatus: $responseStatus,
                exceptionClass: $exception ? $exception::class : null,
            );
        }

        return $this->webhookFailure(
            code: 'delivery_failed',
            category: 'delivery',
            message: 'Webhook delivery failed before a response was received.',
            responseStatus: $responseStatus,
            exceptionClass: $exception ? $exception::class : null,
        );
    }

    public function sanitizeStoredReportFailureMessage(?string $message): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        return $this->normalizeReportExportFailure($message)['message'];
    }

    public function sanitizeStoredWebhookFailureMessage(?string $message, ?int $responseStatus): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        return $this->normalizeWebhookFailure($responseStatus, $message)['message'];
    }

    public function sanitizeStoredWebhookResponseExcerpt(?string $excerpt): ?string
    {
        if ($excerpt === null || trim($excerpt) === '') {
            return null;
        }

        return 'Response body omitted for security. Review upstream endpoint logs if more detail is required.';
    }

    private function reportFailure(string $code, string $message, ?string $exceptionClass): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'log_context' => array_filter([
                'failure_code' => $code,
                'failure_message' => $message,
                'exception_class' => $exceptionClass,
            ]),
        ];
    }

    private function webhookFailure(
        string $code,
        string $category,
        string $message,
        ?int $responseStatus,
        ?string $exceptionClass,
    ): array {
        return [
            'code' => $code,
            'category' => $category,
            'message' => $message,
            'response_body_excerpt' => null,
            'log_context' => array_filter([
                'failure_code' => $code,
                'failure_category' => $category,
                'failure_message' => $message,
                'response_status' => $responseStatus,
                'exception_class' => $exceptionClass,
            ], fn ($value) => $value !== null),
        ];
    }

    private function isSafeReportMessage(string $message): bool
    {
        return in_array($message, [
            'The export could not run because the company record is no longer available.',
            'The selected report could not be generated with the current data.',
            'The export failed while generating the output file.',
        ], true);
    }

    private function isSafeWebhookMessage(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        return str_starts_with($message, 'Webhook target URL ')
            || in_array($message, [
                'Webhook endpoint is inactive.',
                'Webhook endpoint or integration event no longer exists.',
            ], true);
    }

    private function isSafeStoredWebhookMessage(string $message): bool
    {
        return in_array($message, [
            'Connection to the webhook endpoint failed before a response was received.',
            'Webhook delivery failed before a response was received.',
            'Endpoint could not be reached at the configured path (HTTP 404).',
        ], true)
            || str_starts_with($message, 'Endpoint rejected the delivery')
            || str_starts_with($message, 'Endpoint requested a retry later')
            || str_starts_with($message, 'Endpoint returned a server error')
            || str_starts_with($message, 'Endpoint returned an unexpected response');
    }

    private function looksLikeConnectionFailure(string $message): bool
    {
        $haystack = strtolower($message);

        return $haystack !== '' && (
            str_contains($haystack, 'timed out')
            || str_contains($haystack, 'connection refused')
            || str_contains($haystack, 'could not resolve')
            || str_contains($haystack, 'failed to connect')
            || str_contains($haystack, 'dns')
        );
    }
}
