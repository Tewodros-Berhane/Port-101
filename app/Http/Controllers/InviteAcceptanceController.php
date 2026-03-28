<?php

namespace App\Http\Controllers;

use App\Core\Access\Models\Invite;
use App\Core\Company\Models\CompanyUser;
use App\Core\Notifications\NotificationGovernanceService;
use App\Core\RBAC\Models\Role;
use App\Http\Requests\InviteAcceptRequest;
use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Notifications\InviteAcceptedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InviteAcceptanceController extends Controller
{
    public function show(string $token): Response
    {
        $invite = Invite::query()
            ->with(['company:id,name', 'companyRole:id,name'])
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

        if (Auth::check()) {
            return Inertia::render('invites/accept', [
                'canAccept' => false,
                'message' => 'You are currently signed in. Please log out and open the invite link again to accept it.',
            ]);
        }

        return Inertia::render('invites/accept', [
            'canAccept' => true,
            'invite' => [
                'email' => $invite->email,
                'name' => $invite->name,
                'role' => $invite->companyRole?->name ?? $invite->role,
                'company' => $invite->company?->name,
            ],
            'token' => $token,
        ]);
    }

    public function store(
        InviteAcceptRequest $request,
        string $token,
        NotificationGovernanceService $notificationGovernance
    ): RedirectResponse {
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

        if ((in_array($invite->role, ['company_owner', 'company_member'], true) || $invite->company_role_id) && $invite->company_id) {
            $role = $invite->company_role_id
                ? Role::query()
                    ->whereKey($invite->company_role_id)
                    ->where(function ($query) use ($invite): void {
                        $query->whereNull('company_id')
                            ->orWhere('company_id', $invite->company_id);
                    })
                    ->first()
                : Role::query()
                    ->whereNull('company_id')
                    ->where('slug', $invite->role === 'company_owner' ? 'owner' : 'member')
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

        if ($invite->employee_id) {
            $employee = HrEmployee::query()
                ->whereKey($invite->employee_id)
                ->where('company_id', $invite->company_id)
                ->first();

            if (! $employee) {
                throw ValidationException::withMessages([
                    'token' => 'The linked employee record is no longer available for this invite.',
                ]);
            }

            if ($employee->user_id && (string) $employee->user_id !== (string) $user->id) {
                throw ValidationException::withMessages([
                    'token' => 'This employee record is already linked to a different user.',
                ]);
            }

            $employee->forceFill([
                'user_id' => $user->id,
                'requires_system_access' => true,
                'system_access_status' => HrEmployee::ACCESS_STATUS_ACTIVE,
                'system_role_id' => $invite->company_role_id,
                'login_email' => $invite->email,
                'invite_id' => $invite->id,
                'updated_by' => $user->id,
            ])->save();
        }

        $invite->forceFill(['accepted_at' => now()])->save();

        $invite->loadMissing('company:id,name');

        $companyName = $invite->company?->name ?? 'Platform';
        $acceptedBy = $user->name ?: 'User';

        $recipientIds = collect([$invite->created_by])
            ->merge(
                $invite->company_id
                    ? CompanyUser::query()
                        ->where('company_id', $invite->company_id)
                        ->where('is_owner', true)
                        ->pluck('user_id')
                    : collect()
            )
            ->filter()
            ->unique()
            ->reject(fn (string $recipientId) => $recipientId === $user->id)
            ->values();

        if ($recipientIds->isNotEmpty()) {
            $recipients = User::query()
                ->whereIn('id', $recipientIds->all())
                ->get();

            $notificationGovernance->notify(
                recipients: $recipients,
                notification: new InviteAcceptedNotification(
                    inviteeEmail: $invite->email,
                    companyName: $companyName,
                    acceptedBy: $acceptedBy
                ),
                severity: 'low',
                context: [
                    'event' => 'Invite accepted',
                    'source' => 'invites.acceptance',
                ]
            );
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
