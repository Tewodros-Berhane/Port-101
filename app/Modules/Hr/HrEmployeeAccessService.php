<?php

namespace App\Modules\Hr;

use App\Core\Access\Models\Invite;
use App\Core\Company\Models\CompanyUser;
use App\Core\RBAC\Models\Role;
use App\Jobs\SendInviteLinkMail;
use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HrEmployeeAccessService
{
    public function sync(HrEmployee $employee, array $attributes, User $actor): HrEmployee
    {
        $requiresSystemAccess = (bool) ($attributes['requires_system_access'] ?? false);
        $userId = filled($attributes['user_id'] ?? null) ? (string) $attributes['user_id'] : null;
        $systemRoleId = filled($attributes['system_role_id'] ?? null) ? (string) $attributes['system_role_id'] : null;
        $loginEmail = trim((string) ($attributes['login_email'] ?? ''));

        if (! $requiresSystemAccess) {
            return $this->clearPendingAccess($employee, $actor);
        }

        if (! $actor->hasPermission('hr.employee_access.manage')) {
            throw ValidationException::withMessages([
                'requires_system_access' => 'You do not have permission to grant system access.',
            ]);
        }

        $role = $this->resolveAssignableRole((string) $employee->company_id, $systemRoleId);

        if ($userId) {
            return $this->activateExistingUser($employee, $userId, $role, $actor);
        }

        return $this->createOrRefreshInvite($employee, $loginEmail, $role, $actor);
    }

    public function resendInvite(HrEmployee $employee, User $actor): HrEmployee
    {
        $this->assertManageAccess($employee, $actor);

        if ($employee->user_id) {
            throw ValidationException::withMessages([
                'employee' => 'This employee already has an active linked user account.',
            ]);
        }

        $loginEmail = trim((string) $employee->login_email);

        if ($loginEmail === '') {
            throw ValidationException::withMessages([
                'login_email' => 'Add a login email before resending the invite.',
            ]);
        }

        $role = $this->resolveAssignableRole((string) $employee->company_id, $employee->system_role_id);

        return $this->createOrRefreshInvite($employee, $loginEmail, $role, $actor);
    }

    public function cancelInvite(HrEmployee $employee, User $actor): HrEmployee
    {
        $this->assertManageAccess($employee, $actor);

        if ($employee->user_id) {
            throw ValidationException::withMessages([
                'employee' => 'This employee already has an active linked user account. Use deactivate access instead.',
            ]);
        }

        DB::transaction(function () use ($employee, $actor): void {
            if ($employee->invite_id) {
                Invite::query()
                    ->whereKey($employee->invite_id)
                    ->whereNull('accepted_at')
                    ->delete();
            }

            $employee->forceFill([
                'requires_system_access' => true,
                'system_access_status' => HrEmployee::ACCESS_STATUS_SUSPENDED,
                'invite_id' => null,
                'updated_by' => $actor->id,
            ])->save();
        });

        return $this->freshEmployee($employee);
    }

    public function deactivate(HrEmployee $employee, User $actor): HrEmployee
    {
        $this->assertManageAccess($employee, $actor);

        if (! $employee->user_id) {
            throw ValidationException::withMessages([
                'employee' => 'This employee does not have active system access to deactivate.',
            ]);
        }

        if ((string) $employee->user_id === (string) $actor->id) {
            throw ValidationException::withMessages([
                'employee' => 'You cannot deactivate your own system access from this screen.',
            ]);
        }

        $user = User::query()->findOrFail($employee->user_id);

        DB::transaction(function () use ($employee, $actor, $user): void {
            $membership = CompanyUser::query()
                ->where('company_id', $employee->company_id)
                ->where('user_id', $employee->user_id)
                ->first();

            if ($membership?->is_owner) {
                throw ValidationException::withMessages([
                    'employee' => 'Owner access cannot be deactivated from the employee screen.',
                ]);
            }

            $membership?->delete();

            if ((string) $user->current_company_id === (string) $employee->company_id) {
                $fallbackCompanyId = CompanyUser::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('is_owner')
                    ->orderByDesc('created_at')
                    ->value('company_id');

                $user->forceFill([
                    'current_company_id' => $fallbackCompanyId,
                ])->save();
            }

            if ($employee->invite_id) {
                Invite::query()
                    ->whereKey($employee->invite_id)
                    ->whereNull('accepted_at')
                    ->delete();
            }

            $employee->forceFill([
                'requires_system_access' => true,
                'system_access_status' => HrEmployee::ACCESS_STATUS_SUSPENDED,
                'invite_id' => null,
                'login_email' => $user->email,
                'updated_by' => $actor->id,
            ])->save();
        });

        return $this->freshEmployee($employee);
    }

    public function reactivate(HrEmployee $employee, User $actor): HrEmployee
    {
        $this->assertManageAccess($employee, $actor);

        if (! $employee->requires_system_access) {
            throw ValidationException::withMessages([
                'employee' => 'Enable system access on the employee record before reactivating access.',
            ]);
        }

        if ($employee->system_access_status === HrEmployee::ACCESS_STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'employee' => 'This employee already has active system access.',
            ]);
        }

        $role = $this->resolveAssignableRole((string) $employee->company_id, $employee->system_role_id);

        if ($employee->user_id) {
            return $this->reactivateExistingUser($employee, $role, $actor);
        }

        $loginEmail = trim((string) $employee->login_email);

        if ($loginEmail === '') {
            throw ValidationException::withMessages([
                'login_email' => 'Add a login email before reactivating access.',
            ]);
        }

        return $this->createOrRefreshInvite($employee, $loginEmail, $role, $actor);
    }

    public function updateRole(HrEmployee $employee, string $roleId, User $actor): HrEmployee
    {
        $this->assertManageAccess($employee, $actor);

        if (! $employee->requires_system_access) {
            throw ValidationException::withMessages([
                'role_id' => 'This employee does not require system access.',
            ]);
        }

        $role = $this->resolveAssignableRole((string) $employee->company_id, $roleId);

        DB::transaction(function () use ($employee, $role, $actor): void {
            if ($employee->system_access_status === HrEmployee::ACCESS_STATUS_ACTIVE && $employee->user_id) {
                $membership = CompanyUser::query()
                    ->where('company_id', $employee->company_id)
                    ->where('user_id', $employee->user_id)
                    ->first();

                if (! $membership) {
                    throw ValidationException::withMessages([
                        'role_id' => 'The linked user is no longer attached to this company. Reactivate access to restore membership first.',
                    ]);
                }

                if ($membership->is_owner) {
                    throw ValidationException::withMessages([
                        'role_id' => 'Owner access cannot be reassigned from the employee screen.',
                    ]);
                }

                $membership->forceFill([
                    'role_id' => $role->id,
                    'is_owner' => false,
                ])->save();
            }

            if ($employee->invite_id) {
                Invite::query()
                    ->whereKey($employee->invite_id)
                    ->whereNull('accepted_at')
                    ->update([
                        'company_role_id' => $role->id,
                        'updated_at' => now(),
                    ]);
            }

            $employee->forceFill([
                'requires_system_access' => true,
                'system_role_id' => $role->id,
                'updated_by' => $actor->id,
            ])->save();
        });

        return $this->freshEmployee($employee);
    }

    public function roleOptions(string $companyId): array
    {
        return Role::query()
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->where('slug', '!=', 'owner')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])
            ->values()
            ->all();
    }

    private function reactivateExistingUser(HrEmployee $employee, Role $role, User $actor): HrEmployee
    {
        $user = User::query()->findOrFail($employee->user_id);

        DB::transaction(function () use ($employee, $user, $role, $actor): void {
            CompanyUser::query()->updateOrCreate(
                [
                    'company_id' => $employee->company_id,
                    'user_id' => $user->id,
                ],
                [
                    'role_id' => $role->id,
                    'is_owner' => false,
                ],
            );

            if (! $user->current_company_id) {
                $user->forceFill([
                    'current_company_id' => $employee->company_id,
                ])->save();
            }

            $employee->forceFill([
                'requires_system_access' => true,
                'system_access_status' => HrEmployee::ACCESS_STATUS_ACTIVE,
                'system_role_id' => $role->id,
                'login_email' => $user->email,
                'updated_by' => $actor->id,
            ])->save();
        });

        return $this->freshEmployee($employee);
    }

    private function clearPendingAccess(HrEmployee $employee, User $actor): HrEmployee
    {
        if ($employee->user_id) {
            throw ValidationException::withMessages([
                'requires_system_access' => 'This employee already has active system access. Use a dedicated deactivation flow before removing access.',
            ]);
        }

        if ($employee->invite_id) {
            Invite::query()
                ->whereKey($employee->invite_id)
                ->whereNull('accepted_at')
                ->delete();
        }

        $employee->forceFill([
            'requires_system_access' => false,
            'system_access_status' => HrEmployee::ACCESS_STATUS_NONE,
            'system_role_id' => null,
            'login_email' => null,
            'invite_id' => null,
            'updated_by' => $actor->id,
        ])->save();

        return $this->freshEmployee($employee);
    }

    private function activateExistingUser(HrEmployee $employee, string $userId, Role $role, User $actor): HrEmployee
    {
        if ($employee->user_id && (string) $employee->user_id !== $userId) {
            throw ValidationException::withMessages([
                'user_id' => 'This employee is already linked to a different user account.',
            ]);
        }

        $membership = CompanyUser::query()
            ->where('company_id', $employee->company_id)
            ->where('user_id', $userId)
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user does not belong to this company.',
            ]);
        }

        $user = User::query()->findOrFail($userId);

        DB::transaction(function () use ($employee, $membership, $user, $role, $actor): void {
            $membership->forceFill([
                'role_id' => $role->id,
                'is_owner' => false,
            ])->save();

            if (! $user->current_company_id) {
                $user->forceFill(['current_company_id' => $employee->company_id])->save();
            }

            if ($employee->invite_id) {
                Invite::query()
                    ->whereKey($employee->invite_id)
                    ->whereNull('accepted_at')
                    ->delete();
            }

            $employee->forceFill([
                'user_id' => $user->id,
                'requires_system_access' => true,
                'system_access_status' => HrEmployee::ACCESS_STATUS_ACTIVE,
                'system_role_id' => $role->id,
                'login_email' => $user->email,
                'invite_id' => null,
                'updated_by' => $actor->id,
            ])->save();
        });

        return $this->freshEmployee($employee);
    }

    private function createOrRefreshInvite(HrEmployee $employee, string $loginEmail, Role $role, User $actor): HrEmployee
    {
        if ($employee->user_id) {
            throw ValidationException::withMessages([
                'user_id' => 'This employee is already linked to an active user account. Keep the linked user selected or use a dedicated access-change flow.',
            ]);
        }

        $invite = DB::transaction(function () use ($employee, $loginEmail, $role, $actor): Invite {
            $invite = $employee->invite_id
                ? Invite::query()
                    ->whereKey($employee->invite_id)
                    ->whereNull('accepted_at')
                    ->first()
                : null;

            if (! $invite) {
                $invite = Invite::create([
                    'email' => $loginEmail,
                    'name' => $employee->display_name,
                    'role' => 'company_member',
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'company_role_id' => $role->id,
                    'token' => Str::random(40),
                    'expires_at' => now()->addDays(14),
                    'delivery_status' => Invite::DELIVERY_PENDING,
                    'delivery_attempts' => 0,
                    'last_delivery_error' => null,
                    'created_by' => $actor->id,
                ]);
            } else {
                $invite->forceFill([
                    'email' => $loginEmail,
                    'name' => $employee->display_name,
                    'company_role_id' => $role->id,
                    'employee_id' => $employee->id,
                    'token' => Str::random(40),
                    'expires_at' => now()->addDays(14),
                    'delivery_status' => Invite::DELIVERY_PENDING,
                    'last_delivery_at' => null,
                    'last_delivery_error' => null,
                ])->save();
            }

            $employee->forceFill([
                'requires_system_access' => true,
                'system_access_status' => HrEmployee::ACCESS_STATUS_PENDING_INVITE,
                'system_role_id' => $role->id,
                'login_email' => $loginEmail,
                'invite_id' => $invite->id,
                'updated_by' => $actor->id,
            ])->save();

            return $invite;
        });

        SendInviteLinkMail::dispatch($invite->id)->afterCommit();

        return $this->freshEmployee($employee);
    }

    private function assertManageAccess(HrEmployee $employee, User $actor): void
    {
        if (! $actor->hasPermission('hr.employee_access.manage')) {
            throw ValidationException::withMessages([
                'employee' => 'You do not have permission to manage employee access.',
            ]);
        }
    }

    private function freshEmployee(HrEmployee $employee): HrEmployee
    {
        return $employee->fresh([
            'user:id,name,email',
            'systemRole:id,name,slug',
            'invite:id,email,accepted_at,expires_at,delivery_status,delivery_attempts,last_delivery_at,last_delivery_error',
        ]) ?? $employee;
    }

    private function resolveAssignableRole(string $companyId, ?string $roleId): Role
    {
        $role = Role::query()
            ->whereKey($roleId)
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->where('slug', '!=', 'owner')
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'system_role_id' => 'Select a valid company role for system access.',
            ]);
        }

        return $role;
    }
}
