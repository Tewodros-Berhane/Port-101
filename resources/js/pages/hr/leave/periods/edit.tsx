import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleLinks, moduleBreadcrumbs } from '@/lib/page-navigation';

type Props = { leavePeriod: { id: string; name: string; start_date: string; end_date: string; is_closed: boolean } };

export default function HrLeavePeriodEdit({ leavePeriod }: Props) {
    const form = useForm(leavePeriod);

    return (
        <AppLayout breadcrumbs={moduleBreadcrumbs(companyModuleLinks.hr, { title: 'Leave', href: '/company/hr/leave' }, { title: 'Edit period', href: `/company/hr/leave/periods/${leavePeriod.id}/edit` })}>
            <Head title="Edit leave period" />
            <div className="flex items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">Edit leave period</h1><p className="text-sm text-muted-foreground">Adjust the operational leave window and close state.</p></div><BackLinkAction href="/company/hr/leave" label="Back to leave" variant="ghost" /></div>
            <form className="mt-6 grid gap-6 md:max-w-2xl" onSubmit={(event) => { event.preventDefault(); form.put(`/company/hr/leave/periods/${leavePeriod.id}`); }}>
                <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                <Field label="Start date" error={form.errors.start_date}><Input type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} required /></Field>
                <Field label="End date" error={form.errors.end_date}><Input type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} required /></Field>
                <label className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"><input type="checkbox" checked={form.data.is_closed} onChange={(event) => form.setData('is_closed', event.target.checked)} />Period is closed</label>
                <div><Button type="submit" disabled={form.processing}>Update period</Button></div>
            </form>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) { return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>; }
