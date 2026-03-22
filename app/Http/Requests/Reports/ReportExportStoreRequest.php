<?php

namespace App\Http\Requests\Reports;

use App\Modules\Reports\CompanyReportsService;
use App\Modules\Reports\Models\ReportExport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_key' => ['required', 'string', Rule::in(CompanyReportsService::REPORT_KEYS)],
            'format' => ['required', 'string', Rule::in(ReportExport::FORMATS)],
            'preset_id' => ['nullable', 'string', 'max:80'],
            'trend_window' => ['nullable', 'integer', 'in:7,30,90'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'approval_status' => ['nullable', 'string', 'in:pending,approved,rejected,cancelled'],
        ];
    }
}
