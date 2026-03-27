<?php

namespace App\Core\Attachments;

use App\Core\Attachments\Models\Attachment;
use App\Jobs\ScanAttachmentForMalware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AttachmentUploadService
{
    public function __construct(
        private readonly AttachmentSecurityService $securityService,
    ) {}

    public function store(
        UploadedFile $file,
        Model $attachable,
        string $companyId,
        string $context,
        ?string $actorId = null,
    ): Attachment {
        $this->securityService->validateUpload($file, $context);

        $disk = (string) config('core.attachments.disk', 'local');
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $generatedName = (string) Str::ulid().'.'.$extension;
        $path = $file->storeAs(
            'attachments/'.$companyId.'/'.strtolower(class_basename($attachable)),
            $generatedName,
            $disk
        );

        $attachment = Attachment::create([
            'company_id' => $companyId,
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->getKey(),
            'security_context' => $context,
            'disk' => $disk,
            'path' => $path,
            'file_name' => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'scan_status' => $this->securityService->initialScanStatus(),
            'uploaded_by' => $actorId,
        ]);

        if ((bool) config('core.attachments.scan.enabled', true)) {
            ScanAttachmentForMalware::dispatch((string) $attachment->id);
        }

        return $attachment->fresh() ?? $attachment;
    }
}
