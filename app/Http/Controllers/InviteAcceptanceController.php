<?php

namespace App\Http\Controllers;

use App\Core\Access\Models\Invite;
use App\Core\Company\Models\CompanyUser;
use App\Core\RBAC\Models\Role;
use App\Http\Requests\InviteAcceptRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class InviteAcceptanceController extends Controller
{
    public function show(string $token): Response
    {
        $invite = Invite::query()
            ->with('company:id,name')
            ->where('token', $token)
            ->first();

        if (! $invite) {
            return Inertia::render('invites/accept', [
                'canAccept' => false,
                'message' => 'This invite link is invalid.',
            ]);
        }

        if ($invite->accepted_at) {
            return Inertia::render('invites/accept', [
                'canAccept' => false,
                'message' => 'This invite has already been accepted.',
            ]);
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            return Inertia::render('invites/accept', [
                'canAccept' => false,
                'message' => 'This invite has expired.',
            ]);
        }

        return Inertia::render('invites/accept', [
            'canAccept' => true,
            'invite' => [
                'email' => $invite->email,
                'name' => $invite->name,
                'role' => $invite->role,
                'company' => $invite->company?->name,
            ],
            'token' => $token,
        ]);
    }

    public function store(InviteAcceptRequest $request, string $token): RedirectResponse
    {
        $invite = Invite::query()->where('token', $token)->firstOrFail();

        if ($invite->accepted_at || ($invite->expires_at && $invite->expires_at->isPast())) {
            return redirect()->route('login')->with('status', 'Invite is no longer valid.');
        }

        $data = $request->validated();
        $user = User::query()->where('email', $invite->email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $invite->email,
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($invite->role === 'platform_admin') {
            $user->forceFill(['is_super_admin' => true])->save();
        }

        if (in_array($invite->role, ['company_owner', 'company_member'], true) && $invite->company_id) {
            $roleSlug = $invite->role === 'company_owner' ? 'owner' : 'member';
            $role = Role::query()
                ->whereNull('company_id')
                ->where('slug', $roleSlug)
                ->first();

            CompanyUser::updateOrCreate(
                [
                    'company_id' => $invite->company_id,
                    'user_id' => $user->id,
                ],
                [
                    'role_id' => $role?->id,
                    'is_owner' => $invite->role === 'company_owner',
                ]
            );

            if (! $user->current_company_id) {
                $user->forceFill([
                    'current_company_id' => $invite->company_id,
                ])->save();
            }
        }

        $invite->forceFill(['accepted_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
