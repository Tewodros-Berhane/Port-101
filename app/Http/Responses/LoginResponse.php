<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user && $user->is_super_admin) {
            return redirect()->intended(route('platform.dashboard'));
        }

        if (! $user) {
            return redirect()->route('login');
        }

        $activeCompany = $user->currentCompany;

        if (! $activeCompany || ! $activeCompany->is_active) {
            $activeCompany = $user->companies()
                ->where('companies.is_active', true)
                ->orderBy('companies.name')
                ->first();
        }

        if (! $activeCompany) {
            return redirect()
                ->route('company.inactive')
                ->with('warning', 'All assigned companies are currently inactive.');
        }

        if ($user->current_company_id !== $activeCompany->id) {
            $user->forceFill([
                'current_company_id' => $activeCompany->id,
            ])->save();
        }

        return redirect()->intended(route('company.dashboard'));
    }
}
