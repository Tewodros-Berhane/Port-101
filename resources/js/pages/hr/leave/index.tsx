import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';

type Summary = {
    open_requests: number;
    pending_my_approvals: number;
    approved_30d: number;
    allocations: number;
    available_days: number;
    booked_days_30d: number;
};

type LeaveTypeRow = {
    id: string;
    name: string;
    code?: string | null;
    unit: string;
    requires_allocation: boolean;
    requires_approval: boolean;
    is_paid: boolean;
    allow_negative_balance: boolean;
    max_consecutive_days?: number | null;
    color?: string | null;
};

type LeavePeriodRow = {
    id: string;
    name: string;
    start_date?: string | null;
    end_date?: string | null;
    is_closed: boolean;
};

type AllocationRow = {
    id: string;
    employee_name?: string | null;
    employee_number?: string | null;
    leave_type_name?: string | null;
    leave_type_unit?: string | null;
    leave_period_name?: string | null;
    allocated_amount: number;
    used_amount: number;
    balance_amount: number;
    carry_forward_amount: number;
    expires_at?: string | null;
    can_edit: boolean;
};

type RequestRow = {
    id: string;
    request_number: string;
    status: string;
    employee_name?: string | null;
    employee_number?: string | null;
    leave_type_name?: string | null;
    leave_type_unit?: string | null;
    leave_period_name?: string | null;
    approver_name?: string | null;
    approved_by_name?: string | null;
    rejected_by_name?: string | null;
    cancelled_by_name?: string | null;
    from_date?: string | null;
    to_date?: string | null;
    duration_amount: number;
    is_half_day: boolean;
    reason?: string | null;
    decision_notes?: string | null;
    submitted_at?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    cancelled_at?: string | null;
    can_edit: boolean;
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_cancel: boolean;
};

type EmployeeOption = {
    id: string;
    name: string;
    employee_number?: string | null;
    linked_user_name?: string | null;
};

type Props = {
    summary: Summary;
    filters: {
        status: string;
        leave_type_id: string;
        leave_period_id: string;
        employee_id: string;
    };
    statuses: string[];
    leaveTypes: LeaveTypeRow[];
    leavePeriods: LeavePeriodRow[];
    employeeOptions: EmployeeOption[];
    linkedEmployeeId?: string | null;
    allocations: AllocationRow[];
    requests: {
        data: RequestRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    abilities: {
        can_create_request: boolean;
        can_manage_leave: boolean;
        can_approve_leave: boolean;
    };
};

const labelize = (value: string) =>
    value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

export default function HrLeaveIndex({
    summary,
    filters,
    statuses,
    leaveTypes,
    leavePeriods,
    employeeOptions,
    linkedEmployeeId,
    allocations,
    requests,
    abilities,
}: Props) {
    const form = useForm(filters);

    const rejectWithReason = (requestId: string) => {
        const reason = window.prompt('Rejection reason (optional)', '');

        if (reason === null) {
            return;
        }

        router.post(`/company/hr/leave/requests/${requestId}/reject`, { reason }, { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'HR', href: '/company/hr' },
                { title: 'Leave', href: '/company/hr/leave' },
            ]}
        >
            <Head title="Leave workspace" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Leave workspace</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage leave policies, allocations, and approval-backed leave requests.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/company/hr">HR dashboard</Link>
                        </Button>
                        {abilities.can_manage_leave && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href="/company/hr/leave/types/create">New leave type</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/company/hr/leave/periods/create">New leave period</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/company/hr/leave/allocations/create">New allocation</Link>
                                </Button>
                            </>
                        )}
                        {abilities.can_create_request && (
                            <Button asChild>
                                <Link href="/company/hr/leave/requests/create">New leave request</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <Metric label="Open requests" value={summary.open_requests} />
                    <Metric label="My approvals" value={summary.pending_my_approvals} />
                    <Metric label="Approved 30d" value={summary.approved_30d} />
                    <Metric label="Allocations" value={summary.allocations} />
                    <Metric label="Available days" value={summary.available_days.toFixed(2)} />
                    <Metric label="Booked 30d" value={summary.booked_days_30d.toFixed(2)} />
                </div>

                <form
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get('/company/hr/leave', { preserveState: true, replace: true });
                    }}
                >
                    <Field label="Status" error={form.errors.status}>
                        <select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {labelize(status)}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Leave type" error={form.errors.leave_type_id}>
                        <select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.leave_type_id} onChange={(event) => form.setData('leave_type_id', event.target.value)}>
                            <option value="">All leave types</option>
                            {leaveTypes.map((leaveType) => (
                                <option key={leaveType.id} value={leaveType.id}>
                                    {leaveType.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Leave period" error={form.errors.leave_period_id}>
                        <select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.leave_period_id} onChange={(event) => form.setData('leave_period_id', event.target.value)}>
                            <option value="">All leave periods</option>
                            {leavePeriods.map((leavePeriod) => (
                                <option key={leavePeriod.id} value={leavePeriod.id}>
                                    {leavePeriod.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Employee" error={form.errors.employee_id}>
                        <select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)}>
                            <option value="">All employees</option>
                            {employeeOptions.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name}
                                    {employee.employee_number ? ` (${employee.employee_number})` : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button type="submit">Apply</Button>
                        <Button type="button" variant="ghost" onClick={() => router.get('/company/hr/leave')}>Reset</Button>
                    </div>
                </form>

                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-2">
                        <div>
                            <h2 className="text-sm font-semibold">Leave requests</h2>
                            <p className="text-xs text-muted-foreground">
                                Track approvals, balances, and leave decisions in one queue.
                            </p>
                        </div>
                        {linkedEmployeeId && !form.data.employee_id && (
                            <Button variant="ghost" type="button" onClick={() => router.get('/company/hr/leave', { ...filters, employee_id: linkedEmployeeId })}>
                                My requests
                            </Button>
                        )}
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[1180px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Request</th>
                                    <th className="px-3 py-2 font-medium">Employee</th>
                                    <th className="px-3 py-2 font-medium">Leave type</th>
                                    <th className="px-3 py-2 font-medium">Dates</th>
                                    <th className="px-3 py-2 font-medium">Duration</th>
                                    <th className="px-3 py-2 font-medium">Approver</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {requests.data.length === 0 && (
                                    <tr>
                                        <td className="px-3 py-6 text-center text-muted-foreground" colSpan={8}>
                                            No leave requests found.
                                        </td>
                                    </tr>
                                )}
                                {requests.data.map((requestRecord) => (
                                    <tr key={requestRecord.id}>
                                        <td className="px-3 py-2 align-top">
                                            <div className="font-medium">{requestRecord.request_number}</div>
                                            {requestRecord.leave_period_name && (
                                                <div className="text-xs text-muted-foreground">{requestRecord.leave_period_name}</div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div>{requestRecord.employee_name ?? '-'}</div>
                                            {requestRecord.employee_number && (
                                                <div className="text-xs text-muted-foreground">{requestRecord.employee_number}</div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div>{requestRecord.leave_type_name ?? '-'}</div>
                                            <div className="text-xs text-muted-foreground">{requestRecord.leave_type_unit ?? '-'}</div>
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div>{requestRecord.from_date ?? '-'} to {requestRecord.to_date ?? '-'}</div>
                                            {requestRecord.is_half_day && <div className="text-xs text-muted-foreground">Half day</div>}
                                        </td>
                                        <td className="px-3 py-2 align-top">{requestRecord.duration_amount.toFixed(2)}</td>
                                        <td className="px-3 py-2 align-top">{requestRecord.approver_name ?? '-'}</td>
                                        <td className="px-3 py-2 align-top">
                                            <div className="capitalize">{labelize(requestRecord.status)}</div>
                                            {requestRecord.decision_notes && (
                                                <div className="mt-1 max-w-xs truncate text-xs text-muted-foreground" title={requestRecord.decision_notes}>
                                                    {requestRecord.decision_notes}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div className="flex flex-wrap gap-2">
                                                {requestRecord.can_edit && (
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/company/hr/leave/requests/${requestRecord.id}/edit`}>Edit</Link>
                                                    </Button>
                                                )}
                                                {requestRecord.can_submit && (
                                                    <Button variant="outline" size="sm" type="button" onClick={() => router.post(`/company/hr/leave/requests/${requestRecord.id}/submit`, {}, { preserveScroll: true })}>
                                                        Submit
                                                    </Button>
                                                )}
                                                {requestRecord.can_approve && (
                                                    <Button size="sm" type="button" onClick={() => router.post(`/company/hr/leave/requests/${requestRecord.id}/approve`, {}, { preserveScroll: true })}>
                                                        Approve
                                                    </Button>
                                                )}
                                                {requestRecord.can_reject && (
                                                    <Button variant="outline" size="sm" type="button" onClick={() => rejectWithReason(requestRecord.id)}>
                                                        Reject
                                                    </Button>
                                                )}
                                                {requestRecord.can_cancel && (
                                                    <Button variant="ghost" size="sm" type="button" onClick={() => router.post(`/company/hr/leave/requests/${requestRecord.id}/cancel`, {}, { preserveScroll: true })}>
                                                        Cancel
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {requests.links.length > 3 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {requests.links.map((link, index) => (
                                <Button
                                    key={`${link.label}-${index}`}
                                    type="button"
                                    variant={link.active ? 'default' : 'outline'}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold">Leave allocations</h2>
                            {abilities.can_manage_leave && (
                                <Button variant="ghost" asChild>
                                    <Link href="/company/hr/leave/allocations/create">New allocation</Link>
                                </Button>
                            )}
                        </div>
                        <div className="mt-4 overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[860px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Employee</th>
                                        <th className="px-3 py-2 font-medium">Leave type</th>
                                        <th className="px-3 py-2 font-medium">Period</th>
                                        <th className="px-3 py-2 font-medium">Allocated</th>
                                        <th className="px-3 py-2 font-medium">Used</th>
                                        <th className="px-3 py-2 font-medium">Balance</th>
                                        <th className="px-3 py-2 font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {allocations.length === 0 && (
                                        <tr>
                                            <td className="px-3 py-6 text-center text-muted-foreground" colSpan={7}>
                                                No allocations found.
                                            </td>
                                        </tr>
                                    )}
                                    {allocations.map((allocation) => (
                                        <tr key={allocation.id}>
                                            <td className="px-3 py-2">{allocation.employee_name ?? allocation.employee_number ?? '-'}</td>
                                            <td className="px-3 py-2">{allocation.leave_type_name ?? '-'}</td>
                                            <td className="px-3 py-2">{allocation.leave_period_name ?? '-'}</td>
                                            <td className="px-3 py-2">{allocation.allocated_amount.toFixed(2)}</td>
                                            <td className="px-3 py-2">{allocation.used_amount.toFixed(2)}</td>
                                            <td className="px-3 py-2 font-medium">{allocation.balance_amount.toFixed(2)}</td>
                                            <td className="px-3 py-2">
                                                {allocation.can_edit && (
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/company/hr/leave/allocations/${allocation.id}/edit`}>Edit</Link>
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="grid gap-4">
                        <div className="rounded-xl border p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-sm font-semibold">Leave types</h2>
                                {abilities.can_manage_leave && (
                                    <Button variant="ghost" asChild>
                                        <Link href="/company/hr/leave/types/create">New type</Link>
                                    </Button>
                                )}
                            </div>
                            <div className="mt-4 overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[720px] text-sm">
                                    <thead className="bg-muted/60 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Name</th>
                                            <th className="px-3 py-2 font-medium">Unit</th>
                                            <th className="px-3 py-2 font-medium">Allocation</th>
                                            <th className="px-3 py-2 font-medium">Approval</th>
                                            <th className="px-3 py-2 font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {leaveTypes.map((leaveType) => (
                                            <tr key={leaveType.id}>
                                                <td className="px-3 py-2">{leaveType.name}</td>
                                                <td className="px-3 py-2 capitalize">{leaveType.unit}</td>
                                                <td className="px-3 py-2">{leaveType.requires_allocation ? 'Required' : 'Not required'}</td>
                                                <td className="px-3 py-2">{leaveType.requires_approval ? 'Required' : 'Auto'}</td>
                                                <td className="px-3 py-2">
                                                    {abilities.can_manage_leave && (
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={`/company/hr/leave/types/${leaveType.id}/edit`}>Edit</Link>
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="rounded-xl border p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-sm font-semibold">Leave periods</h2>
                                {abilities.can_manage_leave && (
                                    <Button variant="ghost" asChild>
                                        <Link href="/company/hr/leave/periods/create">New period</Link>
                                    </Button>
                                )}
                            </div>
                            <div className="mt-4 overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[720px] text-sm">
                                    <thead className="bg-muted/60 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Name</th>
                                            <th className="px-3 py-2 font-medium">Range</th>
                                            <th className="px-3 py-2 font-medium">State</th>
                                            <th className="px-3 py-2 font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {leavePeriods.map((leavePeriod) => (
                                            <tr key={leavePeriod.id}>
                                                <td className="px-3 py-2">{leavePeriod.name}</td>
                                                <td className="px-3 py-2">{leavePeriod.start_date ?? '-'} to {leavePeriod.end_date ?? '-'}</td>
                                                <td className="px-3 py-2">{leavePeriod.is_closed ? 'Closed' : 'Open'}</td>
                                                <td className="px-3 py-2">
                                                    {abilities.can_manage_leave && (
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={`/company/hr/leave/periods/${leavePeriod.id}/edit`}>Edit</Link>
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
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
