<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\AccountingStatementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingStatementsController extends Controller
{
    public function index(Request $request, AccountingStatementService $statementService): Response
    {
        abort_unless($request->user()?->hasPermission('accounting.statements.view'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $filters = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $startDate = (string) ($filters['start_date'] ?? now()->startOfMonth()->toDateString());
        $endDate = (string) ($filters['end_date'] ?? now()->toDateString());

        $statements = $statementService->financialStatements(
            company: $company,
            startDate: $startDate,
            endDate: $endDate,
        );

        return Inertia::render('accounting/statements/index', [
            'filters' => [
                'start_date' => $statements['start_date'],
                'end_date' => $statements['end_date'],
            ],
            'currencyCode' => $company->currency_code,
            'canExport' => $request->user()?->hasPermission('reports.export') ?? false,
            'statements' => $statements,
        ]);
    }
}
