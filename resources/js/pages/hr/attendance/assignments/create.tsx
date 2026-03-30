import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type EmployeeOption = { id: string; name: string; employee_number?: string | null };
type ShiftOption = { id: string; name: string; code?: string | null; start_time: string; end_time: string };

type Props = { employeeOptions: EmployeeOption[]; shiftOptions: ShiftOption[]; form: { employee_id: string; shift_id: string; from_date: string; to_date: string } };

export default function HrShiftAssignmentCreate({ employeeOptions, shiftOptions, form: defaults }: Props) {
    const form = useForm(defaults);
    return (
        <AppLayout breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.hr, { title: 'Attendance', href: '/company/hr/attendance' }, { title: 'New Assignment', href: '/company/hr/attendance/assignments/create' })}>
            <Head title="New shift assignment" />
            <div className="max-w-2xl space-y-6">
                <div className="flex items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">New shift assignment</h1><p className="text-sm text-muted-foreground">Assign an employee to a defined attendance shift.</p></div><BackLinkAction href="/company/hr/attendance" label="Back to attendance" variant="outline" /></div>
                <form className="space-y-4 rounded-xl border p-4" onSubmit={(event) => { event.preventDefault(); form.post('/company/hr/attendance/assignments'); }}>
                    <Field label="Employee" error={form.errors.employee_id}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)}><option value="">Select employee</option>{employeeOptions.map((employee) => <option key={employee.id} value={employee.id}>{employee.name}{employee.employee_number ? ` (${employee.employee_number})` : ''}</option>)}</select></Field>
                    <Field label="Shift" error={form.errors.shift_id}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.shift_id} onChange={(event) => form.setData('shift_id', event.target.value)}><option value="">Select shift</option>{shiftOptions.map((shift) => <option key={shift.id} value={shift.id}>{shift.name} ({shift.start_time}-{shift.end_time})</option>)}</select></Field>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="From date" error={form.errors.from_date}><input type="date" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.from_date} onChange={(event) => form.setData('from_date', event.target.value)} /></Field>
                        <Field label="To date" error={form.errors.to_date}><input type="date" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.to_date} onChange={(event) => form.setData('to_date', event.target.value)} /></Field>
                    </div>
                    <Button type="submit">Create assignment</Button>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) { return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>; }
