<?php

namespace App\Http\Middleware;

use App\Core\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyContext
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

        $company = $user->currentCompany;

        if (! $user->is_super_admin) {
            if ($company && ! $company->is_active) {
                $company = null;
            }

            if (! $company) {
                $company = $user->companies()
                    ->where('companies.is_active', true)
                    ->orderBy('companies.name')
                    ->first();

                $user->forceFill([
                    'current_company_id' => $company?->id,
                ])->save();
            }
        }

        app(CompanyContext::class)->set($company);

        return $next($request);
    }
}
