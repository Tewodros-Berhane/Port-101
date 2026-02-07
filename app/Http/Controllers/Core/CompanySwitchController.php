<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
        ]);

        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $companyId = $data['company_id'];

        if (! $user->is_super_admin) {
            $company = $user->companies()
                ->where('companies.id', $companyId)
                ->first();

            if (! $company) {
                abort(403);
            }

            if (! $company->is_active) {
                return back()->with('error', 'Cannot switch to an inactive company.');
            }
        }

        $user->forceFill([
            'current_company_id' => $companyId,
        ])->save();

        return back(303);
    }
}
