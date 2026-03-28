<?php

namespace App\Http\Controllers\Hr;

use App\Core\Attachments\AttachmentSecurityService;
use App\Core\Attachments\Models\Attachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrReimbursementReceiptStoreRequest;
use App\Modules\Hr\HrReimbursementService;
use App\Modules\Hr\Models\HrReimbursementClaimLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class HrReimbursementReceiptsController extends Controller
{
    public function __construct(
        private readonly HrReimbursementService $reimbursementService,
        private readonly AttachmentSecurityService $attachmentSecurityService,
    ) {}

    public function store(HrReimbursementReceiptStoreRequest $request, HrReimbursementClaimLine $line): RedirectResponse
    {
        $claim = $line->claim()->firstOrFail();
        $this->authorize('uploadReceipt', $claim);

        try {
            $this->reimbursementService->uploadReceipt($line, $request->file('file'), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back(303)->with('success', 'Receipt uploaded.');
    }

    public function download(HrReimbursementClaimLine $line)
    {
        $claim = $line->claim()->with('employee')->firstOrFail();
        $this->authorize('view', $claim);

        $attachment = $line->receiptAttachment;
        abort_unless($attachment instanceof Attachment, 404);

        $this->attachmentSecurityService->assertDownloadAllowed($attachment);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Attachment file not found.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    public function destroy(HrReimbursementClaimLine $line): RedirectResponse
    {
        $claim = $line->claim()->firstOrFail();
        $this->authorize('removeReceipt', $claim);

        try {
            $this->reimbursementService->removeReceipt($line, request()->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back(303)->with('success', 'Receipt removed.');
    }
}
