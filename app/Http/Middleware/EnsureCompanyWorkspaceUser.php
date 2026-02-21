<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyWorkspaceUser
{
    private function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->is_super_admin) {
            if ($this->shouldReturnJson($request)) {
                return response()->json([
                    'message' => 'Company workspace routes are not available to super admins.',
                    'code' => 'company_workspace_forbidden',
                ], 403);
            }

            return redirect()
                ->route('platform.dashboard')
                ->with('warning', 'Super admins cannot access company workspace routes.');
        }

        return $next($request);
    }
}
