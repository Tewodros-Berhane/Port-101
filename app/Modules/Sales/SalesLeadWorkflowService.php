<?php

namespace App\Modules\Sales;

use App\Models\User;
use App\Modules\Sales\Models\SalesLead;

class SalesLeadWorkflowService
{
    public function create(array $attributes, User $actor): SalesLead
    {
        return SalesLead::create([
            ...$attributes,
            'company_id' => $actor->current_company_id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function update(SalesLead $lead, array $attributes, User $actor): SalesLead
    {
        $lead->update([
            ...$attributes,
            'updated_by' => $actor->id,
        ]);

        return $lead->fresh();
    }

    public function delete(SalesLead $lead): void
    {
        $lead->delete();
    }
}
