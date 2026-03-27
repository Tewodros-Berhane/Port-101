<?php

namespace App\Core\Attachments;

use App\Core\Attachments\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AttachmentSecurityService
{
    /**
     * @return array<string, array<int, string>>
     */
    public function allowlist(string $context): array
    {
        $allowlist = config('core.attachments.allowlists.'.$context)
            ?: config('core.attachments.allowlists.default', []);

        return [
            'mime_types' => array_map('strtolower', (array) ($allowlist['mime_types'] ?? [])),
            'extensions' => array_map('strtolower', (array) ($allowlist['extensions'] ?? [])),
        ];
    }

    public function validateUpload(UploadedFile $file, string $context): void
    {
        $allowlist = $this->allowlist($context);
        $mimeType = strtolower((string) ($file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream'));
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: ''));

        if (
            $allowlist['mime_types'] !== []
            && ! in_array($mimeType, $allowlist['mime_types'], true)
        ) {
            throw ValidationException::withMessages([
                'file' => 'This file type is not allowed for the selected attachment context.',
            ]);
        }

        if (
            $allowlist['extensions'] !== []
            && ! in_array($extension, $allowlist['extensions'], true)
        ) {
            throw ValidationException::withMessages([
                'file' => 'This file extension is not allowed for the selected attachment context.',
            ]);
        }
    }

    public function initialScanStatus(): string
    {
        return (bool) config('core.attachments.scan.enabled', true)
            ? Attachment::SCAN_PENDING
            : Attachment::SCAN_CLEAN;
    }

    public function assertDownloadAllowed(Attachment $attachment): void
    {
        if (! (bool) config('core.attachments.download_requires_clean_scan', true)) {
            return;
        }

        abort_if(
            $attachment->scan_status === Attachment::SCAN_PENDING,
            423,
            'Attachment is still pending a security scan.',
        );

        abort_if(
            $attachment->scan_status === Attachment::SCAN_FAILED,
            423,
            'Attachment security scan failed. Re-upload or rescan before downloading.',
        );

        abort_if(
            $attachment->scan_status === Attachment::SCAN_INFECTED,
            423,
            'Attachment is quarantined because it failed the malware scan.',
        );
    }
}
