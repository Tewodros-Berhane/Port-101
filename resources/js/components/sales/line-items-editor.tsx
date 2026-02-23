import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProductOption = {
    id: string;
    name: string;
    sku?: string | null;
};

export type SalesLineItem = {
    product_id: string | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_percent: number;
    tax_rate: number;
};

type Props = {
    lines: SalesLineItem[];
    products: ProductOption[];
    disabled?: boolean;
    errors?: Record<string, string | undefined>;
    onChange: (lines: SalesLineItem[]) => void;
};

const createLine = (): SalesLineItem => ({
    product_id: null,
    description: '',
    quantity: 1,
    unit_price: 0,
    discount_percent: 0,
    tax_rate: 0,
});

const toNumber = (value: string) => {
    const next = Number(value);
    return Number.isFinite(next) ? next : 0;
};

export default function SalesLineItemsEditor({
    lines,
    products,
    disabled = false,
    errors = {},
    onChange,
}: Props) {
    const updateLine = <K extends keyof SalesLineItem>(
        index: number,
        key: K,
        value: SalesLineItem[K],
    ) => {
        const next = [...lines];
        next[index] = {
            ...next[index],
            [key]: value,
        };
        onChange(next);
    };

    const addLine = () => {
        onChange([...lines, createLine()]);
    };

    const removeLine = (index: number) => {
        if (lines.length <= 1) {
            return;
        }

        onChange(lines.filter((_, currentIndex) => currentIndex !== index));
    };

    const totals = lines.reduce(
        (carry, line) => {
            const gross = line.quantity * line.unit_price;
            const discount = gross * (line.discount_percent / 100);
            const subtotal = gross - discount;
            const tax = subtotal * (line.tax_rate / 100);
            const total = subtotal + tax;

            return {
                subtotal: carry.subtotal + gross,
                discount_total: carry.discount_total + discount,
                tax_total: carry.tax_total + tax,
                grand_total: carry.grand_total + total,
            };
        },
        {
            subtotal: 0,
            discount_total: 0,
            tax_total: 0,
            grand_total: 0,
        },
    );

    return (
        <div className="rounded-xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold">Line items</h2>
                    <p className="text-xs text-muted-foreground">
                        Add products, quantity, pricing, and tax rates.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={addLine}
                    disabled={disabled}
                >
                    Add line
                </Button>
            </div>

            <div className="mt-4 overflow-x-auto rounded-lg border">
                <table className="w-full min-w-[960px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-3 py-2 font-medium">Product</th>
                            <th className="px-3 py-2 font-medium">
                                Description
                            </th>
                            <th className="px-3 py-2 font-medium">Qty</th>
                            <th className="px-3 py-2 font-medium">Unit</th>
                            <th className="px-3 py-2 font-medium">Disc %</th>
                            <th className="px-3 py-2 font-medium">Tax %</th>
                            <th className="px-3 py-2 font-medium text-right">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {lines.map((line, index) => (
                            <tr key={index}>
                                <td className="px-3 py-2 align-top">
                                    <select
                                        className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                                        value={line.product_id ?? ''}
                                        disabled={disabled}
                                        onChange={(event) => {
                                            const selectedProduct = products.find(
                                                (product) =>
                                                    product.id ===
                                                    event.target.value,
                                            );

                                            updateLine(
                                                index,
                                                'product_id',
                                                event.target.value || null,
                                            );

                                            if (
                                                selectedProduct &&
                                                !line.description
                                            ) {
                                                updateLine(
                                                    index,
                                                    'description',
                                                    selectedProduct.name,
                                                );
                                            }
                                        }}
                                    >
                                        <option value="">No linked product</option>
                                        {products.map((product) => (
                                            <option
                                                key={product.id}
                                                value={product.id}
                                            >
                                                {product.name}
                                                {product.sku
                                                    ? ` (${product.sku})`
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={
                                            errors[
                                                `lines.${index}.product_id`
                                            ]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2 align-top">
                                    <Input
                                        value={line.description}
                                        disabled={disabled}
                                        onChange={(event) =>
                                            updateLine(
                                                index,
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `lines.${index}.description`
                                            ]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2 align-top">
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.0001"
                                        value={String(line.quantity)}
                                        disabled={disabled}
                                        onChange={(event) =>
                                            updateLine(
                                                index,
                                                'quantity',
                                                toNumber(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors[`lines.${index}.quantity`]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2 align-top">
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={String(line.unit_price)}
                                        disabled={disabled}
                                        onChange={(event) =>
                                            updateLine(
                                                index,
                                                'unit_price',
                                                toNumber(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors[`lines.${index}.unit_price`]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2 align-top">
                                    <Input
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        value={String(line.discount_percent)}
                                        disabled={disabled}
                                        onChange={(event) =>
                                            updateLine(
                                                index,
                                                'discount_percent',
                                                toNumber(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `lines.${index}.discount_percent`
                                            ]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2 align-top">
                                    <Input
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        value={String(line.tax_rate)}
                                        disabled={disabled}
                                        onChange={(event) =>
                                            updateLine(
                                                index,
                                                'tax_rate',
                                                toNumber(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors[`lines.${index}.tax_rate`]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2 text-right align-top">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={disabled || lines.length <= 1}
                                        onClick={() => removeLine(index)}
                                    >
                                        Remove
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <InputError message={errors.lines} />

            <div className="mt-4 grid gap-2 md:grid-cols-4">
                <div className="rounded-md border p-3">
                    <Label className="text-xs text-muted-foreground">
                        Subtotal
                    </Label>
                    <p className="mt-1 text-sm font-semibold">
                        {totals.subtotal.toFixed(2)}
                    </p>
                </div>
                <div className="rounded-md border p-3">
                    <Label className="text-xs text-muted-foreground">
                        Discount
                    </Label>
                    <p className="mt-1 text-sm font-semibold">
                        {totals.discount_total.toFixed(2)}
                    </p>
                </div>
                <div className="rounded-md border p-3">
                    <Label className="text-xs text-muted-foreground">
                        Tax
                    </Label>
                    <p className="mt-1 text-sm font-semibold">
                        {totals.tax_total.toFixed(2)}
                    </p>
                </div>
                <div className="rounded-md border p-3">
                    <Label className="text-xs text-muted-foreground">
                        Total
                    </Label>
                    <p className="mt-1 text-sm font-semibold">
                        {totals.grand_total.toFixed(2)}
                    </p>
                </div>
            </div>
        </div>
    );
}
