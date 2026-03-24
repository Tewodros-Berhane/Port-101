<?php

namespace App\Http\Controllers\Inventory;

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryReorderRuleStoreRequest;
use App\Http\Requests\Inventory\InventoryReorderRuleUpdateRequest;
use App\Http\Requests\Inventory\InventoryReplenishmentConvertRequest;
use App\Modules\Inventory\InventoryReorderService;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryReorderRule;
use App\Modules\Inventory\Models\InventoryReplenishmentSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryReorderingController extends Controller
{
    public function index(Request $request, InventoryReorderService $reorderService): Response
    {
        $this->authorize('viewAny', InventoryReorderRule::class);

        $user = $request->user();
        $status = (string) $request->string('status', InventoryReplenishmentSuggestion::STATUS_OPEN);

        if (! in_array($status, InventoryReplenishmentSuggestion::STATUSES, true)) {
            $status = InventoryReplenishmentSuggestion::STATUS_OPEN;
        }

        $rulesQuery = InventoryReorderRule::query()
            ->with([
                'product:id,name,sku',
                'location:id,name,code',
                'preferredVendor:id,name',
            ])
            ->orderBy('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $rules = $rulesQuery->get()
            ->map(function (InventoryReorderRule $rule) use ($reorderService) {
                $metrics = $reorderService->evaluateRule($rule);

                return [
                    'id' => $rule->id,
                    'product_name' => $rule->product?->name,
                    'product_sku' => $rule->product?->sku,
                    'location_name' => $rule->location?->name,
                    'location_code' => $rule->location?->code,
                    'preferred_vendor_name' => $rule->preferredVendor?->name,
                    'min_quantity' => (float) $rule->min_quantity,
                    'max_quantity' => (float) $rule->max_quantity,
                    'reorder_quantity' => $rule->reorder_quantity !== null ? (float) $rule->reorder_quantity : null,
                    'lead_time_days' => $rule->lead_time_days,
                    'is_active' => (bool) $rule->is_active,
                    'last_evaluated_at' => $rule->last_evaluated_at?->toIso8601String(),
                    'notes' => $rule->notes,
                    'metrics' => [
                        'available_quantity' => $metrics['available_quantity'],
                        'inbound_quantity' => $metrics['inbound_quantity'],
                        'projected_quantity' => $metrics['projected_quantity'],
                        'suggested_quantity' => $metrics['suggested_quantity'],
                        'requires_replenishment' => $metrics['requires_replenishment'],
                    ],
                ];
            })
            ->values()
            ->all();

        $suggestionsQuery = InventoryReplenishmentSuggestion::query()
            ->with([
                'product:id,name,sku',
                'location:id,name,code',
                'preferredVendor:id,name',
                'rfq:id,rfq_number,status',
            ])
            ->where('status', $status)
            ->latest('triggered_at')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $suggestions = $suggestionsQuery->paginate(20)->withQueryString();

        $baseSuggestionQuery = InventoryReplenishmentSuggestion::query()
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        return Inertia::render('inventory/reordering/index', [
            'filters' => [
                'status' => $status,
            ],
            'metrics' => [
                'active_rules' => (clone $rulesQuery)->where('is_active', true)->count(),
                'open_suggestions' => (clone $baseSuggestionQuery)
                    ->where('status', InventoryReplenishmentSuggestion::STATUS_OPEN)
                    ->count(),
                'converted_30d' => (clone $baseSuggestionQuery)
                    ->where('status', InventoryReplenishmentSuggestion::STATUS_CONVERTED)
                    ->where('converted_at', '>=', now()->subDays(30))
                    ->count(),
                'projected_shortages' => collect($rules)
                    ->filter(fn (array $rule) => (bool) $rule['metrics']['requires_replenishment'])
                    ->count(),
            ],
            'rules' => $rules,
            'suggestions' => $suggestions->through(fn (InventoryReplenishmentSuggestion $suggestion) => [
                'id' => $suggestion->id,
                'status' => $suggestion->status,
                'product_name' => $suggestion->product?->name,
                'product_sku' => $suggestion->product?->sku,
                'location_name' => $suggestion->location?->name,
                'location_code' => $suggestion->location?->code,
                'preferred_vendor_id' => $suggestion->preferred_vendor_id,
                'preferred_vendor_name' => $suggestion->preferredVendor?->name,
                'rfq_id' => $suggestion->rfq_id,
                'rfq_number' => $suggestion->rfq?->rfq_number,
                'rfq_status' => $suggestion->rfq?->status,
                'available_quantity' => (float) $suggestion->available_quantity,
                'inbound_quantity' => (float) $suggestion->inbound_quantity,
                'projected_quantity' => (float) $suggestion->projected_quantity,
                'min_quantity' => (float) $suggestion->min_quantity,
                'max_quantity' => (float) $suggestion->max_quantity,
                'suggested_quantity' => (float) $suggestion->suggested_quantity,
                'triggered_at' => $suggestion->triggered_at?->toIso8601String(),
                'converted_at' => $suggestion->converted_at?->toIso8601String(),
                'dismissed_at' => $suggestion->dismissed_at?->toIso8601String(),
                'resolved_at' => $suggestion->resolved_at?->toIso8601String(),
                'notes' => $suggestion->notes,
                'can_dismiss' => $user?->can('dismiss', $suggestion) ?? false,
                'can_convert' => $user?->can('convertToRfq', $suggestion) ?? false,
            ]),
            'products' => $this->productOptions((string) $user?->current_company_id),
            'locations' => $this->locationOptions((string) $user?->current_company_id),
            'vendors' => $this->vendorOptions((string) $user?->current_company_id),
            'permissions' => [
                'can_manage' => $user?->can('create', InventoryReorderRule::class) ?? false,
                'can_scan' => $user?->can('scan', InventoryReorderRule::class) ?? false,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', InventoryReorderRule::class);

        $companyId = (string) $request->user()?->current_company_id;

        return Inertia::render('inventory/reordering/create', [
            'rule' => [
                'product_id' => '',
                'location_id' => '',
                'preferred_vendor_id' => '',
                'min_quantity' => 0,
                'max_quantity' => 0,
                'reorder_quantity' => '',
                'lead_time_days' => '',
                'is_active' => true,
                'notes' => '',
            ],
            'products' => $this->productOptions($companyId),
            'locations' => $this->locationOptions($companyId),
            'vendors' => $this->vendorOptions($companyId),
        ]);
    }

    public function store(InventoryReorderRuleStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryReorderRule::class);

        $companyId = (string) $request->user()?->current_company_id;

        InventoryReorderRule::create([
            'company_id' => $companyId,
            ...$request->safe()->only([
                'product_id',
                'location_id',
                'preferred_vendor_id',
                'min_quantity',
                'max_quantity',
                'reorder_quantity',
                'lead_time_days',
                'notes',
            ]),
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.inventory.reordering.index')
            ->with('success', 'Reordering rule created.');
    }

    public function edit(Request $request, InventoryReorderRule $rule): Response
    {
        $this->authorize('update', $rule);

        $companyId = (string) $request->user()?->current_company_id;

        return Inertia::render('inventory/reordering/edit', [
            'rule' => [
                'id' => $rule->id,
                'product_id' => $rule->product_id,
                'location_id' => $rule->location_id,
                'preferred_vendor_id' => $rule->preferred_vendor_id,
                'min_quantity' => (float) $rule->min_quantity,
                'max_quantity' => (float) $rule->max_quantity,
                'reorder_quantity' => $rule->reorder_quantity !== null ? (float) $rule->reorder_quantity : '',
                'lead_time_days' => $rule->lead_time_days ?? '',
                'is_active' => (bool) $rule->is_active,
                'notes' => $rule->notes ?? '',
            ],
            'products' => $this->productOptions($companyId),
            'locations' => $this->locationOptions($companyId),
            'vendors' => $this->vendorOptions($companyId),
        ]);
    }

    public function update(
        InventoryReorderRuleUpdateRequest $request,
        InventoryReorderRule $rule,
    ): RedirectResponse {
        $this->authorize('update', $rule);

        $rule->update([
            ...$request->safe()->only([
                'product_id',
                'location_id',
                'preferred_vendor_id',
                'min_quantity',
                'max_quantity',
                'reorder_quantity',
                'lead_time_days',
                'notes',
            ]),
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.inventory.reordering.index')
            ->with('success', 'Reordering rule updated.');
    }

    public function destroy(InventoryReorderRule $rule): RedirectResponse
    {
        $this->authorize('delete', $rule);

        $rule->suggestions()
            ->where('status', InventoryReplenishmentSuggestion::STATUS_OPEN)
            ->update([
                'status' => InventoryReplenishmentSuggestion::STATUS_DISMISSED,
                'dismissed_at' => now(),
                'updated_by' => request()->user()?->id,
            ]);

        $rule->delete();

        return redirect()
            ->route('company.inventory.reordering.index')
            ->with('success', 'Reordering rule deleted.');
    }

    public function scan(
        Request $request,
        InventoryReorderService $reorderService,
    ): RedirectResponse {
        $this->authorize('scan', InventoryReorderRule::class);

        $companyId = (string) $request->user()?->current_company_id;
        $processed = $reorderService->scanCompany($companyId, $request->user()?->id);

        return back()->with(
            'success',
            'Reordering scan completed. '.count($processed).' suggestion(s) refreshed or created.',
        );
    }

    public function dismiss(
        Request $request,
        InventoryReplenishmentSuggestion $suggestion,
        InventoryReorderService $reorderService,
    ): RedirectResponse {
        $this->authorize('dismiss', $suggestion);

        $reorderService->dismiss($suggestion, $request->user()?->id);

        return back()->with('success', 'Replenishment suggestion dismissed.');
    }

    public function convert(
        InventoryReplenishmentConvertRequest $request,
        InventoryReplenishmentSuggestion $suggestion,
        InventoryReorderService $reorderService,
    ): RedirectResponse {
        $this->authorize('convertToRfq', $suggestion);

        $rfq = $reorderService->convertToDraftRfq(
            suggestion: $suggestion,
            vendorId: $request->validated('partner_id'),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.purchasing.rfqs.edit', $rfq)
            ->with('success', 'Replenishment RFQ created.');
    }

    /**
     * @return array<int, array{id: string, name: string, sku: string|null}>
     */
    private function productOptions(string $companyId): array
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', Product::TYPE_STOCK)
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, code: string}>
     */
    private function locationOptions(string $companyId): array
    {
        return InventoryLocation::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', InventoryLocation::TYPE_INTERNAL)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (InventoryLocation $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, type: string}>
     */
    private function vendorOptions(string $companyId): array
    {
        return Partner::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['vendor', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'type' => $partner->type,
            ])
            ->values()
            ->all();
    }
}
