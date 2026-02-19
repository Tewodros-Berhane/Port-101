<?php

use App\Core\Attachments\Models\Attachment;
use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

