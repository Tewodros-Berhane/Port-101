<?php

namespace App\Http\Controllers\Company;

use App\Core\RBAC\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolesController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('core.roles.view'), 403);

        $roles = Role::query()
            ->whereNull('company_id')
            ->with('permissions:id,name,slug,group')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'permission_count' => $role->permissions->count(),
                ];
            });

        return Inertia::render('company/roles', [
            'roles' => $roles,
        ]);
    }
}
