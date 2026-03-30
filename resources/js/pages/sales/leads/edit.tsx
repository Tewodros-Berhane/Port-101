import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { FormErrorSummary } from '@/components/shell/form-error-summary';
import { FormSectionCard } from '@/components/shell/form-section-card';
import { FormShell } from '@/components/shell/form-shell';
import { StickyFormFooter } from '@/components/shell/sticky-form-footer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import { Textarea } from '@/components/ui/textarea';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type PartnerOption = {
    id: string;
    name: string;
};

type Lead = {
    id: string;
    partner_id?: string | null;
    title: string;
    stage: string;
    estimated_value: number;
    expected_close_date?: string | null;
    notes?: string | null;
    converted_at?: string | null;
};

type Props = {
    lead: Lead;
    partners: PartnerOption[];
    stages: string[];
};

const NATIVE_SELECT_CLASS =
    'h-10 w-full rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30';

export default function SalesLeadEdit({ lead, partners, stages }: Props) {
    const form = useForm({
        partner_id: lead.partner_id ?? '',
        title: lead.title,
        stage: lead.stage,
        estimated_value: lead.estimated_value,
        expected_close_date: lead.expected_close_date ?? '',
        notes: lead.notes ?? '',
    });
    const { allowNextNavigation } = useUnsavedChangesGuard({
        enabled: form.isDirty && !form.processing,
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.sales, { title: 'Leads', href: '/company/sales/leads' },
                {
                    title: lead.title,
                    href: `/company/sales/leads/${lead.id}/edit`,
                },)}
        >
            <Head title={lead.title} />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    allowNextNavigation();
                    form.put(`/company/sales/leads/${lead.id}`);
                }}
            >
                <FormShell
                    title="Edit lead"
                    description="Keep lead data, stage, and commercial expectations current so downstream quote and order conversion stays accurate."
                    actions={
                        <BackLinkAction href="/company/sales/leads" label="Back to leads" variant="outline" />
                    }
                    meta={
                        <StatusBadge
                            status={lead.converted_at ? 'completed' : 'draft'}
                            label={
                                lead.converted_at
                                    ? 'Converted'
                                    : 'Not converted'
                            }
                        />
                    }
                    errorSummary={<FormErrorSummary errors={form.errors} />}
                    aside={
                        <FormSectionCard
                            title="Lead status"
                            description="Operational context that helps the pipeline stay trustworthy."
                        >
                            <div className="space-y-3 text-sm text-[color:var(--text-secondary)]">
                                <p>
                                    Converted:{' '}
                                    <span className="font-medium text-foreground">
                                        {lead.converted_at ? 'Yes' : 'No'}
                                    </span>
                                </p>
                                <p>
                                    Changing the stage and expected close date
                                    has the most direct impact on pipeline
                                    reporting.
                                </p>
                            </div>
                        </FormSectionCard>
                    }
                    footer={
                        <StickyFormFooter
                            secondaryActions={
                                <>
                                    <Button variant="outline" asChild>
                                        <Link href="/company/sales/leads">
                                            Cancel
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={() => {
                                            allowNextNavigation();
                                            form.delete(
                                                `/company/sales/leads/${lead.id}`,
                                            );
                                        }}
                                        disabled={form.processing}
                                    >
                                        Delete
                                    </Button>
                                </>
                            }
                            meta="Unsaved changes are protected while this form is open."
                            primaryActions={
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save changes
                                </Button>
                            }
                        />
                    }
                >
                    <FormSectionCard
                        title="Lead profile"
                        description="Maintain the basic opportunity identity and linked partner."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="title">Lead title</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={form.errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="partner_id">Partner</Label>
                                <select
                                    id="partner_id"
                                    className={NATIVE_SELECT_CLASS}
                                    value={form.data.partner_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'partner_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">No partner linked</option>
                                    {partners.map((partner) => (
                                        <option
                                            key={partner.id}
                                            value={partner.id}
                                        >
                                            {partner.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.partner_id} />
                            </div>
                        </div>
                    </FormSectionCard>

                    <FormSectionCard
                        title="Pipeline details"
                        description="Track the current stage, expected value, and close timing."
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="stage">Stage</Label>
                                <select
                                    id="stage"
                                    className={NATIVE_SELECT_CLASS}
                                    value={form.data.stage}
                                    onChange={(event) =>
                                        form.setData(
                                            'stage',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {stages.map((stage) => (
                                        <option key={stage} value={stage}>
                                            {stage}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.stage} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="estimated_value">
                                    Estimated value
                                </Label>
                                <Input
                                    id="estimated_value"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={String(form.data.estimated_value)}
                                    onChange={(event) =>
                                        form.setData(
                                            'estimated_value',
                                            Number(event.target.value || 0),
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.estimated_value}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="expected_close_date">
                                    Expected close date
                                </Label>
                                <Input
                                    id="expected_close_date"
                                    type="date"
                                    value={form.data.expected_close_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'expected_close_date',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.expected_close_date}
                                />
                            </div>
                        </div>
                    </FormSectionCard>

                    <FormSectionCard
                        title="Notes"
                        description="Keep qualification notes and handoff context visible to the sales team."
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </FormSectionCard>
                </FormShell>
            </form>
        </AppLayout>
    );
}
