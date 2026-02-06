import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CurrencyCreate() {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('core.currencies.manage');
    const form = useForm({
        code: '',
        name: '',
        symbol: '',
        decimal_places: 2,
        is_active: true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Currencies', href: '/core/currencies' },
                { title: 'Create', href: '/core/currencies/create' },
            ]}
        >
            <Head title="New Currency" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New currency</h1>
                    <p className="text-sm text-muted-foreground">
                        Add currency and formatting details.
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
                    form.post('/core/currencies');
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
                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Create currency
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
