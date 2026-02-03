<?php

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Contact;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

function createCompanyUserWithPermissions(array $permissions = []): array
{
    $user = User::factory()->create();

    $companyName = 'Test Company '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => config('app.timezone', 'UTC'),
        'owner_id' => $user->id,
    ]);

    $role = Role::create([
        'name' => 'Role '.Str::upper(Str::random(4)),
        'slug' => 'role-'.Str::lower(Str::random(8)),
        'description' => 'Test role',
        'company_id' => null,
    ]);

    if ($permissions !== []) {
        $permissionIds = collect($permissions)
            ->map(function (string $slug) {
                return Permission::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $slug, 'group' => 'master_data']
                )->id;
            })
            ->all();

        $role->permissions()->sync($permissionIds);
    }

    $company->users()->attach($user->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill([
        'current_company_id' => $company->id,
    ])->save();

    return [$user, $company, $role];
}

function makePartner(Company $company, User $user, array $overrides = []): Partner
{
    return Partner::create(array_merge([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Partner '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'email' => 'partner.'.Str::lower(Str::random(4)).'@example.com',
        'phone' => '555-0100',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function makeContact(Company $company, User $user, array $overrides = []): Contact
{
    $partner = makePartner($company, $user);

    return Contact::create(array_merge([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'name' => 'Contact '.Str::upper(Str::random(4)),
        'email' => 'contact.'.Str::lower(Str::random(4)).'@example.com',
        'phone' => '555-0110',
        'title' => 'Manager',
        'is_primary' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function makeAddress(Company $company, User $user, array $overrides = []): Address
{
    $partner = makePartner($company, $user);

    return Address::create(array_merge([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'type' => 'billing',
        'line1' => '123 Market Street',
        'line2' => 'Suite 200',
        'city' => 'Lagos',
        'state' => 'LA',
        'postal_code' => '10001',
        'country_code' => 'NG',
        'is_primary' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

dataset('masterDataResources', array_map(
    static fn (array $resource) => [$resource],
    [
    'partners' => [
        'view' => 'core.partners.view',
        'manage' => 'core.partners.manage',
        'routes' => [
            'index' => 'core.partners.index',
            'create' => 'core.partners.create',
            'store' => 'core.partners.store',
            'edit' => 'core.partners.edit',
            'update' => 'core.partners.update',
            'destroy' => 'core.partners.destroy',
        ],
        'record' => fn (Company $company, User $user) => makePartner($company, $user),
        'store' => fn (Company $company, User $user) => [
            'code' => 'P-'.Str::upper(Str::random(6)),
            'name' => 'Partner '.Str::upper(Str::random(4)),
            'type' => 'vendor',
            'email' => 'partner.'.Str::lower(Str::random(4)).'@example.com',
            'phone' => '555-0101',
            'is_active' => true,
        ],
        'update' => fn (Company $company, User $user, Partner $record) => [
            'code' => $record->code.'-U',
            'name' => $record->name.' Updated',
            'type' => 'customer',
            'email' => 'updated.'.Str::lower(Str::random(4)).'@example.com',
            'phone' => '555-0102',
            'is_active' => false,
        ],
    ],
    'contacts' => [
        'view' => 'core.contacts.view',
        'manage' => 'core.contacts.manage',
        'routes' => [
            'index' => 'core.contacts.index',
            'create' => 'core.contacts.create',
            'store' => 'core.contacts.store',
            'edit' => 'core.contacts.edit',
            'update' => 'core.contacts.update',
            'destroy' => 'core.contacts.destroy',
        ],
        'record' => fn (Company $company, User $user) => makeContact($company, $user),
        'store' => function (Company $company, User $user) {
            $partner = makePartner($company, $user);

            return [
                'partner_id' => $partner->id,
                'name' => 'Contact '.Str::upper(Str::random(4)),
                'email' => 'contact.'.Str::lower(Str::random(4)).'@example.com',
                'phone' => '555-0111',
                'title' => 'Director',
                'is_primary' => true,
            ];
        },
        'update' => fn (Company $company, User $user, Contact $record) => [
            'partner_id' => $record->partner_id,
            'name' => $record->name.' Updated',
            'email' => null,
            'phone' => '555-0112',
            'title' => 'Lead',
            'is_primary' => false,
        ],
    ],
    'addresses' => [
        'view' => 'core.addresses.view',
        'manage' => 'core.addresses.manage',
        'routes' => [
            'index' => 'core.addresses.index',
            'create' => 'core.addresses.create',
            'store' => 'core.addresses.store',
            'edit' => 'core.addresses.edit',
            'update' => 'core.addresses.update',
            'destroy' => 'core.addresses.destroy',
        ],
        'record' => fn (Company $company, User $user) => makeAddress($company, $user),
        'store' => function (Company $company, User $user) {
            $partner = makePartner($company, $user);

            return [
                'partner_id' => $partner->id,
                'type' => 'billing',
                'line1' => '456 Broad Street',
                'line2' => 'Suite 210',
                'city' => 'Abuja',
                'state' => 'FC',
                'postal_code' => '90001',
                'country_code' => 'NG',
                'is_primary' => true,
            ];
        },
        'update' => fn (Company $company, User $user, Address $record) => [
            'partner_id' => $record->partner_id,
            'type' => 'shipping',
            'line1' => '789 Harbor Road',
            'line2' => null,
            'city' => 'Port Harcourt',
            'state' => 'RI',
            'postal_code' => '50001',
            'country_code' => 'NG',
            'is_primary' => false,
        ],
    ],
    'products' => [
        'view' => 'core.products.view',
        'manage' => 'core.products.manage',
        'routes' => [
            'index' => 'core.products.index',
            'create' => 'core.products.create',
            'store' => 'core.products.store',
            'edit' => 'core.products.edit',
            'update' => 'core.products.update',
            'destroy' => 'core.products.destroy',
        ],
        'record' => fn (Company $company, User $user) => Product::create([
            'company_id' => $company->id,
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => 'Product '.Str::upper(Str::random(4)),
            'type' => 'stock',
            'uom_id' => null,
            'default_tax_id' => null,
            'description' => 'Test product',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]),
        'store' => fn (Company $company, User $user) => [
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => 'Product '.Str::upper(Str::random(4)),
            'type' => 'stock',
            'uom_id' => null,
            'default_tax_id' => null,
            'description' => 'Sample product',
            'is_active' => true,
        ],
        'update' => fn (Company $company, User $user, Product $record) => [
            'sku' => $record->sku,
            'name' => $record->name.' Updated',
            'type' => 'service',
            'uom_id' => null,
            'default_tax_id' => null,
            'description' => 'Updated description',
            'is_active' => false,
        ],
    ],
    'taxes' => [
        'view' => 'core.taxes.view',
        'manage' => 'core.taxes.manage',
        'routes' => [
            'index' => 'core.taxes.index',
            'create' => 'core.taxes.create',
            'store' => 'core.taxes.store',
            'edit' => 'core.taxes.edit',
            'update' => 'core.taxes.update',
            'destroy' => 'core.taxes.destroy',
        ],
        'record' => fn (Company $company, User $user) => Tax::create([
            'company_id' => $company->id,
            'name' => 'Tax '.Str::upper(Str::random(4)),
            'type' => 'percent',
            'rate' => 10,
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]),
        'store' => fn (Company $company, User $user) => [
            'name' => 'Tax '.Str::upper(Str::random(4)),
            'type' => 'percent',
            'rate' => 12.5,
            'is_active' => true,
        ],
        'update' => fn (Company $company, User $user, Tax $record) => [
            'name' => $record->name.' Updated',
            'type' => 'fixed',
            'rate' => 5,
            'is_active' => false,
        ],
    ],
    'currencies' => [
        'view' => 'core.currencies.view',
        'manage' => 'core.currencies.manage',
        'routes' => [
            'index' => 'core.currencies.index',
            'create' => 'core.currencies.create',
            'store' => 'core.currencies.store',
            'edit' => 'core.currencies.edit',
            'update' => 'core.currencies.update',
            'destroy' => 'core.currencies.destroy',
        ],
        'record' => fn (Company $company, User $user) => Currency::create([
            'company_id' => $company->id,
            'code' => Str::upper(Str::random(3)),
            'name' => 'Currency '.Str::upper(Str::random(3)),
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]),
        'store' => fn (Company $company, User $user) => [
            'code' => Str::upper(Str::random(3)),
            'name' => 'Currency '.Str::upper(Str::random(3)),
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
        ],
        'update' => fn (Company $company, User $user, Currency $record) => [
            'code' => $record->code,
            'name' => $record->name.' Updated',
            'symbol' => '€',
            'decimal_places' => 3,
            'is_active' => false,
        ],
    ],
    'uoms' => [
        'view' => 'core.uoms.view',
        'manage' => 'core.uoms.manage',
        'routes' => [
            'index' => 'core.uoms.index',
            'create' => 'core.uoms.create',
            'store' => 'core.uoms.store',
            'edit' => 'core.uoms.edit',
            'update' => 'core.uoms.update',
            'destroy' => 'core.uoms.destroy',
        ],
        'record' => fn (Company $company, User $user) => Uom::create([
            'company_id' => $company->id,
            'name' => 'Unit '.Str::upper(Str::random(4)),
            'symbol' => 'u',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]),
        'store' => fn (Company $company, User $user) => [
            'name' => 'Unit '.Str::upper(Str::random(4)),
            'symbol' => 'u',
            'is_active' => true,
        ],
        'update' => fn (Company $company, User $user, Uom $record) => [
            'name' => $record->name.' Updated',
            'symbol' => 'unit',
            'is_active' => false,
        ],
    ],
    'price_lists' => [
        'view' => 'core.price_lists.view',
        'manage' => 'core.price_lists.manage',
        'routes' => [
            'index' => 'core.price-lists.index',
            'create' => 'core.price-lists.create',
            'store' => 'core.price-lists.store',
            'edit' => 'core.price-lists.edit',
            'update' => 'core.price-lists.update',
            'destroy' => 'core.price-lists.destroy',
        ],
        'record' => fn (Company $company, User $user) => PriceList::create([
            'company_id' => $company->id,
            'name' => 'Price List '.Str::upper(Str::random(4)),
            'currency_id' => null,
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]),
        'store' => fn (Company $company, User $user) => [
            'name' => 'Price List '.Str::upper(Str::random(4)),
            'currency_id' => null,
            'is_active' => true,
        ],
        'update' => fn (Company $company, User $user, PriceList $record) => [
            'name' => $record->name.' Updated',
            'currency_id' => null,
            'is_active' => false,
        ],
    ],
    ]
));

test('master data index allows users with view permission', function (array $resource) {
    [$user] = createCompanyUserWithPermissions([$resource['view']]);

    actingAs($user)
        ->get(route($resource['routes']['index']))
        ->assertOk();
})->with('masterDataResources');

test('master data index denies users without view permission', function (array $resource) {
    [$user] = createCompanyUserWithPermissions([]);

    actingAs($user)
        ->get(route($resource['routes']['index']))
        ->assertForbidden();
})->with('masterDataResources');

test('master data manage routes allow users with manage permission', function (array $resource) {
    [$user, $company] = createCompanyUserWithPermissions([$resource['manage']]);

    actingAs($user)
        ->get(route($resource['routes']['create']))
        ->assertOk();

    actingAs($user)
        ->post(route($resource['routes']['store']), ($resource['store'])($company, $user))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $record = ($resource['record'])($company, $user);

    actingAs($user)
        ->get(route($resource['routes']['edit'], $record))
        ->assertOk();

    actingAs($user)
        ->put(
            route($resource['routes']['update'], $record),
            ($resource['update'])($company, $user, $record)
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    actingAs($user)
        ->delete(route($resource['routes']['destroy'], $record))
        ->assertRedirect();
})->with('masterDataResources');

test('master data manage routes deny users without manage permission', function (array $resource) {
    [$user, $company] = createCompanyUserWithPermissions([$resource['view']]);

    actingAs($user)
        ->get(route($resource['routes']['create']))
        ->assertForbidden();

    actingAs($user)
        ->post(route($resource['routes']['store']), ($resource['store'])($company, $user))
        ->assertForbidden();

    $record = ($resource['record'])($company, $user);

    actingAs($user)
        ->get(route($resource['routes']['edit'], $record))
        ->assertForbidden();

    actingAs($user)
        ->put(
            route($resource['routes']['update'], $record),
            ($resource['update'])($company, $user, $record)
        )
        ->assertForbidden();

    actingAs($user)
        ->delete(route($resource['routes']['destroy'], $record))
        ->assertForbidden();
})->with('masterDataResources');
