<?php

namespace App\Support\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StructuredLogContext
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $scopes = [];

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge(
            [
                'app' => config('app.name'),
                'environment' => app()->environment(),
            ],
            ...array_values($this->scopes),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function putScope(string $scope, array $context): void
    {
        $this->scopes[$scope] = $this->filterContext($context);
    }

    public function clearScope(string $scope): void
    {
        unset($this->scopes[$scope]);
    }

    public function clearAll(): void
    {
        $this->scopes = [];
    }

    public function setRequestContext(Request $request): void
    {
        $route = $request->route();
        $user = $request->user();
        $companyId = $user?->current_company_id ?: $request->attributes->get('company_id');
        $routeName = $route?->getName();
        $routeUri = $route?->uri();
        $path = trim($request->path(), '/');
        $parsed = $this->parseRouteContext($routeName, $path, $request->method());

        $this->putScope('runtime', [
            'runtime' => 'http',
            'request_id' => $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id'),
        ]);

        $this->putScope('request', [
            'request_id' => $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id'),
            'request_method' => $request->method(),
            'request_path' => '/'.$path,
            'route_name' => $routeName,
            'route_uri' => $routeUri,
            'user_id' => $user?->id,
            'company_id' => $companyId,
            'module' => $parsed['module'],
            'entity' => $parsed['entity'],
            'action' => $parsed['action'],
        ]);
    }

    public function setConsoleContext(?string $commandName = null, ?string $requestId = null): void
    {
        $this->putScope('runtime', [
            'runtime' => 'console',
            'request_id' => $requestId ?: (string) Str::uuid(),
            'console_command' => $commandName,
            'module' => 'console',
            'action' => $commandName,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function setQueueContext(array $context): void
    {
        $this->putScope('queue', [
            'runtime' => 'queue',
            ...$context,
        ]);
    }

    public function currentRequestId(): ?string
    {
        return $this->scopeValue('queue', 'request_id')
            ?? $this->scopeValue('request', 'request_id')
            ?? $this->scopeValue('runtime', 'request_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function queuePropagationContext(): array
    {
        return $this->filterContext([
            'request_id' => $this->currentRequestId() ?: (string) Str::uuid(),
            'company_id' => $this->scopeValue('request', 'company_id')
                ?? $this->scopeValue('queue', 'company_id'),
            'user_id' => $this->scopeValue('request', 'user_id')
                ?? $this->scopeValue('queue', 'user_id'),
            'parent_job_id' => $this->scopeValue('queue', 'job_id'),
            'correlation_origin' => $this->scopeValue('runtime', 'runtime'),
        ]);
    }

    /**
     * @return array{module: ?string, entity: ?string, action: ?string}
     */
    private function parseRouteContext(?string $routeName, string $path, string $method): array
    {
        if (is_string($routeName) && trim($routeName) !== '') {
            $segments = array_values(array_filter(explode('.', $routeName)));
            $segments = array_values(array_filter(
                $segments,
                fn (string $segment) => ! in_array($segment, ['company', 'platform', 'api', 'v1', 'modules'], true),
            ));

            $module = $segments[0] ?? null;
            $entity = $segments[1] ?? $module;
            $action = count($segments) >= 3 ? Arr::last($segments) : Str::lower($method);

            return [
                'module' => $module,
                'entity' => $entity,
                'action' => $action,
            ];
        }

        $pathSegments = array_values(array_filter(explode('/', $path)));

        if (($pathSegments[0] ?? null) === 'api' && ($pathSegments[1] ?? null) === 'v1') {
            $module = $pathSegments[2] ?? 'api';
            $entity = $pathSegments[3] ?? $module;
            $actionSegment = Arr::last($pathSegments);
            $action = ($actionSegment && ! $this->looksLikeIdentifier($actionSegment))
                ? $actionSegment
                : Str::lower($method);

            return [
                'module' => $module,
                'entity' => $entity,
                'action' => $action,
            ];
        }

        $module = $pathSegments[0] ?? null;
        $entity = $pathSegments[1] ?? $module;

        return [
            'module' => $module,
            'entity' => $entity,
            'action' => Str::lower($method),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function filterContext(array $context): array
    {
        return array_filter($context, fn ($value) => $value !== null && $value !== '');
    }

    private function scopeValue(string $scope, string $key): mixed
    {
        return $this->scopes[$scope][$key] ?? null;
    }

    private function looksLikeIdentifier(string $value): bool
    {
        if (Str::isUuid($value)) {
            return true;
        }

        return preg_match('/^[0-9]+$/', $value) === 1;
    }
}
