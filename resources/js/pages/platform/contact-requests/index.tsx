import { Head, useForm } from '@inertiajs/react';
import { PencilLine } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ModalFormShell } from '@/components/modals/modal-form-shell';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { DataTableShell } from '@/components/shell/data-table-shell';
import {
    FilterField,
    FilterToolbar,
    FilterToolbarActions,
    FilterToolbarGrid,
} from '@/components/shell/filter-toolbar';
import { PageHeader } from '@/components/shell/page-header';
import { PaginationBar } from '@/components/shell/pagination-bar';
import { WorkspaceShell } from '@/components/shell/workspace-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { platformBreadcrumbs } from '@/lib/page-navigation';

type Option = {
    value: string;
    label: string;
};

type ContactRequestRow = {
    id: string;
    request_type: 'demo' | 'sales';
    full_name: string;
    work_email: string;
    company_name: string;
    role_title: string;
    team_size: string;
    preferred_demo_date?: string | null;
    scheduled_demo_date?: string | null;
    modules_interest: string[];
    message?: string | null;
    phone?: string | null;
    country?: string | null;
    source_page?: string | null;
    status: string;
    assigned_to?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
};

type Props = {
    contactRequests: {
        data: ContactRequestRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: {
        search?: string | null;
        request_type?: string | null;
        status?: string | null;
    };
    requestTypeOptions: Option[];
    statusOptions: Option[];
};

const NATIVE_SELECT_CLASS =
    'h-10 w-full rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30';

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatDate = (value?: string | null) =>
    value ? new Date(`${value}T00:00:00`).toLocaleDateString() : '-';

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());

const formatSource = (source?: string | null) => {
    if (!source) {
        return '-';
    }

    return source
        .replace(/^\//, '')
        .replace(/[-/]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
};

function ContactRequestStatusEditor({
    requestId,
    requestType,
    currentStatus,
    preferredDemoDate,
    currentScheduledDemoDate,
    statusOptions,
}: {
    requestId: string;
    requestType: 'demo' | 'sales';
    currentStatus: string;
    preferredDemoDate?: string | null;
    currentScheduledDemoDate?: string | null;
    statusOptions: Option[];
}) {
    const [open, setOpen] = useState(false);
    const rowStatusOptions =
        requestType === 'demo'
            ? statusOptions
            : statusOptions.filter((option) => option.value !== 'demo_scheduled');
    const requestedDemoDate = preferredDemoDate ?? '';
    const initialScheduledDemoDate = currentScheduledDemoDate ?? '';
    const form = useForm({
        status: currentStatus,
        scheduled_demo_date: initialScheduledDemoDate,
        demo_date_change_reason: '',
    });
    const isDemoRequest = requestType === 'demo';
    const needsScheduledDate =
        isDemoRequest && form.data.status === 'demo_scheduled';
    const showScheduledDateField =
        isDemoRequest && (needsScheduledDate || initialScheduledDemoDate !== '');
    const scheduledDateChanged =
        isDemoRequest
        && form.data.scheduled_demo_date !== initialScheduledDemoDate;
    const needsChangeReason =
        isDemoRequest
        && scheduledDateChanged
        && (
            initialScheduledDemoDate !== ''
            || (
                form.data.scheduled_demo_date !== ''
                && form.data.scheduled_demo_date !== requestedDemoDate
            )
        );
    const hasChanges =
        form.data.status !== currentStatus
        || scheduledDateChanged;

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="mx-auto size-9"
                onClick={() => {
                    form.setData({
                        status: currentStatus,
                        scheduled_demo_date: initialScheduledDemoDate,
                        demo_date_change_reason: '',
                    });
                    form.clearErrors();
                    setOpen(true);
                }}
                aria-label="Update request"
                title="Update request"
            >
                <PencilLine className="size-4" />
            </Button>

            <ModalFormShell
                open={open}
                onOpenChange={(nextOpen) => {
                    if (!form.processing) {
                        setOpen(nextOpen);
                    }
                }}
                title="Update contact request"
                description={
                    isDemoRequest
                        ? 'Review the requested date, confirm the scheduled date, and add a reason when the confirmed date changes.'
                        : 'Update the request status.'
                }
                className="sm:max-w-2xl"
            >
                <form
                    className="grid gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();

                        form.put(`/platform/contact-requests/${requestId}`, {
                            preserveScroll: true,
                            preserveState: true,
                            onSuccess: () => {
                                setOpen(false);
                                form.reset('demo_date_change_reason');
                            },
                        });
                    }}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`contact-request-status-${requestId}`}>
                                Status
                            </Label>
                            <select
                                id={`contact-request-status-${requestId}`}
                                className={NATIVE_SELECT_CLASS}
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                                disabled={form.processing}
                            >
                                {rowStatusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.status} />
                        </div>

                        {isDemoRequest ? (
                            <div className="grid gap-2">
                                <Label>Requested demo date</Label>
                                <div className="rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3 text-sm text-foreground">
                                    {formatDate(requestedDemoDate)}
                                </div>
                            </div>
                        ) : null}
                    </div>

                    {showScheduledDateField ? (
                        <div className="grid gap-2">
                            <Label htmlFor={`scheduled-demo-date-${requestId}`}>
                                Confirmed demo date
                            </Label>
                            <Input
                                id={`scheduled-demo-date-${requestId}`}
                                type="date"
                                value={form.data.scheduled_demo_date}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_demo_date',
                                        event.target.value,
                                    )
                                }
                                disabled={form.processing}
                            />
                            <p className="text-xs text-[color:var(--text-secondary)]">
                                Required when the request moves to demo scheduled.
                            </p>
                            <InputError
                                message={form.errors.scheduled_demo_date}
                            />
                        </div>
                    ) : null}

                    {needsChangeReason ? (
                        <div className="grid gap-2">
                            <Label htmlFor={`demo-date-change-reason-${requestId}`}>
                                Reason for the date change
                            </Label>
                            <Textarea
                                id={`demo-date-change-reason-${requestId}`}
                                value={form.data.demo_date_change_reason}
                                onChange={(event) =>
                                    form.setData(
                                        'demo_date_change_reason',
                                        event.target.value,
                                    )
                                }
                                placeholder="Explain why the confirmed demo date differs from the requested or previously scheduled date."
                                disabled={form.processing}
                            />
                            <InputError
                                message={form.errors.demo_date_change_reason}
                            />
                        </div>
                    ) : null}

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing
                                || !hasChanges
                                || (needsScheduledDate
                                    && !form.data.scheduled_demo_date)
                                || (needsChangeReason
                                    && !form.data.demo_date_change_reason.trim())
                            }
                        >
                            {form.processing ? 'Saving...' : 'Save changes'}
                        </Button>
                    </div>
                </form>
            </ModalFormShell>
        </>
    );
}

export default function PlatformContactRequestsIndex({
    contactRequests,
    filters,
    requestTypeOptions,
    statusOptions,
}: Props) {
    const filterForm = useForm({
        search: filters.search ?? '',
        request_type: filters.request_type ?? '',
        status: filters.status ?? '',
    });

    return (
        <AppLayout
            breadcrumbs={platformBreadcrumbs({
                title: 'Contact Requests',
                href: '/platform/contact-requests',
            })}
        >
            <Head title="Contact Requests" />

            <WorkspaceShell
                header={
                    <PageHeader
                        title="Inbound contact requests"
                        description="Review public demo and sales requests submitted from the homepage and public access paths."
                        meta={<span>{contactRequests.total} total requests</span>}
                        actions={
                            <BackLinkAction
                                href="/platform/dashboard"
                                label="Back to platform"
                                variant="outline"
                            />
                        }
                    />
                }
            >
                <FilterToolbar
                    onSubmit={(event) => {
                        event.preventDefault();
                        filterForm.get('/platform/contact-requests', {
                            preserveState: true,
                            preserveScroll: true,
                            replace: true,
                        });
                    }}
                >
                    <FilterToolbarGrid className="xl:grid-cols-[minmax(0,1.4fr)_220px_220px_auto]">
                        <FilterField
                            label="Search"
                            htmlFor="search"
                            hint="Search by name, work email, company, or role title."
                        >
                            <Input
                                id="search"
                                value={filterForm.data.search}
                                onChange={(event) =>
                                    filterForm.setData('search', event.target.value)
                                }
                                placeholder="Search requests"
                            />
                        </FilterField>

                        <FilterField label="Request type" htmlFor="request_type">
                            <select
                                id="request_type"
                                className={NATIVE_SELECT_CLASS}
                                value={filterForm.data.request_type}
                                onChange={(event) =>
                                    filterForm.setData('request_type', event.target.value)
                                }
                            >
                                <option value="">All request types</option>
                                {requestTypeOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FilterField>

                        <FilterField label="Status" htmlFor="status">
                            <select
                                id="status"
                                className={NATIVE_SELECT_CLASS}
                                value={filterForm.data.status}
                                onChange={(event) =>
                                    filterForm.setData('status', event.target.value)
                                }
                            >
                                <option value="">All statuses</option>
                                {statusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FilterField>

                        <FilterToolbarActions className="md:justify-end">
                            <Button type="submit" disabled={filterForm.processing}>
                                Apply
                            </Button>
                            <Button variant="outline" type="button" asChild>
                                <a href="/platform/contact-requests">Reset</a>
                            </Button>
                        </FilterToolbarActions>
                    </FilterToolbarGrid>
                </FilterToolbar>

                <DataTableShell
                    header={
                        <div className="space-y-1">
                            <p className="text-sm font-semibold text-foreground">
                                Public lead capture queue
                            </p>
                            <p className="text-sm text-[color:var(--text-secondary)]">
                                Requests are stored durably and surfaced here for superadmin review.
                            </p>
                        </div>
                    }
                    footer={<PaginationBar links={contactRequests.links} />}
                >
                    <Table container={false}>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Submitted</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Company context</TableHead>
                                <TableHead>Demo dates</TableHead>
                                <TableHead>Modules</TableHead>
                                <TableHead>Message</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-[72px] text-center">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {contactRequests.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={9}
                                        className="py-10 text-center text-[color:var(--text-secondary)]"
                                    >
                                        No public requests match the current filters.
                                    </TableCell>
                                </TableRow>
                            ) : null}

                            {contactRequests.data.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="align-top">
                                        <div className="space-y-1">
                                            <p className="font-medium text-foreground">
                                                {formatDateTime(item.created_at)}
                                            </p>
                                            <p className="text-xs text-[color:var(--text-secondary)]">
                                                Updated {formatDateTime(item.updated_at)}
                                            </p>
                                        </div>
                                    </TableCell>

                                    <TableCell className="align-top">
                                        <div className="space-y-2">
                                            <StatusBadge
                                                status={item.request_type}
                                                label={
                                                    item.request_type === 'demo'
                                                        ? 'Book demo'
                                                        : 'Contact sales'
                                                }
                                            />
                                            <p className="text-xs text-[color:var(--text-secondary)]">
                                                {formatSource(item.source_page)}
                                            </p>
                                        </div>
                                    </TableCell>

                                    <TableCell className="align-top">
                                        <div className="space-y-1">
                                            <p className="font-medium text-foreground">
                                                {item.full_name}
                                            </p>
                                            <p className="text-sm text-[color:var(--text-secondary)]">
                                                {item.work_email}
                                            </p>
                                            <p className="text-xs text-[color:var(--text-secondary)]">
                                                {item.phone || '-'}
                                            </p>
                                        </div>
                                    </TableCell>

                                    <TableCell className="align-top">
                                        <div className="space-y-1">
                                            <p className="font-medium text-foreground">
                                                {item.company_name}
                                            </p>
                                            <p className="text-sm text-[color:var(--text-secondary)]">
                                                {item.role_title}
                                            </p>
                                            <p className="text-xs text-[color:var(--text-secondary)]">
                                                Team size {item.team_size}
                                                {item.country ? ` | ${item.country}` : ''}
                                            </p>
                                        </div>
                                    </TableCell>

                                    <TableCell className="align-top">
                                        {item.request_type === 'demo' ? (
                                            <div className="space-y-2 text-sm">
                                                <div>
                                                    <p className="font-medium text-foreground">
                                                        Preferred
                                                    </p>
                                                    <p className="text-[color:var(--text-secondary)]">
                                                        {formatDate(item.preferred_demo_date)}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="font-medium text-foreground">
                                                        Scheduled
                                                    </p>
                                                    <p className="text-[color:var(--text-secondary)]">
                                                        {formatDate(item.scheduled_demo_date)}
                                                    </p>
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="text-sm text-[color:var(--text-secondary)]">
                                                -
                                            </span>
                                        )}
                                    </TableCell>

                                    <TableCell className="align-top">
                                        <div className="flex max-w-[220px] flex-wrap gap-2">
                                            {item.modules_interest.length > 0 ? (
                                                item.modules_interest.map((module) => (
                                                    <span
                                                        key={`${item.id}-${module}`}
                                                        className="rounded-full border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-2.5 py-1 text-xs text-[color:var(--text-secondary)]"
                                                    >
                                                        {formatLabel(module)}
                                                    </span>
                                                ))
                                            ) : (
                                                <span className="text-sm text-[color:var(--text-secondary)]">
                                                    -
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>

                                    <TableCell className="align-top">
                                        <div className="max-w-[320px] space-y-2">
                                            <p className="text-sm leading-6 text-foreground">
                                                {item.message || 'No additional context provided.'}
                                            </p>
                                        </div>
                                    </TableCell>

                                    <TableCell className="align-top">
                                        <StatusBadge status={item.status} />
                                    </TableCell>

                                    <TableCell className="align-top text-center">
                                        <ContactRequestStatusEditor
                                            requestId={item.id}
                                            requestType={item.request_type}
                                            currentStatus={item.status}
                                            preferredDemoDate={item.preferred_demo_date}
                                            currentScheduledDemoDate={item.scheduled_demo_date}
                                            statusOptions={statusOptions}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </DataTableShell>
            </WorkspaceShell>
        </AppLayout>
    );
}
