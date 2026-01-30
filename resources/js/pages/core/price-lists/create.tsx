import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type CurrencyOption = {
    id: string;
    code: string;
    name: string;
};

type Props = {
    currencies: CurrencyOption[];
};

export default function PriceListCreate({ currencies }: Props) {
    const form = useForm({
        name: '',
        currency_id: '',
        is_active: true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Price Lists', href: '/core/price-lists' },
                { title: 'Create', href: '/core/price-lists/create' },
            ]}
        >
            <Head title="New Price List" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New price list</h1>
                    <p className="text-sm text-muted-foreground">
                        Create a new pricing list.
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
                    form.post('/core/price-lists');
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

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Create price list
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
