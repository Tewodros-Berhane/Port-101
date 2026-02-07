<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
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
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Your account is not assigned to an active company.');
        }

        if ($user->current_company_id !== $activeCompany->id) {
            $user->forceFill([
                'current_company_id' => $activeCompany->id,
            ])->save();
        }

        return redirect()->intended(route('company.dashboard'));
    }
}
