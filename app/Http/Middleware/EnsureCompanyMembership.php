<?php

namespace App\Http\Middleware;

use App\Core\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyMembership
{
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

        $company = app(CompanyContext::class)->get();

        if (! $company && ! $user->is_super_admin) {
            abort(403, 'Company context required.');
        }

        if ($company && ! $user->is_super_admin) {
            $isMember = $user->companies()
                ->where('companies.id', $company->id)
                ->exists();

            if (! $isMember) {
                abort(403, 'Company access denied.');
            }
        }

        return $next($request);
    }
}
