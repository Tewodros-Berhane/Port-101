<?php

namespace App\Modules\Hr;

use App\Core\Attachments\AttachmentSecurityService;
use App\Core\Attachments\AttachmentUploadService;
use App\Core\Attachments\Models\Attachment;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HrEmployeeDocumentService
{
    public function __construct(
        private readonly AttachmentUploadService $attachmentUploadService,
        private readonly AttachmentSecurityService $attachmentSecurityService,
    ) {}

    public function store(HrEmployee $employee, UploadedFile $file, array $attributes, ?string $actorId): HrEmployeeDocument
    {
        return DB::transaction(function () use ($employee, $file, $attributes, $actorId): HrEmployeeDocument {
            $attachment = $this->attachmentUploadService->store(
                file: $file,
                attachable: $employee,
                companyId: (string) $employee->company_id,
                context: 'hr_employee_document',
                actorId: $actorId,
            );

            return HrEmployeeDocument::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attachment_id' => $attachment->id,
                'document_type' => (string) $attributes['document_type'],
                'document_name' => (string) $attributes['document_name'],
                'is_private' => (bool) ($attributes['is_private'] ?? true),
                'valid_until' => $attributes['valid_until'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        });
    }

    public function delete(HrEmployeeDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            $attachment = $document->attachment;

            $document->delete();

            if ($attachment instanceof Attachment) {
                if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                }

                $attachment->delete();
            }
        });
    }

    public function download(HrEmployeeDocument $document)
    {
        $attachment = $document->attachment;

        abort_unless($attachment instanceof Attachment, 404);

        $this->attachmentSecurityService->assertDownloadAllowed($attachment);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Employee document file not found.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }
}
