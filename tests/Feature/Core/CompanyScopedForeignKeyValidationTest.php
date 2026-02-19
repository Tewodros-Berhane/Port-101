<?php

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Models\User;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

function createCompanyScopedValidationContext(): array
{
    $user = User::factory()->create();
    $otherOwner = User::factory()->create();

    $company = Company::create([
        'name' => 'Validation Home '.Str::upper(Str::random(4)),
        'slug' => 'validation-home-'.Str::lower(Str::random(6)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $user->id,
    ]);

    $otherCompany = Company::create([
        'name' => 'Validation Other '.Str::upper(Str::random(4)),
        'slug' => 'validation-other-'.Str::lower(Str::random(6)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $otherOwner->id,
    ]);

    $company->users()->attach($user->id, [
        'role_id' => null,
        'is_owner' => true,
    ]);

    $user->forceFill([
        'current_company_id' => $company->id,
    ])->save();

    return [$user, $company, $otherCompany];
}

test('contact store rejects partner id outside active company', function () {
    [$user, $company, $otherCompany] = createCompanyScopedValidationContext();

    $foreignPartner = Partner::create([
        'company_id' => $otherCompany->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Foreign Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->from(route('core.contacts.create'))
        ->post(route('core.contacts.store'), [
            'partner_id' => $foreignPartner->id,
            'name' => 'Scoped Contact',
            'email' => 'scoped-contact@example.com',
            'phone' => '555-0111',
            'title' => 'Lead',
            'is_primary' => true,
        ])
        ->assertSessionHasErrors(['partner_id'])
        ->assertRedirect(route('core.contacts.create'));

    expect(
        \App\Core\MasterData\Models\Contact::query()
            ->where('company_id', $company->id)
            ->where('name', 'Scoped Contact')
            ->exists()
    )->toBeFalse();
});

test('address update rejects partner id outside active company', function () {
    [$user, $company, $otherCompany] = createCompanyScopedValidationContext();

    $localPartner = Partner::create([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Local Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $foreignPartner = Partner::create([
        'company_id' => $otherCompany->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Foreign Partner',
        'type' => 'vendor',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $address = Address::create([
        'company_id' => $company->id,
        'partner_id' => $localPartner->id,
        'type' => 'billing',
        'line1' => '123 Main Street',
        'line2' => null,
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '73301',
        'country_code' => 'US',
        'is_primary' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->from(route('core.addresses.edit', $address))
        ->put(route('core.addresses.update', $address), [
            'partner_id' => $foreignPartner->id,
            'type' => 'shipping',
            'line1' => '456 Updated Street',
            'line2' => null,
            'city' => 'Dallas',
            'state' => 'TX',
            'postal_code' => '75201',
            'country_code' => 'US',
            'is_primary' => false,
        ])
        ->assertSessionHasErrors(['partner_id'])
        ->assertRedirect(route('core.addresses.edit', $address));

    expect($address->fresh()?->partner_id)->toBe($localPartner->id);
});

test('product store rejects uom and tax ids outside active company', function () {
    [$user, $company, $otherCompany] = createCompanyScopedValidationContext();

    $foreignUom = Uom::create([
        'company_id' => $otherCompany->id,
        'name' => 'Foreign Unit',
        'symbol' => 'fu',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $foreignTax = Tax::create([
        'company_id' => $otherCompany->id,
        'name' => 'Foreign Tax',
        'type' => 'percent',
        'rate' => 5,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->from(route('core.products.create'))
        ->post(route('core.products.store'), [
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => 'Scoped Product',
            'type' => 'stock',
            'uom_id' => $foreignUom->id,
            'default_tax_id' => $foreignTax->id,
            'description' => 'Validation scoped product',
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['uom_id', 'default_tax_id'])
        ->assertRedirect(route('core.products.create'));

    expect(
        Product::query()
            ->where('company_id', $company->id)
            ->where('name', 'Scoped Product')
            ->exists()
    )->toBeFalse();
});

test('price list update rejects currency id outside active company', function () {
    [$user, $company, $otherCompany] = createCompanyScopedValidationContext();

    $foreignCurrency = Currency::create([
        'company_id' => $otherCompany->id,
        'code' => Str::upper(Str::random(3)),
        'name' => 'Foreign Currency',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $priceList = PriceList::create([
        'company_id' => $company->id,
        'currency_id' => null,
        'name' => 'Scoped Price List',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->from(route('core.price-lists.edit', $priceList))
        ->put(route('core.price-lists.update', $priceList), [
            'name' => $priceList->name,
            'currency_id' => $foreignCurrency->id,
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['currency_id'])
        ->assertRedirect(route('core.price-lists.edit', $priceList));

    expect($priceList->fresh()?->currency_id)->toBeNull();
});

