import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function InventoryWarehouseCreate() {
    const form = useForm({
        code: '',
        name: '',
        is_active: true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Warehouses', href: '/company/inventory/warehouses' },
                {
                    title: 'Create',
                    href: '/company/inventory/warehouses/create',
                },
            ]}
        >
            <Head title="New Warehouse" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New warehouse</h1>
                    <p className="text-sm text-muted-foreground">
                        Add a storage site for internal inventory.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/inventory/warehouses">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/inventory/warehouses');
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="code">Code</Label>
                    <Input
                        id="code"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value.toUpperCase())
                        }
                        maxLength={32}
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
                        Create warehouse
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
