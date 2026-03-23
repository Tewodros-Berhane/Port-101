import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEffect } from 'react';

export type InventoryMoveLineInput = {
    id?: string;
    source_lot_id: string;
    resulting_lot_id?: string | null;
    lot_code: string;
    quantity: number;
    source_lot_code?: string | null;
    resulting_lot_code?: string | null;
};

type LotOption = {
    id: string;
    product_id: string;
    location_id: string;
    code: string;
    tracking_mode: string;
    quantity_on_hand: number;
    quantity_reserved: number;
    available_quantity: number;
};

type Props = {
    trackingMode: string;
    moveType: string;
    quantity: number;
    productId: string;
    sourceLocationId: string;
    lines: InventoryMoveLineInput[];
    lotOptions: LotOption[];
    errors: Record<string, string>;
    disabled?: boolean;
    onChange: (lines: InventoryMoveLineInput[]) => void;
};

export default function InventoryMoveLinesEditor({
    trackingMode,
    moveType,
    quantity,
    productId,
    sourceLocationId,
    lines,
    lotOptions,
    errors,
    disabled = false,
    onChange,
}: Props) {
    const isTracked = trackingMode === 'lot' || trackingMode === 'serial';
    const isReceipt = moveType === 'receipt';
    const isSourceMove = moveType === 'delivery' || moveType === 'transfer';

    useEffect(() => {
        if (!isTracked && lines.length > 0) {
            onChange([]);
        }
    }, [isTracked, lines.length, onChange]);

    useEffect(() => {
        if (!isTracked || !isReceipt) {
            return;
        }

        if (trackingMode === 'serial') {
            const desiredCount = Math.max(0, Math.round(quantity || 0));

            if (desiredCount === 0) {
                if (lines.length > 0) {
                    onChange([]);
                }

                return;
            }

            const nextLines = Array.from({ length: desiredCount }, (_, index) => ({
                id: lines[index]?.id,
                source_lot_id: '',
                resulting_lot_id: lines[index]?.resulting_lot_id,
                lot_code: lines[index]?.lot_code ?? '',
                quantity: 1,
                source_lot_code: lines[index]?.source_lot_code,
                resulting_lot_code: lines[index]?.resulting_lot_code,
            }));

            if (JSON.stringify(nextLines) !== JSON.stringify(lines)) {
                onChange(nextLines);
            }

            return;
        }

        if (trackingMode === 'lot' && lines.length === 0 && quantity > 0) {
            onChange([
                {
                    source_lot_id: '',
                    lot_code: '',
                    quantity,
                },
            ]);
        }
    }, [isReceipt, isTracked, lines, onChange, quantity, trackingMode]);

    if (!isTracked) {
        return null;
    }

    const filteredLots = lotOptions.filter(
        (lot) =>
            lot.product_id === productId
            && (!sourceLocationId || lot.location_id === sourceLocationId),
    );

    const updateLine = (
        index: number,
        field: keyof InventoryMoveLineInput,
        value: string | number,
    ) => {
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          [field]: value,
                      }
                    : line,
            ),
        );
    };

    const addLine = () => {
        onChange([
            ...lines,
            {
                source_lot_id: '',
                lot_code: '',
                quantity: trackingMode === 'serial' ? 1 : 0,
            },
        ]);
    };

    const removeLine = (index: number) => {
        onChange(lines.filter((_, lineIndex) => lineIndex !== index));
    };

    return (
        <div className="rounded-xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold">Lot and serial assignments</h2>
                    <p className="text-xs text-muted-foreground">
                        {isReceipt
                            ? 'Tracked receipts require explicit lot or serial codes.'
                            : 'Leave source lines blank to auto-allocate on reserve, or add manual picks.'}
                    </p>
                </div>
                {(trackingMode === 'lot' || isSourceMove) && !disabled && (
                    <Button type="button" variant="outline" onClick={addLine}>
                        Add line
                    </Button>
                )}
            </div>

            <InputError message={errors.lines} />

            {lines.length === 0 ? (
                <div className="mt-4 rounded-lg border border-dashed px-4 py-6 text-sm text-muted-foreground">
                    {isSourceMove
                        ? 'No manual lot or serial picks yet. Reserve will auto-allocate from available stock.'
                        : 'No lot or serial lines yet.'}
                </div>
            ) : (
                <div className="mt-4 grid gap-3">
                    {lines.map((line, index) => (
                        <div key={line.id ?? `${index}-${line.source_lot_id}-${line.lot_code}`} className="rounded-lg border p-4">
                            <div className="grid gap-4 md:grid-cols-3">
                                {isReceipt ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor={`line-${index}-lot-code`}>
                                            {trackingMode === 'serial' ? 'Serial code' : 'Lot code'}
                                        </Label>
                                        <Input
                                            id={`line-${index}-lot-code`}
                                            value={line.lot_code}
                                            onChange={(event) =>
                                                updateLine(index, 'lot_code', event.target.value)
                                            }
                                            disabled={disabled}
                                        />
                                        <InputError message={errors[`lines.${index}.lot_code`]} />
                                    </div>
                                ) : (
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor={`line-${index}-source-lot`}>
                                            {trackingMode === 'serial' ? 'Source serial' : 'Source lot'}
                                        </Label>
                                        <select
                                            id={`line-${index}-source-lot`}
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                                            value={line.source_lot_id}
                                            onChange={(event) =>
                                                updateLine(index, 'source_lot_id', event.target.value)
                                            }
                                            disabled={disabled}
                                        >
                                            <option value="">Select source {trackingMode === 'serial' ? 'serial' : 'lot'}</option>
                                            {filteredLots.map((lot) => (
                                                <option key={lot.id} value={lot.id}>
                                                    {lot.code} · avail {lot.available_quantity.toFixed(4)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors[`lines.${index}.source_lot_id`]} />
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <Label htmlFor={`line-${index}-quantity`}>Quantity</Label>
                                    <Input
                                        id={`line-${index}-quantity`}
                                        type="number"
                                        min={trackingMode === 'serial' ? 1 : 0.0001}
                                        step={trackingMode === 'serial' ? 1 : 0.0001}
                                        value={String(line.quantity)}
                                        onChange={(event) =>
                                            updateLine(index, 'quantity', Number(event.target.value || 0))
                                        }
                                        disabled={disabled || trackingMode === 'serial'}
                                    />
                                    <InputError message={errors[`lines.${index}.quantity`]} />
                                </div>
                            </div>

                            {(line.source_lot_code || line.resulting_lot_code) && (
                                <div className="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                    {line.source_lot_code && (
                                        <span>Source: {line.source_lot_code}</span>
                                    )}
                                    {line.resulting_lot_code && (
                                        <span>Result: {line.resulting_lot_code}</span>
                                    )}
                                </div>
                            )}

                            {!disabled && (trackingMode === 'lot' || isSourceMove) && (
                                <div className="mt-3 flex justify-end">
                                    <Button type="button" variant="ghost" onClick={() => removeLine(index)}>
                                        Remove
                                    </Button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
