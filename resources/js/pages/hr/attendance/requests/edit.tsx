import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type EmployeeOption = { id: string; name: string; employee_number?: string | null };

type Props = {
    employeeOptions: EmployeeOption[];
    statuses: string[];
    requestRecord: {
        id: string;
        employee_id: string;
        from_date: string;
        to_date: string;
        requested_status: string;
        requested_check_in_at: string;
        requested_check_out_at: string;
        reason: string;
        action: string;
    };
};

const labelize = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

export default function HrAttendanceRequestEdit({ employeeOptions, statuses, requestRecord }: Props) {
    const form = useForm(requestRecord);

    return (
        <AppLayout breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.hr, { title: 'Attendance', href: '/company/hr/attendance' }, { title: 'Edit Correction', href: `/company/hr/attendance/requests/${requestRecord.id}/edit` })}>
            <Head title="Edit attendance correction" />
            <div className="max-w-3xl space-y-6">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Edit attendance correction</h1>
                        <p className="text-sm text-muted-foreground">Adjust the correction details before submitting them for approval.</p>
                    </div>
                    <BackLinkAction href="/company/hr/attendance" label="Back to attendance" variant="outline" />
                </div>

                <form className="space-y-4 rounded-xl border p-4" onSubmit={(event) => { event.preventDefault(); form.put(`/company/hr/attendance/requests/${requestRecord.id}`); }}>
                    <Field label="Employee" error={form.errors.employee_id}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)}><option value="">Select employee</option>{employeeOptions.map((employee) => <option key={employee.id} value={employee.id}>{employee.name}{employee.employee_number ? ` (${employee.employee_number})` : ''}</option>)}</select></Field>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="From date" error={form.errors.from_date}><input type="date" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.from_date} onChange={(event) => form.setData('from_date', event.target.value)} /></Field>
                        <Field label="To date" error={form.errors.to_date}><input type="date" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.to_date} onChange={(event) => form.setData('to_date', event.target.value)} /></Field>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Requested status" error={form.errors.requested_status}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.requested_status} onChange={(event) => form.setData('requested_status', event.target.value)}>{statuses.map((status) => <option key={status} value={status}>{labelize(status)}</option>)}</select></Field>
                        <Field label="Check in" error={form.errors.requested_check_in_at}><input type="time" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.requested_check_in_at} onChange={(event) => form.setData('requested_check_in_at', event.target.value)} /></Field>
                        <Field label="Check out" error={form.errors.requested_check_out_at}><input type="time" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.requested_check_out_at} onChange={(event) => form.setData('requested_check_out_at', event.target.value)} /></Field>
                    </div>
                    <Field label="Reason" error={form.errors.reason}><textarea className="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></Field>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant={form.data.action === 'save' ? 'default' : 'outline'} onClick={() => form.setData('action', 'save')}>Save draft</Button>
                        <Button type="button" variant={form.data.action === 'submit' ? 'default' : 'outline'} onClick={() => form.setData('action', 'submit')}>Submit now</Button>
                        <Button type="submit">Update</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>;
}
