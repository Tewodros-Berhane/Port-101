<?php

use App\Core\Attachments\AttachmentMalwareScanService;
use App\Core\Attachments\AttachmentSecurityService;
use App\Core\Attachments\Models\Attachment;
use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Contact;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function createCompanyUserForAttachments(array $permissions): array
{
    $owner = User::factory()->create();
    $user = User::factory()->create();

    $companyName = 'Attachment Co '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);

    $role = Role::create([
        'name' => 'Attachment Role '.Str::upper(Str::random(4)),
        'slug' => 'attachment-role-'.Str::lower(Str::random(8)),
        'description' => 'Attachment test role',
        'company_id' => null,
    ]);

    $permissionIds = collect($permissions)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'core']
            )->id;
        })
        ->all();

    $role->permissions()->sync($permissionIds);

    $company->users()->attach($user->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill(['current_company_id' => $company->id])->save();

    return [$user, $company];
}

test('attachments can be uploaded downloaded and deleted for partner records', function () {
    Storage::fake('attachments');
    config()->set('core.attachments.disk', 'attachments');
    config()->set('core.attachments.scan.enabled', true);
    config()->set('core.attachments.scan.driver', 'basic');

    [$user, $company] = createCompanyUserForAttachments([
        'core.partners.manage',
        'core.attachments.view',
        'core.attachments.manage',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Attachment Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $file = UploadedFile::fake()->create('quote.pdf', 50, 'application/pdf');

    actingAs($user)
        ->post(route('core.attachments.store'), [
            'attachable_type' => 'partner',
            'attachable_id' => $partner->id,
            'file' => $file,
        ])
        ->assertRedirect();

    $attachment = Attachment::query()->latest('created_at')->first();

    expect($attachment)->not->toBeNull();
    expect($attachment?->attachable_type)->toBe(Partner::class);
    expect($attachment?->attachable_id)->toBe($partner->id);
    expect($attachment?->company_id)->toBe($company->id);
    expect($attachment?->scan_status)->toBe(Attachment::SCAN_CLEAN);
    expect($attachment?->scanned_at)->not->toBeNull();

    Storage::disk('attachments')->assertExists($attachment->path);

    actingAs($user)
        ->get(route('core.attachments.download', $attachment))
        ->assertOk();

    actingAs($user)
        ->delete(route('core.attachments.destroy', $attachment))
        ->assertRedirect();

    $deletedAttachment = Attachment::withTrashed()->find($attachment->id);

    expect($deletedAttachment?->trashed())->toBeTrue();
    Storage::disk('attachments')->assertMissing($attachment->path);
});

test('attachments reject file types outside the context allowlist', function () {
    Storage::fake('attachments');
    config()->set('core.attachments.disk', 'attachments');

    [$user, $company] = createCompanyUserForAttachments([
        'core.partners.manage',
        'core.attachments.view',
        'core.attachments.manage',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Attachment Restriction Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('core.attachments.store'), [
            'attachable_type' => 'partner',
            'attachable_id' => $partner->id,
            'file' => UploadedFile::fake()->create('payload.exe', 20, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors('file');

    expect(Attachment::query()->count())->toBe(0);
});

test('attachments cannot be uploaded to same-company records the user cannot update', function () {
    Storage::fake('attachments');
    config()->set('core.attachments.disk', 'attachments');

    [$user, $company] = createCompanyUserForAttachments([
        'core.attachments.view',
        'core.attachments.manage',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Attachment Unauthorized Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('core.attachments.store'), [
            'attachable_type' => 'partner',
            'attachable_id' => $partner->id,
            'file' => UploadedFile::fake()->create('quote.pdf', 50, 'application/pdf'),
        ])
        ->assertForbidden();

    expect(Attachment::query()->count())->toBe(0);
});

test('attachment security validation prefers server detected mime type', function () {
    $path = tempnam(sys_get_temp_dir(), 'attachment-mime-');

    file_put_contents($path, 'plain text payload masquerading as a pdf');

    $file = new UploadedFile(
        $path,
        'receipt.pdf',
        'application/pdf',
        null,
        true,
    );

    expect(app(AttachmentSecurityService::class)->detectedMimeType($file))->toBe('text/plain');

    expect(fn () => app(AttachmentSecurityService::class)->validateUpload($file, 'hr_reimbursement_receipt'))
        ->toThrow(ValidationException::class);

    @unlink($path);
});

test('infected attachments are quarantined and cannot be downloaded', function () {
    Storage::fake('attachments');
    config()->set('core.attachments.disk', 'attachments');
    config()->set('core.attachments.scan.enabled', true);
    config()->set('core.attachments.scan.driver', 'basic');

    [$user, $company] = createCompanyUserForAttachments([
        'core.partners.manage',
        'core.attachments.view',
        'core.attachments.manage',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Attachment Malware Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $path = 'attachments/'.$company->id.'/partner/eicar.txt';
    Storage::disk('attachments')->put($path, AttachmentMalwareScanService::EICAR_SIGNATURE);

    $attachment = Attachment::create([
        'company_id' => $company->id,
        'attachable_type' => $partner::class,
        'attachable_id' => $partner->id,
        'security_context' => 'partner',
        'disk' => 'attachments',
        'path' => $path,
        'file_name' => 'eicar.txt',
        'original_name' => 'eicar.txt',
        'mime_type' => 'text/plain',
        'extension' => 'txt',
        'size' => strlen(AttachmentMalwareScanService::EICAR_SIGNATURE),
        'checksum' => hash('sha256', AttachmentMalwareScanService::EICAR_SIGNATURE),
        'scan_status' => Attachment::SCAN_INFECTED,
        'scan_message' => 'EICAR signature detected during scan.',
        'scanned_at' => now(),
        'quarantined_at' => now(),
        'uploaded_by' => $user->id,
    ]);

    expect($attachment)->not->toBeNull()
        ->and($attachment->fresh()?->scan_status)->toBe(Attachment::SCAN_INFECTED)
        ->and($attachment->fresh()?->quarantined_at)->not->toBeNull();

    actingAs($user)
        ->get(route('core.attachments.download', $attachment))
        ->assertStatus(423);
});

test('basic attachment scanning is blocked outside local and testing unless explicitly allowed', function () {
    Storage::fake('attachments');
    config()->set('app.env', 'production');
    config()->set('core.attachments.disk', 'attachments');
    config()->set('core.attachments.scan.enabled', true);
    config()->set('core.attachments.scan.driver', 'basic');
    config()->set('core.attachments.scan.allow_basic_driver_in_non_local', false);

    [$user, $company] = createCompanyUserForAttachments([
        'core.partners.manage',
        'core.attachments.view',
        'core.attachments.manage',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Attachment Production Scan Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $file = UploadedFile::fake()->create('quote.pdf', 50, 'application/pdf');

    actingAs($user)
        ->post(route('core.attachments.store'), [
            'attachable_type' => 'partner',
            'attachable_id' => $partner->id,
            'file' => $file,
        ])
        ->assertRedirect();

    $attachment = Attachment::query()->latest('created_at')->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment?->scan_status)->toBe(Attachment::SCAN_FAILED)
        ->and($attachment?->scan_message)->toContain('not allowed outside local/testing');
});

test('attachment metadata is available on all supported master-data edit pages', function () {
    [$user, $company] = createCompanyUserForAttachments([
        'core.contacts.manage',
        'core.addresses.manage',
        'core.taxes.manage',
        'core.currencies.manage',
        'core.uoms.manage',
        'core.price_lists.manage',
        'core.attachments.view',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Attachment Relation Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $contact = Contact::create([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'name' => 'Attachment Contact',
        'email' => 'attachment-contact@example.com',
        'phone' => '555-0001',
        'title' => 'Manager',
        'is_primary' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $address = Address::create([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'type' => 'billing',
        'line1' => '10 Attachment Street',
        'line2' => null,
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '73301',
        'country_code' => 'US',
        'is_primary' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $tax = Tax::create([
        'company_id' => $company->id,
        'name' => 'Attachment Tax',
        'type' => 'percent',
        'rate' => 8.25,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $currency = Currency::create([
        'company_id' => $company->id,
        'code' => Str::upper(Str::random(3)),
        'name' => 'Attachment Currency',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $uom = Uom::create([
        'company_id' => $company->id,
        'name' => 'Attachment Unit',
        'symbol' => 'au',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $priceList = PriceList::create([
        'company_id' => $company->id,
        'name' => 'Attachment Price List',
        'currency_id' => $currency->id,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $records = [
        ['route' => 'core.contacts.edit', 'model' => $contact, 'type' => Contact::class],
        ['route' => 'core.addresses.edit', 'model' => $address, 'type' => Address::class],
        ['route' => 'core.taxes.edit', 'model' => $tax, 'type' => Tax::class],
        ['route' => 'core.currencies.edit', 'model' => $currency, 'type' => Currency::class],
        ['route' => 'core.uoms.edit', 'model' => $uom, 'type' => Uom::class],
        ['route' => 'core.price-lists.edit', 'model' => $priceList, 'type' => PriceList::class],
    ];

    foreach ($records as $index => $record) {
        $fileName = "module-{$index}.txt";

        Attachment::create([
            'company_id' => $company->id,
            'attachable_type' => $record['type'],
            'attachable_id' => $record['model']->id,
            'disk' => 'attachments',
            'path' => "attachments/test/{$fileName}",
            'file_name' => $fileName,
            'original_name' => $fileName,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 10,
            'checksum' => hash('sha256', $fileName),
            'uploaded_by' => $user->id,
        ]);

        actingAs($user)
            ->get(route($record['route'], $record['model']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('attachments', 1)
                ->where('attachments.0.original_name', $fileName)
            );
    }
});
