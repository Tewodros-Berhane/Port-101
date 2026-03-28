import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type ShiftForm = { id: string; name: string; code: string; start_time: string; end_time: string; grace_minutes: string; auto_attendance_enabled: boolean };

type Props = { shift: ShiftForm };

export default function HrShiftEdit({ shift }: Props) {
    const form = useForm(shift);
    return (
        <AppLayout breadcrumbs={[{ title: 'Company', href: '/company/dashboard' }, { title: 'HR', href: '/company/hr' }, { title: 'Attendance', href: '/company/hr/attendance' }, { title: 'Edit Shift', href: `/company/hr/attendance/shifts/${shift.id}/edit` }]}>
            <Head title="Edit shift" />
            <div className="max-w-2xl space-y-6">
                <div className="flex items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">Edit shift</h1><p className="text-sm text-muted-foreground">Update the shift schedule and grace policy.</p></div><Button variant="outline" asChild><Link href="/company/hr/attendance">Back to attendance</Link></Button></div>
                <form className="space-y-4 rounded-xl border p-4" onSubmit={(event) => { event.preventDefault(); form.put(`/company/hr/attendance/shifts/${shift.id}`); }}>
                    <Field label="Name" error={form.errors.name}><input className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                    <Field label="Code" error={form.errors.code}><input className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></Field>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Start" error={form.errors.start_time}><input type="time" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.start_time} onChange={(event) => form.setData('start_time', event.target.value)} /></Field>
                        <Field label="End" error={form.errors.end_time}><input type="time" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.end_time} onChange={(event) => form.setData('end_time', event.target.value)} /></Field>
                        <Field label="Grace minutes" error={form.errors.grace_minutes}><input type="number" min="0" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.grace_minutes} onChange={(event) => form.setData('grace_minutes', event.target.value)} /></Field>
                    </div>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.auto_attendance_enabled} onChange={(event) => form.setData('auto_attendance_enabled', event.target.checked)} />Enable auto-attendance processing</label>
                    <Button type="submit">Update shift</Button>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) { return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>; }
