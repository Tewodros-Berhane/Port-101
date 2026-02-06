<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
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
                    'role' => $membership->role?->name,
                    'is_owner' => (bool) $membership->is_owner,
                ];
            });

        return Inertia::render('company/users', [
            'members' => $members,
        ]);
    }
}
