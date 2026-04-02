<?php

namespace App\Support\Http;

use App\Support\Api\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ErrorResponseFactory
{
    /**
     * @var array<int>
     */
    private const HANDLED_STATUSES = [400, 401, 403, 404, 405, 419, 422, 429, 500, 503];

    public static function isApiV1Request(Request $request): bool
    {
        return $request->is('api/v1/*');
    }

    public static function shouldRenderJson(Request $request): bool
    {
        return self::isApiV1Request($request)
            || $request->expectsJson()
            || $request->wantsJson()
            || $request->is('api/*');
    }

    public static function isInertiaRequest(Request $request): bool
    {
        return $request->header(Header::INERTIA) === 'true';
    }

    public static function shouldPassThrough(Response $response, Throwable $exception): bool
    {
        if ($response->isRedirection()) {
            return true;
        }

        return $exception instanceof ValidationException
            || $exception instanceof AuthenticationException;
    }

    public static function resolveStatus(Response $response): int
    {
        $status = $response->getStatusCode();

        if ($status >= 500 && $status !== 503) {
            return 500;
        }

        if (in_array($status, self::HANDLED_STATUSES, true)) {
            return $status;
        }

        return $status;
    }

    public static function shouldHandleStatus(int $status): bool
    {
        return in_array($status, self::HANDLED_STATUSES, true);
    }

    public static function shouldRedirectBack(Request $request, int $status): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        return self::isInertiaRequest($request)
            || in_array($status, [419, 429, 500, 503], true);
    }

    public static function pageResponse(Request $request, int $status): Response
    {
        return tap(
            Inertia::render('error', self::pageProps($request, $status))->toResponse($request),
            fn (Response $response) => $response->setStatusCode($status),
        );
    }

    public static function jsonResponse(Request $request, int $status): JsonResponse
    {
        $payload = [
            'message' => self::content($status)['message'],
            'meta' => array_filter([
                'reference_id' => self::requestId($request),
            ]),
        ];

        if (self::isApiV1Request($request)) {
            return ApiErrorResponse::make($payload['message'], $status, meta: $payload['meta']);
        }

        return response()->json($payload, $status);
    }

    public static function preserveHeaders(Response $newResponse, Response $originalResponse): Response
    {
        foreach (['Allow', 'Retry-After'] as $header) {
            if ($originalResponse->headers->has($header)) {
                $newResponse->headers->set($header, $originalResponse->headers->get($header));
            }
        }

        return $newResponse;
    }

    public static function backRedirect(Request $request, int $status): RedirectResponse
    {
        $level = in_array($status, [419, 429], true) ? 'warning' : 'error';
        $target = self::previousUrl($request);

        return redirect()->to($target)->with($level, self::content($status)['message']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function pageProps(Request $request, int $status): array
    {
        $content = self::content($status);

        return [
            'status' => $status,
            'surface' => $request->user() ? 'app' : 'public',
            'title' => $content['title'],
            'message' => $content['message'],
            'details' => $content['details'],
            'reference' => self::requestId($request),
            'actions' => self::actions($request, $status),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function actions(Request $request, int $status): array
    {
        $home = route('home');
        $dashboard = self::dashboardUrl($request);
        $login = route('login');
        $help = route('public.contact-sales');
        $backFallback = $dashboard ?? $home;

        return match ($status) {
            400 => [
                self::backAction($backFallback),
                self::linkAction('Return home', $home, 'outline'),
            ],
            401 => [
                self::linkAction('Sign in', $login, 'default'),
                self::linkAction('Return home', $home, 'outline'),
            ],
            403 => array_values(array_filter([
                self::backAction($backFallback),
                $dashboard ? self::linkAction('Open dashboard', $dashboard, 'default') : self::linkAction('Return home', $home, 'default'),
            ])),
            404 => array_values(array_filter([
                self::backAction($backFallback),
                $dashboard ? self::linkAction('Open dashboard', $dashboard, 'default') : self::linkAction('Return home', $home, 'default'),
            ])),
            405 => [
                self::backAction($backFallback),
                self::linkAction('Return home', $home, 'outline'),
            ],
            419 => array_values(array_filter([
                self::reloadAction('Refresh page'),
                $request->user() ? self::linkAction('Open dashboard', $dashboard ?? $home, 'outline') : self::linkAction('Sign in again', $login, 'outline'),
            ])),
            422 => [
                self::backAction($backFallback),
                self::reloadAction('Refresh page', 'outline'),
            ],
            429 => [
                self::backAction($backFallback, 'Go back', 'default'),
                self::linkAction('Return home', $home, 'outline'),
            ],
            500 => array_values(array_filter([
                self::reloadAction('Try again'),
                self::linkAction('Get help', $help, 'outline'),
                $dashboard ? self::linkAction('Open dashboard', $dashboard, 'outline') : self::linkAction('Return home', $home, 'outline'),
            ])),
            503 => [
                self::reloadAction('Refresh page'),
                self::linkAction('Get help', $help, 'outline'),
                self::linkAction('Return home', $home, 'outline'),
            ],
            default => [
                self::reloadAction('Refresh page'),
                self::linkAction('Return home', $home, 'outline'),
            ],
        };
    }

    /**
     * @return array{title: string, message: string, details: string}
     */
    public static function content(int $status): array
    {
        return match ($status) {
            400 => [
                'title' => 'Request could not be completed',
                'message' => 'The request was incomplete or is no longer valid.',
                'details' => 'Refresh the page and try again. If the problem continues, start again from a known page in the product.',
            ],
            401 => [
                'title' => 'Sign in required',
                'message' => 'You need to sign in before you can continue.',
                'details' => 'If you already signed in, your session may have ended and you may need to authenticate again.',
            ],
            403 => [
                'title' => 'Access denied',
                'message' => 'You do not have permission to view this page or perform this action.',
                'details' => 'If you expected access here, check your company membership, assigned role, or ask an administrator to review your permissions.',
            ],
            404 => [
                'title' => 'Page not found',
                'message' => 'The page, record, or file you requested could not be found.',
                'details' => 'The link may be outdated, the record may have moved, or the URL may be incomplete.',
            ],
            405 => [
                'title' => 'Action not available',
                'message' => 'This route does not accept that type of request.',
                'details' => 'Return to the previous page and repeat the action from the product UI rather than reusing an outdated link or form.',
            ],
            419 => [
                'title' => 'Page expired',
                'message' => 'Your session or form token expired before the request finished.',
                'details' => 'Refresh the page, sign in again if needed, and repeat the action from the current screen.',
            ],
            422 => [
                'title' => 'Request could not be completed',
                'message' => 'The request could not be processed in its current state.',
                'details' => 'Review the current page state and try again. Field-level validation errors will continue to use the normal form flow.',
            ],
            429 => [
                'title' => 'Too many requests',
                'message' => 'Too many requests were sent from this browser or account in a short period.',
                'details' => 'Wait briefly before trying again. Repeated rapid requests can be temporarily limited for safety.',
            ],
            503 => [
                'title' => 'Service temporarily unavailable',
                'message' => 'Port-101 is temporarily unavailable or undergoing maintenance.',
                'details' => 'Try again in a few minutes. If the interruption lasts longer than expected, contact your administrator or support channel.',
            ],
            default => [
                'title' => 'Something went wrong',
                'message' => 'The server hit an unexpected problem while processing your request.',
                'details' => 'The issue has been logged. Use the reference below if you need help tracing this request.',
            ],
        };
    }

    /**
     * @return array<string, string|null>
     */
    private static function linkAction(string $label, string $href, string $variant = 'default'): array
    {
        return [
            'kind' => 'link',
            'label' => $label,
            'href' => $href,
            'fallback_href' => null,
            'variant' => $variant,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function backAction(string $fallbackHref, string $label = 'Go back', string $variant = 'default'): array
    {
        return [
            'kind' => 'back',
            'label' => $label,
            'href' => null,
            'fallback_href' => $fallbackHref,
            'variant' => $variant,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function reloadAction(string $label, string $variant = 'default'): array
    {
        return [
            'kind' => 'reload',
            'label' => $label,
            'href' => null,
            'fallback_href' => null,
            'variant' => $variant,
        ];
    }

    private static function previousUrl(Request $request): string
    {
        $previous = url()->previous();

        if (blank($previous)) {
            return route('home');
        }

        if (Str::startsWith($previous, $request->root()) && $previous !== $request->fullUrl()) {
            return $previous;
        }

        return route('home');
    }

    private static function requestId(Request $request): ?string
    {
        $requestId = $request->attributes->get('request_id');

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    private static function dashboardUrl(Request $request): ?string
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if ($user->is_super_admin) {
            return route('platform.dashboard');
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return null;
        }

        return route('dashboard');
    }
}
