<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user && $user->is_super_admin) {
            return redirect()->intended(route('platform.dashboard'));
        }

        return redirect()->intended(route('company.dashboard'));
    }
}
