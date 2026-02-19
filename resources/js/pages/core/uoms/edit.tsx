import AttachmentsPanel from '@/components/attachments-panel';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Uom = {
    id: string;
    name: string;
    symbol?: string | null;
    is_active: boolean;
};

type Attachment = {
    id: string;
    original_name: string;
    mime_type?: string | null;
    size: number;
    created_at?: string | null;
    download_url: string;
};

type Props = {
    uom: Uom;
    attachments: Attachment[];
};

export default function UomEdit({ uom, attachments }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('core.uoms.manage');
    const canViewAttachments = hasPermission('core.attachments.view');
    const canManageAttachments = hasPermission('core.attachments.manage');
    const form = useForm({
        name: uom.name ?? '',
        symbol: uom.symbol ?? '',
        is_active: uom.is_active ?? true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Units', href: '/core/uoms' },
                { title: uom.name, href: `/core/uoms/${uom.id}/edit` },
            ]}
        >
            <Head title={uom.name} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Edit unit</h1>
                    <p className="text-sm text-muted-foreground">
                        Update unit of measure details.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/core/uoms">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/core/uoms/${uom.id}`);
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="symbol">Symbol</Label>
                    <Input
                        id="symbol"
                        value={form.data.symbol}
                        onChange={(event) =>
                            form.setData('symbol', event.target.value)
                        }
                    />
                    <InputError message={form.errors.symbol} />
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="is_active"
                        checked={form.data.is_active}
                        onCheckedChange={(value) =>
                            form.setData('is_active', Boolean(value))
                        }
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>

                {canManage && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => form.delete(`/core/uoms/${uom.id}`)}
                            disabled={form.processing}
                        >
                            Delete
                        </Button>
                    </div>
                )}
            </form>

            <div className="mt-6">
                <AttachmentsPanel
                    attachableType="uom"
                    attachableId={uom.id}
                    attachments={attachments}
                    canView={canViewAttachments}
                    canManage={canManageAttachments}
                />
            </div>
        </AppLayout>
    );
}
