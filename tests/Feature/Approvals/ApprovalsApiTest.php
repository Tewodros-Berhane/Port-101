<?php

use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function assignApprovalsApiRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Approvals API Role '.Str::upper(Str::random(4)),
        'slug' => 'approvals-api-role-'.Str::lower(Str::random(8)),
        'description' => 'Approvals API test role',
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

function createApprovalsApiPartner(string $companyId, string $userId, string $name): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'name' => $name,
        'type' => 'both',
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('api v1 approvals endpoints are company scoped and support approve and reject actions', function () {
    [$requester, $company] = makeActiveCompanyMember();
    $approver = User::factory()->create();
    [$otherRequester, $otherCompany] = makeActiveCompanyMember();
    $otherApprover = User::factory()->create();

    assignApprovalsApiRole($approver, $company->id, [
        'approvals.requests.view',
        'approvals.requests.manage',
    ]);
    assignApprovalsApiRole($otherApprover, $otherCompany->id, [
        'approvals.requests.view',
        'approvals.requests.manage',
    ]);

    $partner = createApprovalsApiPartner($company->id, $requester->id, 'Approvals API Partner');
    $otherPartner = createApprovalsApiPartner($otherCompany->id, $otherRequester->id, 'Other Approvals API Partner');

    $quote = SalesQuote::create([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'quote_number' => 'QT-API-'.Str::upper(Str::random(4)),
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
        'order_number' => 'PO-API-'.Str::upper(Str::random(4)),
        'status' => PurchaseOrder::STATUS_DRAFT,
        'order_date' => now()->toDateString(),
        'subtotal' => 900,
        'tax_total' => 0,
        'grand_total' => 900,
        'requires_approval' => true,
        'created_by' => $requester->id,
        'updated_by' => $requester->id,
    ]);

    $otherQuote = SalesQuote::create([
        'company_id' => $otherCompany->id,
        'partner_id' => $otherPartner->id,
        'quote_number' => 'QT-OTH-'.Str::upper(Str::random(4)),
        'status' => SalesQuote::STATUS_SENT,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(7)->toDateString(),
        'subtotal' => 400,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 400,
        'requires_approval' => true,
        'created_by' => $otherRequester->id,
        'updated_by' => $otherRequester->id,
    ]);

    app(ApprovalQueueService::class)->syncPendingRequests($otherCompany, $otherApprover->id);

    Sanctum::actingAs($approver);

    getJson('/api/v1/approvals/requests?status=pending&sort=requested_at&direction=desc&per_page=500')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonPath('meta.sort', 'requested_at')
        ->assertJsonPath('meta.direction', 'desc')
        ->assertJsonPath('meta.filters.status', ApprovalRequest::STATUS_PENDING);

    $quoteApprovalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('source_type', SalesQuote::class)
        ->where('source_id', $quote->id)
        ->firstOrFail();

    $purchaseApprovalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('source_type', PurchaseOrder::class)
        ->where('source_id', $purchaseOrder->id)
        ->firstOrFail();

    $otherApprovalRequest = ApprovalRequest::query()
        ->withoutGlobalScopes()
        ->where('company_id', $otherCompany->id)
        ->where('source_type', SalesQuote::class)
        ->where('source_id', $otherQuote->id)
        ->firstOrFail();

    getJson('/api/v1/approvals/requests?module=sales&search='.urlencode((string) $quote->quote_number))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $quoteApprovalRequest->id)
        ->assertJsonPath('meta.filters.module', ApprovalRequest::MODULE_SALES)
        ->assertJsonPath('meta.filters.search', $quote->quote_number);

    getJson('/api/v1/approvals/requests/'.$quoteApprovalRequest->id)
        ->assertOk()
        ->assertJsonPath('data.id', $quoteApprovalRequest->id)
        ->assertJsonPath('data.steps_count', 1)
        ->assertJsonPath('data.steps.0.status', 'pending')
        ->assertJsonPath('data.can_approve', true)
        ->assertJsonPath('data.can_reject', true);

    getJson('/api/v1/approvals/requests/'.$otherApprovalRequest->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    postJson('/api/v1/approvals/requests/'.$quoteApprovalRequest->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', ApprovalRequest::STATUS_APPROVED)
        ->assertJsonPath('data.approved_by_user_id', $approver->id)
        ->assertJsonPath('data.steps.0.status', 'approved');

    expect($quote->fresh()?->status)->toBe(SalesQuote::STATUS_APPROVED);
    expect($quote->fresh()?->approved_by)->toBe($approver->id);

    postJson('/api/v1/approvals/requests/'.$purchaseApprovalRequest->id.'/reject', [
        'reason' => 'Needs updated vendor terms',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', ApprovalRequest::STATUS_REJECTED)
        ->assertJsonPath('data.rejection_reason', 'Needs updated vendor terms')
        ->assertJsonPath('data.rejected_by_user_id', $approver->id)
        ->assertJsonPath('data.steps.0.status', 'rejected');

    expect($purchaseApprovalRequest->fresh()?->status)->toBe(ApprovalRequest::STATUS_REJECTED);
    expect($purchaseApprovalRequest->fresh()?->rejection_reason)->toBe('Needs updated vendor terms');
});

test('api v1 approvals permissions and workflow errors use the shared contract', function () {
    [$requester, $company] = makeActiveCompanyMember();
    $viewer = User::factory()->create();

    assignApprovalsApiRole($viewer, $company->id, [
        'approvals.requests.view',
    ]);

    $partner = createApprovalsApiPartner($company->id, $requester->id, 'Approvals Viewer Partner');

    $quote = SalesQuote::create([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'quote_number' => 'QT-VIEW-'.Str::upper(Str::random(4)),
        'status' => SalesQuote::STATUS_SENT,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(5)->toDateString(),
        'subtotal' => 250,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 250,
        'requires_approval' => true,
        'created_by' => $requester->id,
        'updated_by' => $requester->id,
    ]);

    app(ApprovalQueueService::class)->syncPendingRequests($company, $requester->id);

    $approvalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('source_type', SalesQuote::class)
        ->where('source_id', $quote->id)
        ->firstOrFail();

    Sanctum::actingAs($viewer);

    getJson('/api/v1/approvals/requests')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    getJson('/api/v1/approvals/requests/'.$approvalRequest->id)
        ->assertOk()
        ->assertJsonPath('data.id', $approvalRequest->id)
        ->assertJsonPath('data.can_approve', false);

    postJson('/api/v1/approvals/requests/'.$approvalRequest->id.'/approve')
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    assignApprovalsApiRole($viewer, $company->id, [
        'approvals.requests.view',
        'approvals.requests.manage',
    ]);

    Sanctum::actingAs($viewer);

    postJson('/api/v1/approvals/requests/'.$approvalRequest->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', ApprovalRequest::STATUS_APPROVED);

    postJson('/api/v1/approvals/requests/'.$approvalRequest->id.'/approve')
        ->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => ['approval'],
        ]);
});
