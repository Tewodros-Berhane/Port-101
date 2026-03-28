import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type EmployeeOption = { id: string; name: string; employee_number?: string | null };
type LeaveTypeOption = { id: string; name: string; unit: string };
type LeavePeriodOption = { id: string; name: string };

type Props = { employeeOptions: EmployeeOption[]; leaveTypes: LeaveTypeOption[]; leavePeriods: LeavePeriodOption[]; form: { employee_id: string; leave_type_id: string; leave_period_id: string; allocated_amount: string; used_amount: string; carry_forward_amount: string; expires_at: string; notes: string; } };

export default function HrLeaveAllocationCreate({ employeeOptions, leaveTypes, leavePeriods, form: initialForm }: Props) {
    const form = useForm(initialForm);

    return (
        <AppLayout breadcrumbs={[{ title: 'HR', href: '/company/hr' }, { title: 'Leave', href: '/company/hr/leave' }, { title: 'Create allocation', href: '/company/hr/leave/allocations/create' }]}>
            <Head title="New leave allocation" />
            <div className="flex items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">New leave allocation</h1><p className="text-sm text-muted-foreground">Assign leave entitlement for an employee and leave period.</p></div><Button variant="ghost" asChild><Link href="/company/hr/leave">Back</Link></Button></div>
            <form className="mt-6 grid gap-6" onSubmit={(event) => { event.preventDefault(); form.post('/company/hr/leave/allocations'); }}>
                <div className="grid gap-6 md:grid-cols-2">
                    <Field label="Employee" error={form.errors.employee_id}><select className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)} required><option value="">Select employee</option>{employeeOptions.map((employee) => <option key={employee.id} value={employee.id}>{employee.name}{employee.employee_number ? ` (${employee.employee_number})` : ''}</option>)}</select></Field>
                    <Field label="Leave type" error={form.errors.leave_type_id}><select className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.leave_type_id} onChange={(event) => form.setData('leave_type_id', event.target.value)} required><option value="">Select leave type</option>{leaveTypes.map((leaveType) => <option key={leaveType.id} value={leaveType.id}>{leaveType.name} ({leaveType.unit})</option>)}</select></Field>
                    <Field label="Leave period" error={form.errors.leave_period_id}><select className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.leave_period_id} onChange={(event) => form.setData('leave_period_id', event.target.value)} required><option value="">Select leave period</option>{leavePeriods.map((leavePeriod) => <option key={leavePeriod.id} value={leavePeriod.id}>{leavePeriod.name}</option>)}</select></Field>
                    <Field label="Allocated amount" error={form.errors.allocated_amount}><Input value={form.data.allocated_amount} onChange={(event) => form.setData('allocated_amount', event.target.value)} required /></Field>
                    <Field label="Used amount" error={form.errors.used_amount}><Input value={form.data.used_amount} onChange={(event) => form.setData('used_amount', event.target.value)} /></Field>
                    <Field label="Carry forward" error={form.errors.carry_forward_amount}><Input value={form.data.carry_forward_amount} onChange={(event) => form.setData('carry_forward_amount', event.target.value)} /></Field>
                    <Field label="Expiry date" error={form.errors.expires_at}><Input type="date" value={form.data.expires_at} onChange={(event) => form.setData('expires_at', event.target.value)} /></Field>
                </div>
                <Field label="Notes" error={form.errors.notes}><textarea className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></Field>
                <div><Button type="submit" disabled={form.processing}>Create allocation</Button></div>
            </form>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) { return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>; }
