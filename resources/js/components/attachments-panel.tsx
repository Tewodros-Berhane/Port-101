import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';

type Attachment = {
    id: string;
    original_name: string;
    mime_type?: string | null;
    size: number;
    created_at?: string | null;
    download_url: string;
};

type Props = {
    attachableType: string;
    attachableId: string;
    attachments: Attachment[];
    canView: boolean;
    canManage: boolean;
};

const formatBytes = (size: number) => {
    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function AttachmentsPanel({
    attachableType,
    attachableId,
    attachments,
    canView,
    canManage,
}: Props) {
    const uploadForm = useForm({
        attachable_type: attachableType,
        attachable_id: attachableId,
        file: null as File | null,
    });
    const deleteForm = useForm({});

    if (!canView && !canManage) {
        return null;
    }

    return (
        <div className="rounded-xl border p-4">
            <h2 className="text-sm font-semibold">Attachments</h2>
            <p className="mt-1 text-xs text-muted-foreground">
                Upload and manage files related to this record.
            </p>

            {canManage && (
                <form
                    className="mt-4 flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        uploadForm.post('/core/attachments', {
                            preserveScroll: true,
                            forceFormData: true,
                            onSuccess: () => {
                                uploadForm.reset('file');
                            },
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`attachment-${attachableId}`}>File</Label>
                        <input
                            id={`attachment-${attachableId}`}
                            type="file"
                            className="block rounded-md border border-input px-3 py-1 text-sm"
                            onChange={(event) =>
                                uploadForm.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={uploadForm.errors.file} />
                    </div>
                    <Button
                        type="submit"
                        disabled={uploadForm.processing || !uploadForm.data.file}
                    >
                        Upload
                    </Button>
                </form>
            )}

            <div className="mt-4 overflow-x-auto rounded-md border">
                <table className="w-full min-w-max text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-3 py-2 font-medium">File</th>
                            <th className="px-3 py-2 font-medium">Type</th>
                            <th className="px-3 py-2 font-medium">Size</th>
                            <th className="px-3 py-2 font-medium">Uploaded</th>
                            <th className="px-3 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {attachments.length === 0 && (
                            <tr>
                                <td
                                    className="px-3 py-6 text-center text-muted-foreground"
                                    colSpan={5}
                                >
                                    No attachments uploaded.
                                </td>
                            </tr>
                        )}
                        {attachments.map((attachment) => (
                            <tr key={attachment.id}>
                                <td className="px-3 py-2 font-medium">
                                    {attachment.original_name}
                                </td>
                                <td className="px-3 py-2 text-muted-foreground">
                                    {attachment.mime_type ?? '-'}
                                </td>
                                <td className="px-3 py-2 text-muted-foreground">
                                    {formatBytes(attachment.size)}
                                </td>
                                <td className="px-3 py-2 text-muted-foreground">
                                    {formatDate(attachment.created_at)}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    <div className="flex justify-end gap-2">
                                        <Button variant="outline" asChild>
                                            <a href={attachment.download_url}>
                                                Download
                                            </a>
                                        </Button>
                                        {canManage && (
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                onClick={() =>
                                                    deleteForm.delete(
                                                        `/core/attachments/${attachment.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                disabled={deleteForm.processing}
                                            >
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

