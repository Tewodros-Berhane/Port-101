import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

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

export default function SalesLeadEdit({ lead, partners, stages }: Props) {
    const form = useForm({
        partner_id: lead.partner_id ?? '',
        title: lead.title,
        stage: lead.stage,
        estimated_value: lead.estimated_value,
        expected_close_date: lead.expected_close_date ?? '',
        notes: lead.notes ?? '',
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Sales', href: '/company/sales' },
                { title: 'Leads', href: '/company/sales/leads' },
                {
                    title: lead.title,
                    href: `/company/sales/leads/${lead.id}/edit`,
                },
            ]}
        >
            <Head title={lead.title} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit lead</h1>
                    <p className="text-sm text-muted-foreground">
                        Keep lead data and progression up to date.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/sales/leads">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/sales/leads/${lead.id}`);
                }}
            >
                <div className="rounded-xl border p-4 text-sm">
                    <p>
                        Converted:{' '}
                        <span className="font-medium">
                            {lead.converted_at ? 'Yes' : 'No'}
                        </span>
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="title">Lead title</Label>
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.title} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="partner_id">Partner</Label>
                    <select
                        id="partner_id"
                        className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                        value={form.data.partner_id}
                        onChange={(event) =>
                            form.setData('partner_id', event.target.value)
                        }
                    >
                        <option value="">No partner linked</option>
                        {partners.map((partner) => (
                            <option key={partner.id} value={partner.id}>
                                {partner.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.partner_id} />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="stage">Stage</Label>
                        <select
                            id="stage"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.stage}
                            onChange={(event) =>
                                form.setData('stage', event.target.value)
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
                        <Label htmlFor="estimated_value">Estimated value</Label>
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
                        <InputError message={form.errors.estimated_value} />
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

                <div className="grid gap-2">
                    <Label htmlFor="notes">Notes</Label>
                    <textarea
                        id="notes"
                        className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                    <InputError message={form.errors.notes} />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Save changes
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() =>
                            form.delete(`/company/sales/leads/${lead.id}`)
                        }
                        disabled={form.processing}
                    >
                        Delete
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
