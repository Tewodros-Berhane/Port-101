<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ModulesController extends Controller
{
    public function sales(): Response
    {
        $this->abortUnlessHasAnyPermission([
            'sales.leads.view',
            'sales.quotes.view',
            'sales.orders.view',
        ]);

        return $this->placeholder('Sales');
    }

    public function inventory(): Response
    {
        $this->abortUnlessHasAnyPermission([
            'inventory.stock.view',
            'inventory.moves.view',
        ]);

        return $this->placeholder('Inventory');
    }

    public function purchasing(): Response
    {
        $this->abortUnlessHasAnyPermission([
            'purchasing.rfq.view',
            'purchasing.po.view',
        ]);

        return $this->placeholder('Purchasing');
    }

    public function accounting(): Response
    {
        $this->abortUnlessHasAnyPermission([
            'accounting.invoices.view',
            'accounting.payments.view',
        ]);

        return $this->placeholder('Accounting');
    }

    public function reports(): Response
    {
        $this->abortUnlessHasAnyPermission([
            'reports.view',
        ]);

        return $this->placeholder('Reports');
    }

    public function approvals(): Response
    {
        $this->abortUnlessHasAnyPermission([
            'approvals.requests.view',
        ]);

        return $this->placeholder('Approvals');
    }

    private function placeholder(string $module): Response
    {
        return Inertia::render('company/module-placeholder', [
            'module' => $module,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function abortUnlessHasAnyPermission(array $permissions): void
    {
        $user = request()->user();

        foreach ($permissions as $permission) {
            if ($user?->hasPermission($permission)) {
                return;
            }
        }

        abort(403);
    }
}
