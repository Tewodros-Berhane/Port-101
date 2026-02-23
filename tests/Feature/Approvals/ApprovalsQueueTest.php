<?php

use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignApprovalsRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Approvals Role '.Str::upper(Str::random(4)),
        'slug' => 'approvals-role-'.Str::lower(Str::random(8)),
        'description' => 'Approvals workflow role',
        'data_scope' => User::DATA_SCOPE_COMPANY,
        'company_id' => null,
    ]);

    $permissionIds = collect($permissionSlugs)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'approvals']
            )->id;
        })
        ->all();

    $role->permissions()->sync($permissionIds);

    $user->memberships()->updateOrCreate(
        ['company_id' => $companyId],
        [
            'role_id' => $role->id,
            'is_owner' => false,
        ],
    );

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

test('approvals queue syncs pending requests and supports approve/reject actions', function () {
    [$requester, $company] = makeActiveCompanyMember();
    $approver = User::factory()->create();

    assignApprovalsRole($approver, $company->id, [
        'approvals.requests.view',
        'approvals.requests.manage',
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Approval Partner '.Str::upper(Str::random(4)),
        'type' => 'both',
        'is_active' => true,
        'created_by' => $requester->id,
        'updated_by' => $requester->id,
    ]);

    $quote = SalesQuote::create([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'quote_number' => 'QT-'.Str::upper(Str::random(6)),
        'status' => SalesQuote::STATUS_SENT,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(7)->toDateString(),
        'subtotal' => 500,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 500,
        'requires_approval' => true,
        'created_by' => $requester->id,
        'updated_by' => $requester->id,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'order_number' => 'PO-'.Str::upper(Str::random(6)),
        'status' => PurchaseOrder::STATUS_DRAFT,
        'order_date' => now()->toDateString(),
        'subtotal' => 900,
        'tax_total' => 0,
        'grand_total' => 900,
        'requires_approval' => true,
        'created_by' => $requester->id,
        'updated_by' => $requester->id,
    ]);

    actingAs($approver)
        ->get(route('company.modules.approvals'))
        ->assertOk();

    $quoteApprovalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('source_type', SalesQuote::class)
        ->where('source_id', $quote->id)
        ->first();

    $purchaseApprovalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('source_type', PurchaseOrder::class)
        ->where('source_id', $purchaseOrder->id)
        ->first();

    expect($quoteApprovalRequest)->not->toBeNull();
    expect($purchaseApprovalRequest)->not->toBeNull();
    expect($quoteApprovalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);
    expect($purchaseApprovalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($approver)
        ->post(route('company.approvals.approve', $quoteApprovalRequest))
        ->assertSessionHas('success');

    expect($quote->fresh()?->status)->toBe(SalesQuote::STATUS_APPROVED);
    expect($quote->fresh()?->approved_by)->toBe($approver->id);
    expect($quoteApprovalRequest?->fresh()?->status)->toBe(ApprovalRequest::STATUS_APPROVED);

    actingAs($approver)
        ->post(route('company.approvals.reject', $purchaseApprovalRequest), [
            'reason' => 'Needs updated vendor terms',
        ])
        ->assertSessionHas('success');

    expect($purchaseApprovalRequest?->fresh()?->status)->toBe(ApprovalRequest::STATUS_REJECTED);
    expect($purchaseApprovalRequest?->fresh()?->rejection_reason)->toBe('Needs updated vendor terms');

    actingAs($approver)
        ->get(route('company.modules.approvals'))
        ->assertOk();

    expect($purchaseApprovalRequest?->fresh()?->status)->toBe(ApprovalRequest::STATUS_REJECTED);
});
