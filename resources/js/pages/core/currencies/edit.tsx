import AttachmentsPanel from '@/components/attachments-panel';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Currency = {
    id: string;
    code: string;
    name: string;
    symbol?: string | null;
    decimal_places: number;
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
    currency: Currency;
    attachments: Attachment[];
};

export default function CurrencyEdit({ currency, attachments }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('core.currencies.manage');
    const canViewAttachments = hasPermission('core.attachments.view');
    const canManageAttachments = hasPermission('core.attachments.manage');
    const form = useForm({
        code: currency.code ?? '',
        name: currency.name ?? '',
        symbol: currency.symbol ?? '',
        decimal_places: currency.decimal_places ?? 2,
        is_active: currency.is_active ?? true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Currencies', href: '/core/currencies' },
                {
                    title: currency.code,
                    href: `/core/currencies/${currency.id}/edit`,
                },
            ]}
        >
            <Head title={currency.code} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Edit currency</h1>
                    <p className="text-sm text-muted-foreground">
                        Update currency formatting details.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/core/currencies">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/core/currencies/${currency.id}`);
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="code">Code</Label>
                    <Input
                        id="code"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData(
                                'code',
                                event.target.value.toUpperCase(),
                            )
                        }
                        maxLength={3}
                        required
                    />
                    <InputError message={form.errors.code} />
                </div>

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

                <div className="grid gap-2">
                    <Label htmlFor="decimal_places">Decimal places</Label>
                    <Input
                        id="decimal_places"
                        type="number"
                        min={0}
                        max={6}
                        value={form.data.decimal_places}
                        onChange={(event) =>
                            form.setData(
                                'decimal_places',
                                Number(event.target.value),
                            )
                        }
                        required
                    />
                    <InputError message={form.errors.decimal_places} />
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
                            onClick={() =>
                                form.delete(`/core/currencies/${currency.id}`)
                            }
                            disabled={form.processing}
                        >
                            Delete
                        </Button>
                    </div>
                )}
            </form>

            <div className="mt-6">
                <AttachmentsPanel
                    attachableType="currency"
                    attachableId={currency.id}
                    attachments={attachments}
                    canView={canViewAttachments}
                    canManage={canManageAttachments}
                />
            </div>
        </AppLayout>
    );
}
