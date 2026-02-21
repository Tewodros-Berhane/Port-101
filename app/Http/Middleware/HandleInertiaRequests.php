<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $company = $user?->currentCompany;
        $permissions = $user
            ? $user->permissionsForCompany($company)
            : [];
        $canViewNotifications = in_array('core.notifications.view', $permissions, true);

        $recentNotifications = [];
        if ($user && $canViewNotifications) {
            $recentNotifications = $user->notifications()
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($notification) {
                    $payload = is_array($notification->data)
                        ? $notification->data
                        : [];

                    return [
                        'id' => $notification->id,
                        'title' => $payload['title'] ?? 'Notification',
                        'message' => $payload['message'] ?? '',
                        'url' => $payload['url'] ?? null,
                        'severity' => $payload['severity'] ?? 'low',
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'company' => $company
                ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'is_active' => (bool) $company->is_active,
                ]
                : null,
            'companies' => $user
                ? $user->companies()
                    ->select('companies.id', 'companies.name', 'companies.slug', 'companies.is_active')
                    ->withPivot(['role_id', 'is_owner'])
                    ->get()
                    ->map(function ($company) {
                        return [
                            'id' => $company->id,
                            'name' => $company->name,
                            'slug' => $company->slug,
                            'is_active' => (bool) $company->is_active,
                            'role_id' => $company->pivot?->role_id,
                            'is_owner' => (bool) $company->pivot?->is_owner,
                        ];
                    })
                : [],
            'permissions' => $permissions,
            'notifications' => [
                'unread_count' => $user ? $user->unreadNotifications()->count() : 0,
                'recent' => $recentNotifications,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
