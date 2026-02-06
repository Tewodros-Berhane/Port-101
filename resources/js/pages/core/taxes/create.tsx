import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function TaxCreate() {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('core.taxes.manage');
    const form = useForm({
        name: '',
        type: 'percent',
        rate: '0',
        is_active: true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Taxes', href: '/core/taxes' },
                { title: 'Create', href: '/core/taxes/create' },
            ]}
        >
            <Head title="New Tax" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New tax</h1>
                    <p className="text-sm text-muted-foreground">
                        Add a tax rule for pricing.
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
                    form.post('/core/taxes');
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

                {canManage && (
                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Create tax
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
