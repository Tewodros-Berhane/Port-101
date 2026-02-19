<?php

namespace App\Http\Controllers\Company;

use App\Core\Company\Models\CompanyUser;
use App\Core\Notifications\NotificationGovernanceService;
use App\Core\RBAC\Models\Role;
use App\Http\Controllers\Controller;
use App\Notifications\CompanyRoleUpdatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        $company = $request->user()?->currentCompany;

        $members = $company?->memberships()
            ->with(['user:id,name,email', 'role:id,name'])
            ->orderByDesc('is_owner')
            ->latest('created_at')
            ->get()
            ->map(function ($membership) {
                return [
                    'id' => $membership->id,
                    'name' => $membership->user?->name,
                    'email' => $membership->user?->email,
                    'role_id' => $membership->role?->id,
                    'role' => $membership->role?->name,
                    'is_owner' => (bool) $membership->is_owner,
                ];
            });

        $roles = Role::query()
            ->where(function ($query) use ($company) {
                $query->whereNull('company_id');

                if ($company) {
                    $query->orWhere('company_id', $company->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ];
            });

        return Inertia::render('company/users', [
            'members' => $members,
            'roles' => $roles,
        ]);
    }

    public function updateRole(
        Request $request,
        CompanyUser $membership,
        NotificationGovernanceService $notificationGovernance
    ): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company || $membership->company_id !== $company->id) {
            abort(403);
        }

        if ($membership->is_owner) {
            return redirect()
                ->route('company.users.index')
                ->with('error', 'Owner role assignment cannot be changed from this screen.');
        }

        $data = $request->validate([
            'role_id' => ['required', 'uuid'],
        ]);

        $role = Role::query()
            ->where('id', $data['role_id'])
            ->where(function ($query) use ($company) {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $company->id);
            })
            ->first();

        if (! $role) {
            return redirect()
                ->route('company.users.index')
                ->with('error', 'Selected role is not available for this company.');
        }

        $membership->update([
            'role_id' => $role->id,
        ]);

        $targetUser = $membership->user;
        $actor = $request->user();

        if ($targetUser && $actor && $targetUser->id !== $actor->id) {
            $notificationGovernance->notify(
                recipients: [$targetUser],
                notification: new CompanyRoleUpdatedNotification(
                    companyName: $company->name,
                    roleName: $role->name,
                    updatedBy: $actor->name
                ),
                severity: 'medium',
                context: [
                    'event' => 'Company role updated',
                    'source' => 'company.users',
                ]
            );
        }

        return redirect()
            ->route('company.users.index')
            ->with('success', 'User role updated.');
    }
}
