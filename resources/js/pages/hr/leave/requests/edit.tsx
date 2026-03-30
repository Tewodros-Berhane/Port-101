import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleLinks, moduleBreadcrumbs } from '@/lib/page-navigation';

type EmployeeOption = { id: string; name: string; employee_number?: string | null };
type LeaveTypeOption = { id: string; name: string; unit: string; requires_allocation: boolean; requires_approval: boolean };
type LeavePeriodOption = { id: string; name: string; start_date?: string | null; end_date?: string | null; is_closed: boolean };

type Props = {
    employeeOptions: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
    leavePeriods: LeavePeriodOption[];
    requestRecord: {
        id: string;
        employee_id: string;
        leave_type_id: string;
        leave_period_id: string;
        from_date: string;
        to_date: string;
        duration_amount: string;
        is_half_day: boolean;
        reason: string;
        action: string;
    };
};

export default function HrLeaveRequestEdit({ employeeOptions, leaveTypes, leavePeriods, requestRecord }: Props) {
    const form = useForm(requestRecord);

    return (
        <AppLayout breadcrumbs={moduleBreadcrumbs(companyModuleLinks.hr, { title: 'Leave', href: '/company/hr/leave' }, { title: 'Edit request', href: `/company/hr/leave/requests/${requestRecord.id}/edit` })}>
            <Head title="Edit leave request" />

            <div className="flex items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit leave request</h1>
                    <p className="text-sm text-muted-foreground">Adjust the draft request before submitting it for approval.</p>
                </div>
                <BackLinkAction href="/company/hr/leave" label="Back to leave" variant="ghost" />
            </div>

            <form className="mt-6 grid gap-6" onSubmit={(event) => { event.preventDefault(); form.put(`/company/hr/leave/requests/${requestRecord.id}`); }}>
                <div className="grid gap-6 md:grid-cols-2">
                    <Field label="Employee" error={form.errors.employee_id}>
                        <select className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)} disabled>
                            {employeeOptions.map((employee) => <option key={employee.id} value={employee.id}>{employee.name}{employee.employee_number ? ` (${employee.employee_number})` : ''}</option>)}
                        </select>
                    </Field>
                    <Field label="Leave type" error={form.errors.leave_type_id}>
                        <select className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.leave_type_id} onChange={(event) => form.setData('leave_type_id', event.target.value)} required>
                            {leaveTypes.map((leaveType) => <option key={leaveType.id} value={leaveType.id}>{leaveType.name} ({leaveType.unit})</option>)}
                        </select>
                    </Field>
                    <Field label="Leave period" error={form.errors.leave_period_id}>
                        <select className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.leave_period_id} onChange={(event) => form.setData('leave_period_id', event.target.value)}>
                            <option value="">Auto-detect from dates</option>
                            {leavePeriods.map((leavePeriod) => <option key={leavePeriod.id} value={leavePeriod.id}>{leavePeriod.name}{leavePeriod.is_closed ? ' (closed)' : ''}</option>)}
                        </select>
                    </Field>
                    <Field label="Half day" error={form.errors.is_half_day}>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.is_half_day} onChange={(event) => form.setData('is_half_day', event.target.checked)} />
                            Request a half day
                        </label>
                    </Field>
                    <Field label="From date" error={form.errors.from_date}>
                        <Input type="date" value={form.data.from_date} onChange={(event) => form.setData('from_date', event.target.value)} required />
                    </Field>
                    <Field label="To date" error={form.errors.to_date}>
                        <Input type="date" value={form.data.to_date} onChange={(event) => form.setData('to_date', event.target.value)} required />
                    </Field>
                    <Field label="Duration amount" error={form.errors.duration_amount}>
                        <Input value={form.data.duration_amount} onChange={(event) => form.setData('duration_amount', event.target.value)} placeholder="Needed for hours-based leave" />
                    </Field>
                </div>

                <Field label="Reason" error={form.errors.reason}>
                    <textarea className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                </Field>

                <div className="flex flex-wrap gap-3">
                    <Button type="button" variant="outline" onClick={() => { form.setData('action', 'save'); form.put(`/company/hr/leave/requests/${requestRecord.id}`); }} disabled={form.processing}>Save changes</Button>
                    <Button type="submit" onClick={() => form.setData('action', 'submit')} disabled={form.processing}>Save and submit</Button>
                </div>
            </form>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>;
}
