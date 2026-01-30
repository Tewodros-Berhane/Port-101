import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Tax = {
    id: string;
    name: string;
    type: string;
    rate: string;
    is_active: boolean;
};

type Props = {
    tax: Tax;
};

export default function TaxEdit({ tax }: Props) {
    const form = useForm({
        name: tax.name ?? '',
        type: tax.type ?? 'percent',
        rate: tax.rate ?? '0',
        is_active: tax.is_active ?? true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Taxes', href: '/core/taxes' },
                { title: tax.name, href: `/core/taxes/${tax.id}/edit` },
            ]}
        >
            <Head title={tax.name} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Edit tax</h1>
                    <p className="text-sm text-muted-foreground">
                        Update tax rules for pricing.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/core/taxes">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/core/taxes/${tax.id}`);
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
                    <Label htmlFor="type">Type</Label>
                    <select
                        id="type"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.type}
                        onChange={(event) =>
                            form.setData('type', event.target.value)
                        }
                    >
                        <option value="percent">Percent</option>
                        <option value="fixed">Fixed</option>
                    </select>
                    <InputError message={form.errors.type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="rate">Rate</Label>
                    <Input
                        id="rate"
                        type="number"
                        step="0.0001"
                        min="0"
                        value={form.data.rate}
                        onChange={(event) =>
                            form.setData('rate', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.rate} />
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

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Save changes
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() => form.delete(`/core/taxes/${tax.id}`)}
                        disabled={form.processing}
                    >
                        Delete
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
