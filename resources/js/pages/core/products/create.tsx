import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Option = {
    id: string;
    name: string;
};

type Props = {
    uoms: Option[];
    taxes: Option[];
};

export default function ProductCreate({ uoms, taxes }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('core.products.manage');
    const form = useForm({
        sku: '',
        name: '',
        type: 'stock',
        uom_id: '',
        default_tax_id: '',
        description: '',
        is_active: true,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Products', href: '/core/products' },
                { title: 'Create', href: '/core/products/create' },
            ]}
        >
            <Head title="New Product" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New product</h1>
                    <p className="text-sm text-muted-foreground">
                        Add a product or service.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/core/products">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/core/products');
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
                    <Label htmlFor="sku">SKU</Label>
                    <Input
                        id="sku"
                        value={form.data.sku}
                        onChange={(event) =>
                            form.setData('sku', event.target.value)
                        }
                    />
                    <InputError message={form.errors.sku} />
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
                        <option value="stock">Stock</option>
                        <option value="service">Service</option>
                    </select>
                    <InputError message={form.errors.type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="uom_id">Unit of Measure</Label>
                    <select
                        id="uom_id"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.uom_id}
                        onChange={(event) =>
                            form.setData('uom_id', event.target.value)
                        }
                    >
                        <option value="">None</option>
                        {uoms.map((uom) => (
                            <option key={uom.id} value={uom.id}>
                                {uom.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.uom_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="default_tax_id">Default tax</Label>
                    <select
                        id="default_tax_id"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.default_tax_id}
                        onChange={(event) =>
                            form.setData('default_tax_id', event.target.value)
                        }
                    >
                        <option value="">None</option>
                        {taxes.map((tax) => (
                            <option key={tax.id} value={tax.id}>
                                {tax.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.default_tax_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="description">Description</Label>
                    <textarea
                        id="description"
                        className="min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                    <InputError message={form.errors.description} />
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
                            Create product
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
