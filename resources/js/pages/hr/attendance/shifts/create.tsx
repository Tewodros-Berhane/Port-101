import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type ShiftForm = { name: string; code: string; start_time: string; end_time: string; grace_minutes: string; auto_attendance_enabled: boolean };

type Props = { form: ShiftForm };

export default function HrShiftCreate({ form: defaults }: Props) {
    const form = useForm(defaults);
    return (
        <AppLayout breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.hr, { title: 'Attendance', href: '/company/hr/attendance' }, { title: 'New Shift', href: '/company/hr/attendance/shifts/create' })}>
            <Head title="New shift" />
            <div className="max-w-2xl space-y-6">
                <div className="flex items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">New shift</h1><p className="text-sm text-muted-foreground">Define the expected shift window for attendance tracking.</p></div><BackLinkAction href="/company/hr/attendance" label="Back to attendance" variant="outline" /></div>
                <form className="space-y-4 rounded-xl border p-4" onSubmit={(event) => { event.preventDefault(); form.post('/company/hr/attendance/shifts'); }}>
                    <Field label="Name" error={form.errors.name}><input className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                    <Field label="Code" error={form.errors.code}><input className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></Field>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Start" error={form.errors.start_time}><input type="time" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.start_time} onChange={(event) => form.setData('start_time', event.target.value)} /></Field>
                        <Field label="End" error={form.errors.end_time}><input type="time" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.end_time} onChange={(event) => form.setData('end_time', event.target.value)} /></Field>
                        <Field label="Grace minutes" error={form.errors.grace_minutes}><input type="number" min="0" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.grace_minutes} onChange={(event) => form.setData('grace_minutes', event.target.value)} /></Field>
                    </div>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.auto_attendance_enabled} onChange={(event) => form.setData('auto_attendance_enabled', event.target.checked)} />Enable auto-attendance processing</label>
                    <Button type="submit">Create shift</Button>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) { return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>; }
