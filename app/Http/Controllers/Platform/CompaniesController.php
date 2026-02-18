<?php

namespace App\Http\Controllers\Platform;

use App\Core\Company\Models\Company;
use App\Core\Company\Models\CompanyUser;
use App\Core\RBAC\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CompanyStoreRequest;
use App\Http\Requests\Platform\CompanyUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompaniesController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'owner_id' => $request->input('owner_id'),
        ];

        $companies = Company::query()
            ->with('owner:id,name,email')
            ->withCount('users')
            ->when($filters['search'], function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('owner', function ($ownerQuery) use ($search) {
                            $ownerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'], function ($query, string $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                }

                if ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->when($filters['owner_id'], function ($query, string $ownerId) {
                $query->where('owner_id', $ownerId);
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $ownerIds = Company::query()
            ->whereNotNull('owner_id')
            ->distinct()
            ->pluck('owner_id');

        $owners = $ownerIds->isNotEmpty()
            ? User::query()
                ->whereIn('id', $ownerIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : collect();

        return Inertia::render('platform/companies/index', [
            'companyRegistry' => $companies->through(function (Company $company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'owner' => $company->owner?->name,
                    'owner_email' => $company->owner?->email,
                    'is_active' => $company->is_active,
                    'users_count' => $company->users_count,
                ];
            }),
            'filters' => $filters,
            'owners' => $owners,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/companies/create', [
            'defaultTimezone' => config('app.timezone', 'UTC'),
        ]);
    }

    public function store(CompanyStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $slug = $data['slug'] ?: Str::slug($data['name']);
        $baseSlug = $slug;
        $suffix = 1;

        while (Company::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix += 1;
        }

        $owner = User::query()->where('email', $data['owner_email'])->first();

        if (! $owner) {
            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        $company = Company::create([
            'name' => $data['name'],
            'slug' => $slug,
            'timezone' => $data['timezone'] ?: config('app.timezone', 'UTC'),
            'currency_code' => $data['currency_code']
                ? strtoupper($data['currency_code'])
                : null,
            'is_active' => $data['is_active'],
            'owner_id' => $owner->id,
        ]);

        $ownerRole = Role::query()
            ->whereNull('company_id')
            ->where('slug', 'owner')
            ->first();

        CompanyUser::updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $owner->id],
            ['role_id' => $ownerRole?->id, 'is_owner' => true]
        );

        if (! $owner->current_company_id) {
            $owner->forceFill([
                'current_company_id' => $company->id,
            ])->save();
        }

        return redirect()
            ->route('platform.companies.show', $company)
            ->with('success', 'Company created.');
    }

    public function show(Company $company): Response
    {
        $company->load([
            'owner:id,name,email',
            'memberships.user:id,name,email',
            'memberships.role:id,name',
        ]);

        $owners = User::query()
            ->where('is_super_admin', false)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $memberships = $company->memberships->map(function (CompanyUser $membership) {
            return [
                'id' => $membership->id,
                'user' => [
                    'id' => $membership->user?->id,
                    'name' => $membership->user?->name,
                    'email' => $membership->user?->email,
                ],
                'role' => $membership->role?->name,
                'is_owner' => (bool) $membership->is_owner,
            ];
        });

        return Inertia::render('platform/companies/show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'timezone' => $company->timezone,
                'currency_code' => $company->currency_code,
                'is_active' => $company->is_active,
                'owner_id' => $company->owner_id,
                'owner' => $company->owner?->name,
                'owner_email' => $company->owner?->email,
                'created_at' => $company->created_at?->toIso8601String(),
            ],
            'owners' => $owners,
            'memberships' => $memberships,
        ]);
    }

    public function update(CompanyUpdateRequest $request, Company $company): RedirectResponse
    {
        $data = $request->validated();
        $ownerId = $data['owner_id'] ?? $company->owner_id;

        $company->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'timezone' => $data['timezone'] ?: config('app.timezone', 'UTC'),
            'currency_code' => $data['currency_code']
                ? strtoupper($data['currency_code'])
                : null,
            'is_active' => $data['is_active'],
            'owner_id' => $ownerId,
        ]);

        if ($ownerId) {
            $ownerRole = Role::query()
                ->whereNull('company_id')
                ->where('slug', 'owner')
                ->first();

            $company->memberships()
                ->where('is_owner', true)
                ->update(['is_owner' => false]);

            CompanyUser::updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $ownerId],
                ['role_id' => $ownerRole?->id, 'is_owner' => true]
            );

            $owner = User::query()->find($ownerId);

            if ($owner && ! $owner->current_company_id) {
                $owner->forceFill([
                    'current_company_id' => $company->id,
                ])->save();
            }
        }

        return redirect()
            ->route('platform.companies.show', $company)
            ->with('success', 'Company updated.');
    }
}
