import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';

type Props = {
    company: {
        id?: string | null;
        name?: string | null;
        slug?: string | null;
        timezone?: string | null;
        currency_code?: string | null;
        owner?: string | null;
        owner_email?: string | null;
    };
    settings: {
        fiscal_year_start?: string | null;
        locale?: string | null;
        date_format?: string | null;
        number_format?: string | null;
        audit_retention_days?: number | null;
        tax_period?: 'monthly' | 'quarterly' | 'annual' | null;
        tax_submission_day?: number | null;
        approval_enabled?: boolean | null;
        approval_policy?: 'none' | 'amount_based' | 'always' | null;
        approval_threshold_amount?: number | null;
        approval_escalation_hours?: number | null;
        sales_order_prefix?: string | null;
        sales_order_next_number?: number | null;
        purchase_order_prefix?: string | null;
        purchase_order_next_number?: number | null;
        invoice_prefix?: string | null;
        invoice_next_number?: number | null;
    };
};

export default function CompanySettings({ company, settings }: Props) {
    const form = useForm({
        name: company.name ?? '',
        timezone: company.timezone ?? 'UTC',
        currency_code: company.currency_code ?? '',
        fiscal_year_start: settings.fiscal_year_start ?? '',
        locale: settings.locale ?? '',
        date_format: settings.date_format ?? 'Y-m-d',
        number_format: settings.number_format ?? '1,234.56',
        audit_retention_days: settings.audit_retention_days ?? 365,
        tax_period: settings.tax_period ?? 'monthly',
        tax_submission_day: settings.tax_submission_day ?? 15,
        approval_enabled: settings.approval_enabled ?? false,
        approval_policy: settings.approval_policy ?? 'none',
        approval_threshold_amount: settings.approval_threshold_amount ?? 10000,
        approval_escalation_hours: settings.approval_escalation_hours ?? 24,
        sales_order_prefix: settings.sales_order_prefix ?? 'SO',
        sales_order_next_number: settings.sales_order_next_number ?? 1001,
        purchase_order_prefix: settings.purchase_order_prefix ?? 'PO',
        purchase_order_next_number: settings.purchase_order_next_number ?? 1001,
        invoice_prefix: settings.invoice_prefix ?? 'INV',
        invoice_next_number: settings.invoice_next_number ?? 1001,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Settings', href: '/company/settings' },
            ]}
        >
            <Head title="Company Settings" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Company settings</h1>
                    <p className="text-sm text-muted-foreground">
                        Update company profile and operational defaults.
                    </p>
                </div>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put('/company/settings');
                }}
            >
                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Profile</h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Company name</Label>
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
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                value={company.slug ?? ''}
                                disabled
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="timezone">Timezone</Label>
                            <Input
                                id="timezone"
                                value={form.data.timezone}
                                onChange={(event) =>
                                    form.setData('timezone', event.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.timezone} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="currency_code">Currency code</Label>
                            <Input
                                id="currency_code"
                                value={form.data.currency_code}
                                onChange={(event) =>
                                    form.setData(
                                        'currency_code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                maxLength={3}
                            />
                            <InputError message={form.errors.currency_code} />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">
                        Operational defaults
                    </h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="fiscal_year_start">
                                Fiscal year start
                            </Label>
                            <Input
                                id="fiscal_year_start"
                                type="date"
                                value={form.data.fiscal_year_start}
                                onChange={(event) =>
                                    form.setData(
                                        'fiscal_year_start',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.fiscal_year_start}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="locale">Locale</Label>
                            <Input
                                id="locale"
                                value={form.data.locale}
                                onChange={(event) =>
                                    form.setData('locale', event.target.value)
                                }
                                placeholder="en_US"
                            />
                            <InputError message={form.errors.locale} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="date_format">Date format</Label>
                            <select
                                id="date_format"
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                value={form.data.date_format}
                                onChange={(event) =>
                                    form.setData(
                                        'date_format',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="Y-m-d">YYYY-MM-DD</option>
                                <option value="d/m/Y">DD/MM/YYYY</option>
                                <option value="m/d/Y">MM/DD/YYYY</option>
                            </select>
                            <InputError message={form.errors.date_format} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="number_format">Number format</Label>
                            <select
                                id="number_format"
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                value={form.data.number_format}
                                onChange={(event) =>
                                    form.setData(
                                        'number_format',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="1,234.56">1,234.56</option>
                                <option value="1.234,56">1.234,56</option>
                                <option value="1 234,56">1 234,56</option>
                            </select>
                            <InputError message={form.errors.number_format} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="audit_retention_days">
                                Audit retention (days)
                            </Label>
                            <Input
                                id="audit_retention_days"
                                type="number"
                                min={1}
                                max={3650}
                                value={String(form.data.audit_retention_days)}
                                onChange={(event) =>
                                    form.setData(
                                        'audit_retention_days',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.audit_retention_days}
                            />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Tax periods</h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tax_period">Tax period</Label>
                            <select
                                id="tax_period"
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                value={form.data.tax_period}
                                onChange={(event) =>
                                    form.setData(
                                        'tax_period',
                                        event.target.value as
                                            | 'monthly'
                                            | 'quarterly'
                                            | 'annual',
                                    )
                                }
                            >
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annual">Annual</option>
                            </select>
                            <InputError message={form.errors.tax_period} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tax_submission_day">
                                Filing day of month
                            </Label>
                            <Input
                                id="tax_submission_day"
                                type="number"
                                min={1}
                                max={28}
                                value={String(form.data.tax_submission_day)}
                                onChange={(event) =>
                                    form.setData(
                                        'tax_submission_day',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.tax_submission_day}
                            />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">
                        Approval policy defaults
                    </h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="approval_enabled">
                                Approval workflow
                            </Label>
                            <select
                                id="approval_enabled"
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                value={form.data.approval_enabled ? '1' : '0'}
                                onChange={(event) =>
                                    form.setData(
                                        'approval_enabled',
                                        event.target.value === '1',
                                    )
                                }
                            >
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                            <InputError
                                message={form.errors.approval_enabled}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="approval_policy">
                                Default policy
                            </Label>
                            <select
                                id="approval_policy"
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                value={form.data.approval_policy}
                                onChange={(event) =>
                                    form.setData(
                                        'approval_policy',
                                        event.target.value as
                                            | 'none'
                                            | 'amount_based'
                                            | 'always',
                                    )
                                }
                            >
                                <option value="none">No approvals</option>
                                <option value="amount_based">
                                    Amount-based approvals
                                </option>
                                <option value="always">
                                    Always require approval
                                </option>
                            </select>
                            <InputError message={form.errors.approval_policy} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="approval_threshold_amount">
                                Approval threshold amount
                            </Label>
                            <Input
                                id="approval_threshold_amount"
                                type="number"
                                min={0}
                                step="0.01"
                                value={String(
                                    form.data.approval_threshold_amount,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'approval_threshold_amount',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.approval_threshold_amount}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="approval_escalation_hours">
                                Escalation SLA (hours)
                            </Label>
                            <Input
                                id="approval_escalation_hours"
                                type="number"
                                min={1}
                                max={168}
                                value={String(
                                    form.data.approval_escalation_hours,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'approval_escalation_hours',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.approval_escalation_hours}
                            />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">
                        Numbering sequences
                    </h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="sales_order_prefix">
                                Sales order prefix
                            </Label>
                            <Input
                                id="sales_order_prefix"
                                value={form.data.sales_order_prefix}
                                onChange={(event) =>
                                    form.setData(
                                        'sales_order_prefix',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                maxLength={12}
                            />
                            <InputError
                                message={form.errors.sales_order_prefix}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sales_order_next_number">
                                Sales order next number
                            </Label>
                            <Input
                                id="sales_order_next_number"
                                type="number"
                                min={1}
                                value={String(
                                    form.data.sales_order_next_number,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'sales_order_next_number',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.sales_order_next_number}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="purchase_order_prefix">
                                Purchase order prefix
                            </Label>
                            <Input
                                id="purchase_order_prefix"
                                value={form.data.purchase_order_prefix}
                                onChange={(event) =>
                                    form.setData(
                                        'purchase_order_prefix',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                maxLength={12}
                            />
                            <InputError
                                message={form.errors.purchase_order_prefix}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="purchase_order_next_number">
                                Purchase order next number
                            </Label>
                            <Input
                                id="purchase_order_next_number"
                                type="number"
                                min={1}
                                value={String(
                                    form.data.purchase_order_next_number,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'purchase_order_next_number',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.purchase_order_next_number}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="invoice_prefix">
                                Invoice prefix
                            </Label>
                            <Input
                                id="invoice_prefix"
                                value={form.data.invoice_prefix}
                                onChange={(event) =>
                                    form.setData(
                                        'invoice_prefix',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                maxLength={12}
                            />
                            <InputError message={form.errors.invoice_prefix} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="invoice_next_number">
                                Invoice next number
                            </Label>
                            <Input
                                id="invoice_next_number"
                                type="number"
                                min={1}
                                value={String(form.data.invoice_next_number)}
                                onChange={(event) =>
                                    form.setData(
                                        'invoice_next_number',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.invoice_next_number}
                            />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Ownership</h2>
                    <div className="mt-3 text-sm text-muted-foreground">
                        <p>Owner: {company.owner ?? '-'}</p>
                        <p>Email: {company.owner_email ?? '-'}</p>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Save settings
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
