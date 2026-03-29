<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\HrEmployee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('core.users.manage'), 403);

        $company = $request->user()?->currentCompany;
        $employeesByUserId = $company
            ? HrEmployee::query()
                ->where('company_id', $company->id)
                ->whereNotNull('user_id')
                ->get(['id', 'user_id', 'display_name'])
                ->keyBy('user_id')
            : collect();

        $members = $company?->memberships()
            ->with(['user:id,name,email', 'role:id,name'])
            ->orderByDesc('is_owner')
            ->latest('created_at')
            ->get()
            ->map(function ($membership) use ($employeesByUserId) {
                $employee = $membership->user_id ? $employeesByUserId->get($membership->user_id) : null;

                return [
                    'id' => $membership->id,
                    'name' => $membership->user?->name,
                    'email' => $membership->user?->email,
                    'role_id' => $membership->role?->id,
                    'role' => $membership->role?->name,
                    'is_owner' => (bool) $membership->is_owner,
                    'employee' => $employee ? [
                        'id' => $employee->id,
                        'display_name' => $employee->display_name,
                    ] : null,
                ];
            });

        return Inertia::render('company/users', [
            'members' => $members,
        ]);
    }
}
