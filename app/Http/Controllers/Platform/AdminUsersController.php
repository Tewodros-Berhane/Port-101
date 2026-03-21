<?php

namespace App\Http\Controllers\Platform;

use App\Core\Access\Models\Invite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AdminUserStoreRequest;
use App\Jobs\SendInviteLinkMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminUsersController extends Controller
{
    public function index(Request $request): Response
    {
        $activeAdmins = User::query()
            ->where('is_super_admin', true)
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => 'user-'.$user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => 'active',
                    'delivery_status' => null,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'expires_at' => null,
                    'accepted_at' => null,
                ];
            });

        $pendingInvites = Invite::query()
            ->where('role', 'platform_admin')
            ->whereNull('accepted_at')
            ->latest('created_at')
            ->get()
            ->map(function (Invite $invite) {
                $status = $invite->expires_at && $invite->expires_at->isPast()
                    ? 'expired_invite'
                    : 'pending_invite';

                return [
                    'id' => 'invite-'.$invite->id,
                    'name' => $invite->name ?: 'Pending platform admin',
                    'email' => $invite->email,
                    'status' => $status,
                    'delivery_status' => $invite->delivery_status,
                    'created_at' => $invite->created_at?->toIso8601String(),
                    'expires_at' => $invite->expires_at?->toIso8601String(),
                    'accepted_at' => null,
                ];
            });

        $rows = $activeAdmins
            ->concat($pendingInvites)
            ->sortByDesc('created_at')
            ->values();

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginatedRows = new LengthAwarePaginator(
            items: $rows->forPage($currentPage, $perPage)->values(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $currentPage,
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('platform/admin-users/index', [
            'admins' => $paginatedRows,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/admin-users/create');
    }

    public function store(AdminUserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $invite = Invite::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'platform_admin',
            'company_id' => null,
            'token' => Str::random(40),
            'expires_at' => now()->addDays(14),
            'delivery_status' => Invite::DELIVERY_PENDING,
            'delivery_attempts' => 0,
            'last_delivery_error' => null,
            'created_by' => $request->user()?->id,
        ]);

        SendInviteLinkMail::dispatch($invite->id);

        return redirect()
            ->route('platform.admin-users.index')
            ->with('success', 'Platform admin invite created and queued for delivery.');
    }
}
