import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type AccountingManualJournalLineInput = {
    account_id: string;
    description: string;
    debit: number;
    credit: number;
};

type AccountOption = {
    id: string;
    code: string;
    name: string;
    account_type: string;
};

type Props = {
    lines: AccountingManualJournalLineInput[];
    accounts: AccountOption[];
    errors: Record<string, string | undefined>;
    onChange: (lines: AccountingManualJournalLineInput[]) => void;
    disabled?: boolean;
};

export default function AccountingManualJournalLinesEditor({
    lines,
    accounts,
    errors,
    onChange,
    disabled = false,
}: Props) {
    const updateLine = (
        index: number,
        patch: Partial<AccountingManualJournalLineInput>,
    ) => {
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, ...patch } : line,
            ),
        );
    };

    const appendLine = () => {
        onChange([
            ...lines,
            {
                account_id: '',
                description: '',
                debit: 0,
                credit: 0,
            },
        ]);
    };

    const removeLine = (index: number) => {
        if (lines.length <= 2) {
            return;
        }

        onChange(lines.filter((_, lineIndex) => lineIndex !== index));
    };

    return (
        <div className="rounded-xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold">Journal lines</h2>
                    <p className="text-xs text-muted-foreground">
                        Balance debit and credit lines before posting.
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

            <InputError message={errors.lines} />

            <div className="mt-4 space-y-4">
                {lines.map((line, index) => (
                    <div key={index} className="rounded-lg border p-3">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                            <div className="grid gap-2 xl:col-span-2">
                                <Label htmlFor={`line-${index}-account`}>
                                    Account
                                </Label>
                                <select
                                    id={`line-${index}-account`}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                                    value={line.account_id}
                                    onChange={(event) =>
                                        updateLine(index, {
                                            account_id: event.target.value,
                                        })
                                    }
                                    disabled={disabled}
                                >
                                    <option value="">Select account</option>
                                    {accounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.code} - {account.name} (
                                            {account.account_type})
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors[`lines.${index}.account_id`]}
                                />
                            </div>

                            <div className="grid gap-2 xl:col-span-2">
                                <Label htmlFor={`line-${index}-description`}>
                                    Line description
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
                                    message={errors[`lines.${index}.description`]}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`line-${index}-debit`}>
                                    Debit
                                </Label>
                                <Input
                                    id={`line-${index}-debit`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={line.debit}
                                    onChange={(event) =>
                                        updateLine(index, {
                                            debit: numeric(
                                                event.target.value,
                                                0,
                                            ),
                                        })
                                    }
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`lines.${index}.debit`]}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`line-${index}-credit`}>
                                    Credit
                                </Label>
                                <Input
                                    id={`line-${index}-credit`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={line.credit}
                                    onChange={(event) =>
                                        updateLine(index, {
                                            credit: numeric(
                                                event.target.value,
                                                0,
                                            ),
                                        })
                                    }
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`lines.${index}.credit`]}
                                />
                            </div>
                        </div>

                        <div className="mt-3 flex justify-end">
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => removeLine(index)}
                                disabled={disabled || lines.length <= 2}
                            >
                                Remove line
                            </Button>
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
