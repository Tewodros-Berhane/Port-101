<?php

namespace App\Http\Controllers\Projects;

use App\Core\Attachments\Models\Attachment;
use App\Core\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Modules\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectFilesController extends Controller
{
    public function store(Project $project, Request $request): RedirectResponse
    {
        $this->authorize('view', $project);
        abort_unless($request->user()?->can('update', $project), 403);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.(int) config('core.attachments.max_size_kb', 10240),
            ],
        ]);

        $file = $data['file'];
        $disk = (string) config('core.attachments.disk', 'local');
        $path = $file->store(
            'attachments/'.$project->company_id.'/projects',
            $disk
        );

        $attachment = Attachment::create([
            'company_id' => $project->company_id,
            'attachable_type' => Project::class,
            'attachable_id' => $project->id,
            'disk' => $disk,
            'path' => $path,
            'file_name' => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $file->extension(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()?->id,
        ]);

        $this->recordFileActivity(
            project: $project,
            attachment: $attachment,
            action: 'file_uploaded',
            userId: $request->user()?->id,
        );

        return back(303)->with('success', 'Project file uploaded.');
    }

    public function download(Attachment $attachment)
    {
        $project = $this->projectForAttachment($attachment);

        $this->authorize('view', $project);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Attachment file not found.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
    }

    public function destroy(Attachment $attachment, Request $request): RedirectResponse
    {
        $project = $this->projectForAttachment($attachment);

        abort_unless($request->user()?->can('update', $project), 403);

        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $this->recordFileActivity(
            project: $project,
            attachment: $attachment,
            action: 'file_deleted',
            userId: $request->user()?->id,
        );

        $attachment->delete();

        return back(303)->with('success', 'Project file removed.');
    }

    private function projectForAttachment(Attachment $attachment): Project
    {
        abort_unless((string) $attachment->attachable_type === Project::class, 404);

        return Project::query()->findOrFail($attachment->attachable_id);
    }

    private function recordFileActivity(
        Project $project,
        Attachment $attachment,
        string $action,
        ?string $userId = null
    ): void {
        AuditLog::create([
            'company_id' => $project->company_id,
            'user_id' => $userId,
            'auditable_type' => Project::class,
            'auditable_id' => $project->id,
            'action' => $action,
            'changes' => $action === 'file_deleted'
                ? [
                    'before' => [
                        'file_name' => $attachment->original_name,
                    ],
                ]
                : [
                    'after' => [
                        'file_name' => $attachment->original_name,
                    ],
                ],
            'metadata' => [
                'source' => 'projects.files',
            ],
        ]);
    }
}
