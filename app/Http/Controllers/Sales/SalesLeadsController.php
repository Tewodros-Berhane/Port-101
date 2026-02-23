<?php

namespace App\Http\Controllers\Sales;

use App\Core\MasterData\Models\Partner;
use App\Modules\Sales\Models\SalesLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesLeadStoreRequest;
use App\Http\Requests\Sales\SalesLeadUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesLeadsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalesLead::class);

        $user = $request->user();

        $leadsQuery = SalesLead::query()
            ->with('partner:id,name')
            ->orderByDesc('created_at')
            ->when($user, fn ($query) => $user->applyDataScopeToQuery($query));

        $leads = $leadsQuery->paginate(20)->withQueryString();

        return Inertia::render('sales/leads/index', [
            'leads' => $leads->through(function (SalesLead $lead) {
                return [
                    'id' => $lead->id,
                    'title' => $lead->title,
                    'stage' => $lead->stage,
                    'partner_name' => $lead->partner?->name,
                    'estimated_value' => (float) $lead->estimated_value,
                    'expected_close_date' => $lead->expected_close_date?->toDateString(),
                    'converted_at' => $lead->converted_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SalesLead::class);

        return Inertia::render('sales/leads/create', [
            'partners' => $this->partnerOptions(),
            'stages' => ['new', 'qualified', 'quoted', 'won', 'lost'],
        ]);
    }

    public function store(SalesLeadStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', SalesLead::class);

        $user = $request->user();

        $lead = SalesLead::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('company.sales.leads.edit', $lead)
            ->with('success', 'Lead created.');
    }

    public function edit(SalesLead $lead): Response
    {
        $this->authorize('update', $lead);

        return Inertia::render('sales/leads/edit', [
            'lead' => [
                'id' => $lead->id,
                'partner_id' => $lead->partner_id,
                'title' => $lead->title,
                'stage' => $lead->stage,
                'estimated_value' => (float) $lead->estimated_value,
                'expected_close_date' => $lead->expected_close_date?->toDateString(),
                'notes' => $lead->notes,
                'converted_at' => $lead->converted_at?->toIso8601String(),
            ],
            'partners' => $this->partnerOptions(),
            'stages' => ['new', 'qualified', 'quoted', 'won', 'lost'],
        ]);
    }

    public function update(
        SalesLeadUpdateRequest $request,
        SalesLead $lead
    ): RedirectResponse {
        $this->authorize('update', $lead);

        $lead->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.sales.leads.edit', $lead)
            ->with('success', 'Lead updated.');
    }

    public function destroy(SalesLead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return redirect()
            ->route('company.sales.leads.index')
            ->with('success', 'Lead removed.');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function partnerOptions(): array
    {
        return Partner::query()
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
            ])
            ->values()
            ->all();
    }
}


