import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ReasonDialog } from '@/components/feedback/reason-dialog';
import InputError from '@/components/input-error';
import { ModalFormShell } from '@/components/modals/modal-form-shell';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Summary = {
    records_today: number;
    present_today: number;
    missing_today: number;
    late_today: number;
    open_corrections: number;
    pending_my_approvals: number;
    active_shift_assignments: number;
};

type EmployeeOption = {
    id: string;
    name: string;
    employee_number?: string | null;
    linked_user_name?: string | null;
};

type ShiftOption = {
    id: string;
    name: string;
    code?: string | null;
    start_time: string;
    end_time: string;
};

type RecordRow = {
    id: string;
    attendance_date?: string | null;
    status: string;
    approval_status: string;
    employee_name?: string | null;
    employee_number?: string | null;
    shift_name?: string | null;
    check_in_at?: string | null;
    check_out_at?: string | null;
    worked_minutes: number;
    late_minutes: number;
    overtime_minutes: number;
    approved_by_name?: string | null;
    source_summary?: string | null;
};

type CorrectionRequestRow = {
    id: string;
    request_number: string;
    status: string;
    employee_name?: string | null;
    employee_number?: string | null;
    approver_name?: string | null;
    requested_status: string;
    from_date?: string | null;
    to_date?: string | null;
    requested_check_in_at?: string | null;
    requested_check_out_at?: string | null;
    reason: string;
    decision_notes?: string | null;
    can_edit: boolean;
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_cancel: boolean;
};

type CheckinRow = {
    id: string;
    employee_name?: string | null;
    employee_number?: string | null;
    log_type: string;
    source: string;
    recorded_at?: string | null;
    device_reference?: string | null;
};

type AssignmentRow = {
    id: string;
    employee_name?: string | null;
    employee_number?: string | null;
    shift_name?: string | null;
    shift_window?: string | null;
    from_date?: string | null;
    to_date?: string | null;
    can_edit: boolean;
};

type Props = {
    summary: Summary;
    filters: {
        status: string;
        approval_status: string;
        employee_id: string;
        attendance_date: string;
    };
    statuses: string[];
    approvalStatuses: string[];
    employeeOptions: EmployeeOption[];
    shiftOptions: ShiftOption[];
    linkedEmployeeId?: string | null;
    openAttendanceRecordId?: string | null;
    todayIso: string;
    records: {
        data: RecordRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    correctionRequests: CorrectionRequestRow[];
    recentCheckins: CheckinRow[];
    assignments: AssignmentRow[];
    abilities: {
        can_record_attendance: boolean;
        can_create_request: boolean;
        can_manage_attendance: boolean;
    };
};

const labelize = (value: string) =>
    value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

export default function HrAttendanceIndex({
    summary,
    filters,
    statuses,
    approvalStatuses,
    employeeOptions,
    linkedEmployeeId,
    openAttendanceRecordId,
    todayIso,
    records,
    correctionRequests,
    recentCheckins,
    assignments,
    abilities,
}: Props) {
    const filterForm = useForm(filters);
    const [showShiftModal, setShowShiftModal] = useState(false);
    const [rejectingRequest, setRejectingRequest] = useState<{
        id: string;
        requestNumber: string;
    } | null>(null);
    const punchForm = useForm({
        employee_id: linkedEmployeeId ?? '',
        recorded_at: todayIso,
        source: 'web',
    });
    const shiftForm = useForm({
        name: '',
        code: '',
        start_time: '',
        end_time: '',
        grace_minutes: '',
        auto_attendance_enabled: false,
    });
    const rejectForm = useForm({
        reason: '',
    });

    const closeShiftModal = (open: boolean) => {
        setShowShiftModal(open);

        if (!open) {
            shiftForm.reset();
            shiftForm.clearErrors();
        }
    };

    const closeRejectDialog = (open: boolean) => {
        if (rejectForm.processing) {
            return;
        }

        if (!open) {
            rejectForm.reset();
            rejectForm.clearErrors();
            setRejectingRequest(null);
        }
    };

    const submitRejectRequest = () => {
        if (!rejectingRequest) {
            return;
        }

        rejectForm.post(
            `/company/hr/attendance/requests/${rejectingRequest.id}/reject`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeRejectDialog(false);
                },
            },
        );
    };

    const submitPunch = (endpoint: 'check-in' | 'check-out') => {
        punchForm.post(`/company/hr/attendance/${endpoint}`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.hr, { title: 'Attendance', href: '/company/hr/attendance' },)}
        >
            <Head title="Attendance workspace" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Attendance workspace</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage shifts, check-ins, daily attendance records, and correction approvals.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <BackLinkAction href="/company/hr" label="Back to HR" variant="outline" />
                        {abilities.can_manage_attendance && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowShiftModal(true)}
                                >
                                    New shift
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/company/hr/attendance/assignments/create">New assignment</Link>
                                </Button>
                            </>
                        )}
                        {abilities.can_create_request && (
                            <Button asChild>
                                <Link href="/company/hr/attendance/requests/create">New correction</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <ModalFormShell
                    open={showShiftModal}
                    onOpenChange={closeShiftModal}
                    title="New shift"
                    description="Define the expected shift window for attendance tracking."
                    className="sm:max-w-xl"
                >
                    <form
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            shiftForm.post('/company/hr/attendance/shifts', {
                                onSuccess: () => closeShiftModal(false),
                            });
                        }}
                    >
                        <Field label="Name" error={shiftForm.errors.name}>
                            <Input
                                value={shiftForm.data.name}
                                onChange={(event) =>
                                    shiftForm.setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Code" error={shiftForm.errors.code}>
                            <Input
                                value={shiftForm.data.code}
                                onChange={(event) =>
                                    shiftForm.setData(
                                        'code',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field
                                label="Start"
                                error={shiftForm.errors.start_time}
                            >
                                <Input
                                    type="time"
                                    value={shiftForm.data.start_time}
                                    onChange={(event) =>
                                        shiftForm.setData(
                                            'start_time',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="End" error={shiftForm.errors.end_time}>
                                <Input
                                    type="time"
                                    value={shiftForm.data.end_time}
                                    onChange={(event) =>
                                        shiftForm.setData(
                                            'end_time',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Grace minutes"
                                error={shiftForm.errors.grace_minutes}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    value={shiftForm.data.grace_minutes}
                                    onChange={(event) =>
                                        shiftForm.setData(
                                            'grace_minutes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                        <label className="flex items-center gap-2 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3 text-sm">
                            <input
                                type="checkbox"
                                checked={shiftForm.data.auto_attendance_enabled}
                                onChange={(event) =>
                                    shiftForm.setData(
                                        'auto_attendance_enabled',
                                        event.target.checked,
                                    )
                                }
                            />
                            Enable auto-attendance processing
                        </label>
                        <div className="flex items-center justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeShiftModal(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={shiftForm.processing}>
                                Create shift
                            </Button>
                        </div>
                    </form>
                </ModalFormShell>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-7">
                    <Metric label="Records today" value={summary.records_today} />
                    <Metric label="Present" value={summary.present_today} />
                    <Metric label="Missing" value={summary.missing_today} />
                    <Metric label="Late today" value={summary.late_today} />
                    <Metric label="Open corrections" value={summary.open_corrections} />
                    <Metric label="My approvals" value={summary.pending_my_approvals} />
                    <Metric label="Active shifts" value={summary.active_shift_assignments} />
                </div>

                {abilities.can_record_attendance && (
                    <div className="rounded-xl border p-4">
                        <div>
                            <h2 className="text-sm font-semibold">Quick punch</h2>
                            <p className="text-xs text-muted-foreground">
                                Record a check-in or check-out directly into the attendance log.
                            </p>
                        </div>
                        <div className="mt-4 grid gap-4 md:grid-cols-4">
                            <Field label="Employee" error={punchForm.errors.employee_id}>
                                <select
                                    className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                                    value={punchForm.data.employee_id}
                                    onChange={(event) => punchForm.setData('employee_id', event.target.value)}
                                    disabled={!abilities.can_manage_attendance && Boolean(linkedEmployeeId)}
                                >
                                    <option value="">Select employee</option>
                                    {employeeOptions.map((employee) => (
                                        <option key={employee.id} value={employee.id}>
                                            {employee.name}
                                            {employee.employee_number ? ` (${employee.employee_number})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Recorded at" error={punchForm.errors.recorded_at}>
                                <input
                                    type="datetime-local"
                                    className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                                    value={punchForm.data.recorded_at}
                                    onChange={(event) => punchForm.setData('recorded_at', event.target.value)}
                                />
                            </Field>
                            <Field label="Source" error={punchForm.errors.source}>
                                <select
                                    className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                                    value={punchForm.data.source}
                                    onChange={(event) => punchForm.setData('source', event.target.value)}
                                >
                                    <option value="web">Web</option>
                                    <option value="manual">Manual</option>
                                    <option value="mobile">Mobile</option>
                                </select>
                            </Field>
                            <div className="flex items-end gap-2">
                                <Button type="button" onClick={() => submitPunch('check-in')}>
                                    Check in
                                </Button>
                                <Button
                                    type="button"
                                    variant={openAttendanceRecordId ? 'default' : 'outline'}
                                    onClick={() => submitPunch('check-out')}
                                >
                                    Check out
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
                <form
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        filterForm.get('/company/hr/attendance', { preserveState: true, replace: true });
                    }}
                >
                    <Field label="Status" error={filterForm.errors.status}>
                        <select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={filterForm.data.status} onChange={(event) => filterForm.setData('status', event.target.value)}>
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>{labelize(status)}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Approval" error={filterForm.errors.approval_status}>
                        <select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={filterForm.data.approval_status} onChange={(event) => filterForm.setData('approval_status', event.target.value)}>
                            <option value="">All approvals</option>
                            {approvalStatuses.map((approvalStatus) => (
                                <option key={approvalStatus} value={approvalStatus}>{labelize(approvalStatus)}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Employee" error={filterForm.errors.employee_id}>
                        <select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={filterForm.data.employee_id} onChange={(event) => filterForm.setData('employee_id', event.target.value)}>
                            <option value="">All employees</option>
                            {employeeOptions.map((employee) => (
                                <option key={employee.id} value={employee.id}>{employee.name}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Attendance date" error={filterForm.errors.attendance_date}>
                        <input type="date" className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={filterForm.data.attendance_date} onChange={(event) => filterForm.setData('attendance_date', event.target.value)} />
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button type="submit">Apply</Button>
                        <Button type="button" variant="ghost" onClick={() => router.get('/company/hr/attendance')}>Reset</Button>
                    </div>
                </form>

                <div className="rounded-xl border p-4">
                    <div>
                        <h2 className="text-sm font-semibold">Daily attendance records</h2>
                        <p className="text-xs text-muted-foreground">Review normalized daily attendance, lateness, and overtime signals.</p>
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[1180px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Date</th>
                                    <th className="px-3 py-2 font-medium">Employee</th>
                                    <th className="px-3 py-2 font-medium">Shift</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Check in</th>
                                    <th className="px-3 py-2 font-medium">Check out</th>
                                    <th className="px-3 py-2 font-medium">Worked</th>
                                    <th className="px-3 py-2 font-medium">Late</th>
                                    <th className="px-3 py-2 font-medium">Overtime</th>
                                    <th className="px-3 py-2 font-medium">Approval</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {records.data.length === 0 && (
                                    <tr><td className="px-3 py-6 text-center text-muted-foreground" colSpan={10}>No attendance records found.</td></tr>
                                )}
                                {records.data.map((record) => (
                                    <tr key={record.id}>
                                        <td className="px-3 py-2">{record.attendance_date ?? '-'}</td>
                                        <td className="px-3 py-2"><div>{record.employee_name ?? '-'}</div>{record.employee_number && <div className="text-xs text-muted-foreground">{record.employee_number}</div>}</td>
                                        <td className="px-3 py-2">{record.shift_name ?? '-'}</td>
                                        <td className="px-3 py-2 capitalize">{labelize(record.status)}</td>
                                        <td className="px-3 py-2">{record.check_in_at ?? '-'}</td>
                                        <td className="px-3 py-2">{record.check_out_at ?? '-'}</td>
                                        <td className="px-3 py-2">{record.worked_minutes} min</td>
                                        <td className="px-3 py-2">{record.late_minutes} min</td>
                                        <td className="px-3 py-2">{record.overtime_minutes} min</td>
                                        <td className="px-3 py-2"><div>{labelize(record.approval_status)}</div>{record.source_summary && <div className="max-w-xs truncate text-xs text-muted-foreground" title={record.source_summary}>{record.source_summary}</div>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {records.links.length > 3 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {records.links.map((link, index) => (
                                <Button key={`${link.label}-${index}`} type="button" variant={link.active ? 'default' : 'outline'} disabled={!link.url} onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })} dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold">Correction requests</h2>
                            {abilities.can_create_request && <Button variant="ghost" asChild><Link href="/company/hr/attendance/requests/create">New correction</Link></Button>}
                        </div>
                        <div className="mt-4 overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[1080px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Request</th>
                                        <th className="px-3 py-2 font-medium">Employee</th>
                                        <th className="px-3 py-2 font-medium">Range</th>
                                        <th className="px-3 py-2 font-medium">Requested</th>
                                        <th className="px-3 py-2 font-medium">Approver</th>
                                        <th className="px-3 py-2 font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {correctionRequests.length === 0 && (
                                        <tr><td className="px-3 py-6 text-center text-muted-foreground" colSpan={6}>No correction requests found.</td></tr>
                                    )}
                                    {correctionRequests.map((requestRecord) => (
                                        <tr key={requestRecord.id}>
                                            <td className="px-3 py-2 align-top"><div className="font-medium">{requestRecord.request_number}</div><div className="text-xs text-muted-foreground">{labelize(requestRecord.status)}</div></td>
                                            <td className="px-3 py-2 align-top"><div>{requestRecord.employee_name ?? '-'}</div>{requestRecord.employee_number && <div className="text-xs text-muted-foreground">{requestRecord.employee_number}</div>}</td>
                                            <td className="px-3 py-2 align-top">{requestRecord.from_date ?? '-'} to {requestRecord.to_date ?? '-'}</td>
                                            <td className="px-3 py-2 align-top"><div className="capitalize">{labelize(requestRecord.requested_status)}</div>{requestRecord.requested_check_in_at && <div className="text-xs text-muted-foreground">{requestRecord.requested_check_in_at}{requestRecord.requested_check_out_at ? ` -> ${requestRecord.requested_check_out_at}` : ''}</div>}</td>
                                            <td className="px-3 py-2 align-top">{requestRecord.approver_name ?? '-'}</td>
                                            <td className="px-3 py-2 align-top">
                                                <div className="flex flex-wrap gap-2">
                                                    {requestRecord.can_edit && <Button variant="outline" size="sm" asChild><Link href={`/company/hr/attendance/requests/${requestRecord.id}/edit`}>Edit</Link></Button>}
                                                    {requestRecord.can_submit && <Button variant="outline" size="sm" type="button" onClick={() => router.post(`/company/hr/attendance/requests/${requestRecord.id}/submit`, {}, { preserveScroll: true })}>Submit</Button>}
                                                    {requestRecord.can_approve && <Button size="sm" type="button" onClick={() => router.post(`/company/hr/attendance/requests/${requestRecord.id}/approve`, {}, { preserveScroll: true })}>Approve</Button>}
                                                    {requestRecord.can_reject && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            type="button"
                                                            onClick={() => {
                                                                rejectForm.reset();
                                                                rejectForm.clearErrors();
                                                                setRejectingRequest({
                                                                    id: requestRecord.id,
                                                                    requestNumber: requestRecord.request_number,
                                                                });
                                                            }}
                                                        >
                                                            Reject
                                                        </Button>
                                                    )}
                                                    {requestRecord.can_cancel && <Button variant="ghost" size="sm" type="button" onClick={() => router.post(`/company/hr/attendance/requests/${requestRecord.id}/cancel`, {}, { preserveScroll: true })}>Cancel</Button>}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div className="grid gap-4">
                        <div className="rounded-xl border p-4">
                            <h2 className="text-sm font-semibold">Recent check-ins</h2>
                            <div className="mt-4 overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[760px] text-sm">
                                    <thead className="bg-muted/60 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Employee</th>
                                            <th className="px-3 py-2 font-medium">Type</th>
                                            <th className="px-3 py-2 font-medium">Source</th>
                                            <th className="px-3 py-2 font-medium">Recorded at</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {recentCheckins.length === 0 && <tr><td className="px-3 py-6 text-center text-muted-foreground" colSpan={4}>No check-ins recorded yet.</td></tr>}
                                        {recentCheckins.map((checkin) => (
                                            <tr key={checkin.id}>
                                                <td className="px-3 py-2">{checkin.employee_name ?? checkin.employee_number ?? '-'}</td>
                                                <td className="px-3 py-2 uppercase">{checkin.log_type}</td>
                                                <td className="px-3 py-2 capitalize">{checkin.source}</td>
                                                <td className="px-3 py-2">{checkin.recorded_at ?? '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="rounded-xl border p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-sm font-semibold">Shift assignments</h2>
                                {abilities.can_manage_attendance && <Button variant="ghost" asChild><Link href="/company/hr/attendance/assignments/create">New assignment</Link></Button>}
                            </div>
                            <div className="mt-4 overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[760px] text-sm">
                                    <thead className="bg-muted/60 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Employee</th>
                                            <th className="px-3 py-2 font-medium">Shift</th>
                                            <th className="px-3 py-2 font-medium">Range</th>
                                            <th className="px-3 py-2 font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {assignments.length === 0 && <tr><td className="px-3 py-6 text-center text-muted-foreground" colSpan={4}>No shift assignments found.</td></tr>}
                                        {assignments.map((assignment) => (
                                            <tr key={assignment.id}>
                                                <td className="px-3 py-2">{assignment.employee_name ?? assignment.employee_number ?? '-'}</td>
                                                <td className="px-3 py-2"><div>{assignment.shift_name ?? '-'}</div>{assignment.shift_window && <div className="text-xs text-muted-foreground">{assignment.shift_window}</div>}</td>
                                                <td className="px-3 py-2">{assignment.from_date ?? '-'} to {assignment.to_date ?? 'Open'}</td>
                                                <td className="px-3 py-2">{assignment.can_edit && <Button variant="outline" size="sm" asChild><Link href={`/company/hr/attendance/assignments/${assignment.id}/edit`}>Edit</Link></Button>}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ReasonDialog
                open={Boolean(rejectingRequest)}
                onOpenChange={closeRejectDialog}
                title={
                    rejectingRequest
                        ? `Reject correction ${rejectingRequest.requestNumber}?`
                        : 'Reject attendance correction?'
                }
                description="This will reject the correction request and keep the current attendance record unchanged."
                confirmLabel="Reject correction"
                processingLabel="Rejecting..."
                cancelLabel="Keep correction"
                processing={rejectForm.processing}
                onConfirm={submitRejectRequest}
                reason={rejectForm.data.reason}
                onReasonChange={(value) => rejectForm.setData('reason', value)}
                reasonLabel="Reason"
                reasonPlaceholder="Add context for the requester if needed."
                reasonHelperText="This note is optional, but it will be recorded with the decision."
                reasonError={rejectForm.errors.reason}
                errors={rejectForm.errors}
            />
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
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
