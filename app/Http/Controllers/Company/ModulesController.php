<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ModulesController extends Controller
{
    public function sales(): Response
    {
        return $this->placeholder('Sales');
    }

    public function inventory(): Response
    {
        return $this->placeholder('Inventory');
    }

    public function purchasing(): Response
    {
        return $this->placeholder('Purchasing');
    }

    public function accounting(): Response
    {
        return $this->placeholder('Accounting');
    }

    public function reports(): Response
    {
        return $this->placeholder('Reports');
    }

    public function approvals(): Response
    {
        return $this->placeholder('Approvals');
    }

    private function placeholder(string $module): Response
    {
        return Inertia::render('company/module-placeholder', [
            'module' => $module,
        ]);
    }
}
