<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AdminUserStoreRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminUsersController extends Controller
{
    public function index(): Response
    {
        $admins = User::query()
            ->where('is_super_admin', true)
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('platform/admin-users/index', [
            'admins' => $admins->through(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/admin-users/create');
    }

    public function store(AdminUserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),
            'is_super_admin' => true,
        ]);

        return redirect()
            ->route('platform.admin-users.index')
            ->with('success', 'Platform admin created.');
    }
}
