import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleLinks, moduleBreadcrumbs } from '@/lib/page-navigation';

type Props = {
    units: string[];
    leaveType: {
        id: string;
        name: string;
        code: string;
        unit: string;
        requires_allocation: boolean;
        is_paid: boolean;
        requires_approval: boolean;
        allow_negative_balance: boolean;
        max_consecutive_days: string;
        color: string;
    };
};

export default function HrLeaveTypeEdit({ units, leaveType }: Props) {
    const form = useForm(leaveType);

    return (
        <AppLayout breadcrumbs={moduleBreadcrumbs(companyModuleLinks.hr, { title: 'Leave', href: '/company/hr/leave' }, { title: 'Edit type', href: `/company/hr/leave/types/${leaveType.id}/edit` })}>
            <Head title="Edit leave type" />
            <div className="flex items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">Edit leave type</h1><p className="text-sm text-muted-foreground">Adjust allocation, approval, and balance settings for this leave type.</p></div><BackLinkAction href="/company/hr/leave" label="Back to leave" variant="ghost" /></div>
            <form className="mt-6 grid gap-6" onSubmit={(event) => { event.preventDefault(); form.put(`/company/hr/leave/types/${leaveType.id}`); }}>
                <div className="grid gap-6 md:grid-cols-2">
                    <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                    <Field label="Code" error={form.errors.code}><Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></Field>
                    <Field label="Unit" error={form.errors.unit}><select className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.unit} onChange={(event) => form.setData('unit', event.target.value)}>{units.map((unit) => <option key={unit} value={unit}>{unit}</option>)}</select></Field>
                    <Field label="Color" error={form.errors.color}><Input value={form.data.color} onChange={(event) => form.setData('color', event.target.value)} placeholder="#2563eb" /></Field>
                    <Field label="Max consecutive days" error={form.errors.max_consecutive_days}><Input value={form.data.max_consecutive_days} onChange={(event) => form.setData('max_consecutive_days', event.target.value)} /></Field>
                </div>
                <div className="grid gap-3 md:grid-cols-2">
                    <CheckboxRow label="Requires allocation" checked={form.data.requires_allocation} onChange={(checked) => form.setData('requires_allocation', checked)} />
                    <CheckboxRow label="Paid leave" checked={form.data.is_paid} onChange={(checked) => form.setData('is_paid', checked)} />
                    <CheckboxRow label="Requires approval" checked={form.data.requires_approval} onChange={(checked) => form.setData('requires_approval', checked)} />
                    <CheckboxRow label="Allow negative balance" checked={form.data.allow_negative_balance} onChange={(checked) => form.setData('allow_negative_balance', checked)} />
                </div>
                <div><Button type="submit" disabled={form.processing}>Update leave type</Button></div>
            </form>
        </AppLayout>
    );
}

function CheckboxRow({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return <label className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />{label}</label>;
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>;
}
