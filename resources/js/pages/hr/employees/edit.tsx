import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Option = {
    id: string;
    name: string;
    code?: string | null;
    email?: string;
    employee_number?: string;
    slug?: string;
};

type Props = {
    employee: Record<string, string | boolean>;
    employeeId: string;
    statuses: string[];
    employmentTypes: string[];
    departments: Option[];
    designations: Option[];
    managers: Option[];
    companyUsers: Option[];
    accessRoles: Option[];
};

export default function HrEmployeeEdit({ employee, employeeId, statuses, employmentTypes, departments, designations, managers, companyUsers, accessRoles }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('hr.employees.manage');
    const canManageAccess = hasPermission('hr.employee_access.manage');
    const form = useForm(employee);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'HR', href: '/company/hr' },
                { title: 'Employees', href: '/company/hr/employees' },
                { title: 'Edit', href: `/company/hr/employees/${employeeId}/edit` },
            ]}
        >
            <Head title="Edit employee" />

            <div className="flex items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit employee</h1>
                    <p className="text-sm text-muted-foreground">
                        Update employee profile, approver defaults, and optional system access.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href={`/company/hr/employees/${employeeId}`}>Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/hr/employees/${employeeId}`);
                }}
            >
                <div className="grid gap-6 md:grid-cols-2">
                    <Field label="First name" error={form.errors.first_name}><Input value={form.data.first_name} onChange={(event) => form.setData('first_name', event.target.value)} required /></Field>
                    <Field label="Last name" error={form.errors.last_name}><Input value={form.data.last_name} onChange={(event) => form.setData('last_name', event.target.value)} required /></Field>
                    <Field label="Employee number" error={form.errors.employee_number}><Input value={form.data.employee_number} onChange={(event) => form.setData('employee_number', event.target.value)} /></Field>
                    <Field label="Linked existing user" error={form.errors.user_id}>
                        <select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.user_id} onChange={(event) => form.setData('user_id', event.target.value)}>
                            <option value="">No linked user</option>
                            {companyUsers.map((user) => <option key={user.id} value={user.id}>{user.name}{user.email ? ` (${user.email})` : ''}</option>)}
                        </select>
                    </Field>
                    <Field label="Status" error={form.errors.employment_status}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.employment_status} onChange={(event) => form.setData('employment_status', event.target.value)}>{statuses.map((status) => <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>)}</select></Field>
                    <Field label="Employment type" error={form.errors.employment_type}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.employment_type} onChange={(event) => form.setData('employment_type', event.target.value)}>{employmentTypes.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}</select></Field>
                    <Field label="Department" error={form.errors.department_id}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.department_id} onChange={(event) => form.setData('department_id', event.target.value)}><option value="">No department</option>{departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}</select></Field>
                    <Field label="New department" error={form.errors.department_name}><Input value={form.data.department_name} onChange={(event) => form.setData('department_name', event.target.value)} placeholder="Create by name if not listed" /></Field>
                    <Field label="Designation" error={form.errors.designation_id}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.designation_id} onChange={(event) => form.setData('designation_id', event.target.value)}><option value="">No designation</option>{designations.map((designation) => <option key={designation.id} value={designation.id}>{designation.name}</option>)}</select></Field>
                    <Field label="New designation" error={form.errors.designation_name}><Input value={form.data.designation_name} onChange={(event) => form.setData('designation_name', event.target.value)} placeholder="Create by name if not listed" /></Field>
                    <Field label="Work email" error={form.errors.work_email}><Input type="email" value={form.data.work_email} onChange={(event) => form.setData('work_email', event.target.value)} /></Field>
                    <Field label="Work phone" error={form.errors.work_phone}><Input value={form.data.work_phone} onChange={(event) => form.setData('work_phone', event.target.value)} /></Field>
                    <Field label="Personal email" error={form.errors.personal_email}><Input type="email" value={form.data.personal_email} onChange={(event) => form.setData('personal_email', event.target.value)} /></Field>
                    <Field label="Personal phone" error={form.errors.personal_phone}><Input value={form.data.personal_phone} onChange={(event) => form.setData('personal_phone', event.target.value)} /></Field>
                    <Field label="Hire date" error={form.errors.hire_date}><Input type="date" value={form.data.hire_date} onChange={(event) => form.setData('hire_date', event.target.value)} /></Field>
                    <Field label="Date of birth" error={form.errors.date_of_birth}><Input type="date" value={form.data.date_of_birth} onChange={(event) => form.setData('date_of_birth', event.target.value)} /></Field>
                    <Field label="Manager" error={form.errors.manager_employee_id}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.manager_employee_id} onChange={(event) => form.setData('manager_employee_id', event.target.value)}><option value="">No manager</option>{managers.map((manager) => <option key={manager.id} value={manager.id}>{manager.name}{manager.employee_number ? ` (${manager.employee_number})` : ''}</option>)}</select></Field>
                    <Field label="Work location" error={form.errors.work_location}><Input value={form.data.work_location} onChange={(event) => form.setData('work_location', event.target.value)} /></Field>
                    <Field label="Leave approver" error={form.errors.leave_approver_user_id}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.leave_approver_user_id} onChange={(event) => form.setData('leave_approver_user_id', event.target.value)}><option value="">No approver</option>{companyUsers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></Field>
                    <Field label="Attendance approver" error={form.errors.attendance_approver_user_id}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.attendance_approver_user_id} onChange={(event) => form.setData('attendance_approver_user_id', event.target.value)}><option value="">No approver</option>{companyUsers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></Field>
                    <Field label="Reimbursement approver" error={form.errors.reimbursement_approver_user_id}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.reimbursement_approver_user_id} onChange={(event) => form.setData('reimbursement_approver_user_id', event.target.value)}><option value="">No approver</option>{companyUsers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></Field>
                    <Field label="Timezone" error={form.errors.timezone}><Input value={form.data.timezone} onChange={(event) => form.setData('timezone', event.target.value)} /></Field>
                    <Field label="Country code" error={form.errors.country_code}><Input value={form.data.country_code} onChange={(event) => form.setData('country_code', event.target.value)} /></Field>
                    <Field label="Bank reference" error={form.errors.bank_account_reference}><Input value={form.data.bank_account_reference} onChange={(event) => form.setData('bank_account_reference', event.target.value)} /></Field>
                    <Field label="Emergency contact" error={form.errors.emergency_contact_name}><Input value={form.data.emergency_contact_name} onChange={(event) => form.setData('emergency_contact_name', event.target.value)} /></Field>
                    <Field label="Emergency phone" error={form.errors.emergency_contact_phone}><Input value={form.data.emergency_contact_phone} onChange={(event) => form.setData('emergency_contact_phone', event.target.value)} /></Field>
                    <Field label="Termination date" error={form.errors.termination_date}><Input type="date" value={form.data.termination_date} onChange={(event) => form.setData('termination_date', event.target.value)} /></Field>
                </div>

                {canManageAccess && (
                    <div className="rounded-xl border p-4">
                        <div className="flex items-start gap-3">
                            <Checkbox
                                checked={Boolean(form.data.requires_system_access)}
                                onCheckedChange={(value) => {
                                    const checked = Boolean(value);
                                    form.setData((data) => ({
                                        ...data,
                                        requires_system_access: checked,
                                        system_role_id: checked ? data.system_role_id : '',
                                        login_email: checked ? data.login_email : '',
                                    }));
                                }}
                            />
                            <div className="space-y-1">
                                <Label className="text-sm font-medium">System access required</Label>
                                <p className="text-sm text-muted-foreground">
                                    Use this when the employee needs a company role and app login. If no linked user is selected, saving will create or refresh a pending invite.
                                </p>
                                <InputError message={form.errors.requires_system_access} />
                            </div>
                        </div>

                        {Boolean(form.data.requires_system_access) && (
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <Field label="System role" error={form.errors.system_role_id}>
                                    <select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={form.data.system_role_id} onChange={(event) => form.setData('system_role_id', event.target.value)}>
                                        <option value="">Select role</option>
                                        {accessRoles.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
                                    </select>
                                </Field>
                                <Field label="Login email" error={form.errors.login_email}>
                                    <Input
                                        type="email"
                                        value={form.data.login_email}
                                        onChange={(event) => form.setData('login_email', event.target.value)}
                                        placeholder="Required when no linked user is selected"
                                    />
                                </Field>
                            </div>
                        )}
                    </div>
                )}

                <Field label="Notes" error={form.errors.notes}>
                    <textarea className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </Field>

                {canManage && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={form.processing}>Save changes</Button>
                        <Button type="button" variant="destructive" onClick={() => form.delete(`/company/hr/employees/${employeeId}`)} disabled={form.processing}>Delete</Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
