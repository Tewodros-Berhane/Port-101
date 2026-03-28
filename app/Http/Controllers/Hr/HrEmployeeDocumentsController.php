<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Modules\Hr\HrEmployeeDocumentService;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HrEmployeeDocumentsController extends Controller
{
    public function __construct(
        private readonly HrEmployeeDocumentService $documentService,
    ) {}

    public function store(HrEmployee $employee, Request $request): RedirectResponse
    {
        $this->authorize('update', $employee);
        abort_unless($request->user()?->hasPermission('hr.employees.private_manage'), 403);

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:64'],
            'document_name' => ['required', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date'],
            'is_private' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'max:'.(int) config('core.attachments.max_size_kb', 10240)],
        ]);

        $this->documentService->store(
            employee: $employee,
            file: $request->file('file'),
            attributes: $data,
            actorId: $request->user()?->id,
        );

        return back(303)->with('success', 'Employee document uploaded.');
    }

    public function download(HrEmployeeDocument $document)
    {
        $this->authorize('view', $document);

        return $this->documentService->download($document);
    }

    public function destroy(HrEmployeeDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->delete($document);

        return back(303)->with('success', 'Employee document removed.');
    }
}
