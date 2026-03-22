<?php

use App\Http\Middleware\EnsureCompanyMembership;
use App\Http\Middleware\EnsureCompanyWorkspaceUser;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventAuthenticatedPageCache;
use App\Http\Middleware\ResolveCompanyContext;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'company' => EnsureCompanyMembership::class,
            'company.workspace' => EnsureCompanyWorkspaceUser::class,
            'company.context' => ResolveCompanyContext::class,
            'superadmin' => EnsureSuperAdmin::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            ResolveCompanyContext::class,
            HandleInertiaRequests::class,
            PreventAuthenticatedPageCache::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiV1Request = static fn (Request $request): bool => $request->is('api/v1/*');

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiV1Request) {
            if (! $isApiV1Request($request)) {
                return null;
            }

            return ApiErrorResponse::make('Unauthenticated.', 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isApiV1Request) {
            if (! $isApiV1Request($request)) {
                return null;
            }

            $message = $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'This action is unauthorized.';

            return ApiErrorResponse::make($message, 403);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiV1Request) {
            if (! $isApiV1Request($request)) {
                return null;
            }

            return ApiErrorResponse::make(
                $exception->getMessage(),
                422,
                $exception->errors(),
            );
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($isApiV1Request) {
            if (! $isApiV1Request($request)) {
                return null;
            }

            return ApiErrorResponse::make('Resource not found.', 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($isApiV1Request) {
            if (! $isApiV1Request($request)) {
                return null;
            }

            return ApiErrorResponse::make('Resource not found.', 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($isApiV1Request) {
            if (! $isApiV1Request($request)) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $exception->getMessage();

            if ($message === '') {
                $message = match ($status) {
                    403 => 'This action is unauthorized.',
                    405 => 'Method not allowed.',
                    default => 'Request failed.',
                };
            }

            return ApiErrorResponse::make($message, $status);
        });
    })->create();
