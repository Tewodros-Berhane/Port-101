import AttachmentsPanel from '@/components/attachments-panel';
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
    sku?: string | null;
};

type Product = {
    id: string;
    sku?: string | null;
    name: string;
    type: string;
    tracking_mode?: string | null;
    uom_id?: string | null;
    default_tax_id?: string | null;
    description?: string | null;
    is_active: boolean;
    bundle: {
        enabled: boolean;
        mode: string;
        components: Array<{
            product_id: string;
            quantity: number;
            product_name?: string | null;
            product_sku?: string | null;
        }>;
    };
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
    product: Product;
    uoms: Option[];
    taxes: Option[];
    attachments: Attachment[];
    trackingModes: string[];
    bundleModes: string[];
    bundleProducts: Option[];
};

export default function ProductEdit({
    product,
    uoms,
    taxes,
    attachments,
    trackingModes,
    bundleModes,
    bundleProducts,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('core.products.manage');
    const canViewAttachments = hasPermission('core.attachments.view');
    const canManageAttachments = hasPermission('core.attachments.manage');
    const form = useForm({
        sku: product.sku ?? '',
        name: product.name ?? '',
        type: product.type ?? 'stock',
        tracking_mode: product.tracking_mode ?? 'none',
        uom_id: product.uom_id ?? '',
        default_tax_id: product.default_tax_id ?? '',
        description: product.description ?? '',
        is_active: product.is_active ?? true,
        bundle: {
            enabled: product.bundle?.enabled ?? false,
            mode: product.bundle?.mode ?? 'sales_only',
            components: (product.bundle?.components ?? []).map((component) => ({
                product_id: component.product_id,
                quantity: String(component.quantity),
            })),
        },
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Products', href: '/core/products' },
                {
                    title: product.name,
                    href: `/core/products/${product.id}/edit`,
                },
            ]}
        >
            <Head title={product.name} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Edit product</h1>
                    <p className="text-sm text-muted-foreground">
                        Update product or service details.
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
                    form.put(`/core/products/${product.id}`);
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
                            form.setData((data) => ({
                                ...data,
                                type: event.target.value,
                                tracking_mode:
                                    event.target.value === 'stock'
                                        ? data.tracking_mode
                                        : 'none',
                                bundle:
                                    event.target.value === 'stock'
                                        ? data.bundle
                                        : {
                                              enabled: false,
                                              mode: data.bundle.mode,
                                              components: [],
                                          },
                            }))
                        }
                    >
                        <option value="stock">Stock</option>
                        <option value="service">Service</option>
                    </select>
                    <InputError message={form.errors.type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="tracking_mode">Tracking</Label>
                    <select
                        id="tracking_mode"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.tracking_mode}
                        onChange={(event) =>
                            form.setData('tracking_mode', event.target.value)
                        }
                        disabled={form.data.type !== 'stock'}
                    >
                        {trackingModes.map((mode) => (
                            <option key={mode} value={mode}>
                                {mode}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.tracking_mode} />
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

                <div className="rounded-xl border p-4">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Bundle configuration
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Sales-only bundles explode into component demand
                                during delivery reservation.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="bundle_enabled"
                                checked={form.data.bundle.enabled}
                                disabled={form.data.type !== 'stock'}
                                onCheckedChange={(value) =>
                                    form.setData('bundle', {
                                        ...form.data.bundle,
                                        enabled:
                                            form.data.type === 'stock' &&
                                            Boolean(value),
                                    })
                                }
                            />
                            <Label htmlFor="bundle_enabled">Enable bundle</Label>
                        </div>
                    </div>

                    {form.data.bundle.enabled && form.data.type === 'stock' && (
                        <div className="mt-4 grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="bundle_mode">Bundle mode</Label>
                                <select
                                    id="bundle_mode"
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                    value={form.data.bundle.mode}
                                    onChange={(event) =>
                                        form.setData('bundle', {
                                            ...form.data.bundle,
                                            mode: event.target.value,
                                        })
                                    }
                                >
                                    {bundleModes.map((mode) => (
                                        <option key={mode} value={mode}>
                                            {mode}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={
                                        form.errors['bundle.mode'] ??
                                        form.errors['bundle.enabled']
                                    }
                                />
                            </div>

                            <div className="grid gap-3">
                                <div className="flex items-center justify-between gap-3">
                                    <Label>Components</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            form.setData('bundle', {
                                                ...form.data.bundle,
                                                components: [
                                                    ...form.data.bundle
                                                        .components,
                                                    {
                                                        product_id: '',
                                                        quantity: '1',
                                                    },
                                                ],
                                            })
                                        }
                                    >
                                        Add component
                                    </Button>
                                </div>

                                {form.data.bundle.components.length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        No components added yet.
                                    </p>
                                )}

                                {form.data.bundle.components.map(
                                    (component, index) => (
                                        <div
                                            key={`${index}-${component.product_id}`}
                                            className="grid gap-3 rounded-lg border p-3 md:grid-cols-[minmax(0,1fr)_160px_auto]"
                                        >
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`bundle_component_product_${index}`}
                                                >
                                                    Component product
                                                </Label>
                                                <select
                                                    id={`bundle_component_product_${index}`}
                                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                                    value={component.product_id}
                                                    onChange={(event) =>
                                                        form.setData('bundle', {
                                                            ...form.data.bundle,
                                                            components:
                                                                form.data.bundle.components.map(
                                                                    (
                                                                        row,
                                                                        rowIndex,
                                                                    ) =>
                                                                        rowIndex ===
                                                                        index
                                                                            ? {
                                                                                  ...row,
                                                                                  product_id:
                                                                                      event
                                                                                          .target
                                                                                          .value,
                                                                              }
                                                                            : row,
                                                                ),
                                                        })
                                                    }
                                                >
                                                    <option value="">
                                                        Select stock product
                                                    </option>
                                                    {bundleProducts.map(
                                                        (bundleProduct) => (
                                                            <option
                                                                key={
                                                                    bundleProduct.id
                                                                }
                                                                value={
                                                                    bundleProduct.id
                                                                }
                                                            >
                                                                {
                                                                    bundleProduct.name
                                                                }
                                                                {bundleProduct.sku
                                                                    ? ` (${bundleProduct.sku})`
                                                                    : ''}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`bundle_component_quantity_${index}`}
                                                >
                                                    Quantity
                                                </Label>
                                                <Input
                                                    id={`bundle_component_quantity_${index}`}
                                                    type="number"
                                                    min="0.0001"
                                                    step="0.0001"
                                                    value={component.quantity}
                                                    onChange={(event) =>
                                                        form.setData('bundle', {
                                                            ...form.data.bundle,
                                                            components:
                                                                form.data.bundle.components.map(
                                                                    (
                                                                        row,
                                                                        rowIndex,
                                                                    ) =>
                                                                        rowIndex ===
                                                                        index
                                                                            ? {
                                                                                  ...row,
                                                                                  quantity:
                                                                                      event
                                                                                          .target
                                                                                          .value,
                                                                              }
                                                                            : row,
                                                                ),
                                                        })
                                                    }
                                                />
                                            </div>

                                            <div className="flex items-end">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        form.setData('bundle', {
                                                            ...form.data.bundle,
                                                            components:
                                                                form.data.bundle.components.filter(
                                                                    (
                                                                        _,
                                                                        rowIndex,
                                                                    ) =>
                                                                        rowIndex !==
                                                                        index,
                                                                ),
                                                        })
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        </div>
                                    ),
                                )}

                                <InputError
                                    message={form.errors['bundle.components']}
                                />
                            </div>
                        </div>
                    )}
                </div>

                <AttachmentsPanel
                    attachableType="product"
                    attachableId={product.id}
                    attachments={attachments}
                    canView={canViewAttachments}
                    canManage={canManageAttachments}
                />

                {canManage && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                form.delete(`/core/products/${product.id}`)
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
