import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type AccountingInvoiceLineInput = {
    product_id?: string | null;
    description: string;
    quantity: number;
    unit_price: number;
    tax_rate: number;
};

type ProductOption = {
    id: string;
    name: string;
    sku?: string | null;
};

type Props = {
    lines: AccountingInvoiceLineInput[];
    products: ProductOption[];
    errors: Record<string, string | undefined>;
    onChange: (lines: AccountingInvoiceLineInput[]) => void;
    disabled?: boolean;
};

export default function AccountingInvoiceLineItemsEditor({
    lines,
    products,
    errors,
    onChange,
    disabled = false,
}: Props) {
    const updateLine = (
        index: number,
        patch: Partial<AccountingInvoiceLineInput>,
    ) => {
        const next = lines.map((line, lineIndex) =>
            lineIndex === index ? { ...line, ...patch } : line,
        );
        onChange(next);
    };

    const appendLine = () => {
        onChange([
            ...lines,
            {
                product_id: '',
                description: '',
                quantity: 1,
                unit_price: 0,
                tax_rate: 0,
            },
        ]);
    };

    const removeLine = (index: number) => {
        if (lines.length <= 1) {
            return;
        }

        onChange(lines.filter((_, lineIndex) => lineIndex !== index));
    };

    return (
        <div className="rounded-xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold">Invoice lines</h2>
                    <p className="text-xs text-muted-foreground">
                        Define quantities, pricing, and tax rates.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={appendLine}
                    disabled={disabled}
                >
                    Add line
                </Button>
            </div>

            <div className="mt-4 space-y-4">
                {lines.map((line, index) => (
                    <div key={index} className="rounded-lg border p-3">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                            <div className="grid gap-2 xl:col-span-2">
                                <Label htmlFor={`line-${index}-product`}>
                                    Product
                                </Label>
                                <select
                                    id={`line-${index}-product`}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                                    value={line.product_id ?? ''}
                                    onChange={(event) =>
                                        updateLine(index, {
                                            product_id:
                                                event.target.value || null,
                                        })
                                    }
                                    disabled={disabled}
                                >
                                    <option value="">No product</option>
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
                                    message={errors[`lines.${index}.product_id`]}
                                />
                            </div>

                            <div className="grid gap-2 xl:col-span-2">
                                <Label htmlFor={`line-${index}-description`}>
                                    Description
                                </Label>
                                <Input
                                    id={`line-${index}-description`}
                                    value={line.description}
                                    onChange={(event) =>
                                        updateLine(index, {
                                            description: event.target.value,
                                        })
                                    }
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[
                                        `lines.${index}.description`
                                    ]}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`line-${index}-quantity`}>
                                    Quantity
                                </Label>
                                <Input
                                    id={`line-${index}-quantity`}
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    value={line.quantity}
                                    onChange={(event) =>
                                        updateLine(index, {
                                            quantity: numeric(
                                                event.target.value,
                                                0,
                                            ),
                                        })
                                    }
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`lines.${index}.quantity`]}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`line-${index}-unit-price`}>
                                    Unit price
                                </Label>
                                <Input
                                    id={`line-${index}-unit-price`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={line.unit_price}
                                    onChange={(event) =>
                                        updateLine(index, {
                                            unit_price: numeric(
                                                event.target.value,
                                                0,
                                            ),
                                        })
                                    }
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[
                                        `lines.${index}.unit_price`
                                    ]}
                                />
                            </div>
                        </div>

                        <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                            <div className="grid gap-2 xl:col-span-2">
                                <Label htmlFor={`line-${index}-tax-rate`}>
                                    Tax rate %
                                </Label>
                                <Input
                                    id={`line-${index}-tax-rate`}
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={line.tax_rate}
                                    onChange={(event) =>
                                        updateLine(index, {
                                            tax_rate: numeric(
                                                event.target.value,
                                                0,
                                            ),
                                        })
                                    }
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`lines.${index}.tax_rate`]}
                                />
                            </div>
                            <div className="flex items-end justify-end md:col-span-2 xl:col-span-4">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() => removeLine(index)}
                                    disabled={disabled || lines.length <= 1}
                                >
                                    Remove line
                                </Button>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function numeric(value: string, fallback: number): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : fallback;
}
