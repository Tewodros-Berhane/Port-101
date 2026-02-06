import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type CurrencyOption = {
    id: string;
    code: string;
    name: string;
};

type PriceList = {
    id: string;
    name: string;
    currency_id?: string | null;
    is_active: boolean;
};

type Props = {
    priceList: PriceList;
    currencies: CurrencyOption[];
};

export default function PriceListEdit({ priceList, currencies }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('core.price_lists.manage');
    const form = useForm({
        name: priceList.name ?? '',
        currency_id: priceList.currency_id ?? '',
        is_active: priceList.is_active ?? true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Price Lists', href: '/core/price-lists' },
                {
                    title: priceList.name,
                    href: `/core/price-lists/${priceList.id}/edit`,
                },
            ]}
        >
            <Head title={priceList.name} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Edit price list</h1>
                    <p className="text-sm text-muted-foreground">
                        Update pricing list details.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/core/price-lists">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/core/price-lists/${priceList.id}`);
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
                    <Label htmlFor="currency_id">Currency</Label>
                    <select
                        id="currency_id"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.currency_id}
                        onChange={(event) =>
                            form.setData('currency_id', event.target.value)
                        }
                    >
                        <option value="">Default</option>
                        {currencies.map((currency) => (
                            <option key={currency.id} value={currency.id}>
                                {currency.code} — {currency.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.currency_id} />
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
                                form.delete(`/core/price-lists/${priceList.id}`)
                            }
                            disabled={form.processing}
                        >
                            Delete
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
