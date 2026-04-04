import { Head, Link, router, useForm, useRemember } from '@inertiajs/react';
import { useState } from 'react';
import {
    OperationResultPanel,
    type OperationResultFeedback,
} from '@/components/feedback/operation-result-panel';
import { ReasonDialog } from '@/components/feedback/reason-dialog';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { resolveFlashToast } from '@/lib/feedback-toast';
import { firstFormErrorMessage } from '@/lib/form-feedback';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';
import type { SharedData } from '@/types';

type FilterOption = {
    id: string;
    name: string;
};

type ProjectFilterOption = FilterOption & {
    project_code: string;
    customer_name?: string | null;
};

type ScheduleRow = {
    id: string;
    project_id: string;
    project_code?: string | null;
    project_name?: string | null;
    customer_name?: string | null;
    name: string;
    description?: string | null;
    frequency: string;
    quantity: number;
    unit_price: number;
    amount: number;
    currency_code?: string | null;
    status: string;
    next_run_on?: string | null;
    ends_on?: string | null;
    auto_create_invoice_draft: boolean;
    invoice_grouping: string;
    last_run_at?: string | null;
    last_invoice_id?: string | null;
    last_invoice_number?: string | null;
    can_open_last_invoice?: boolean;
    latest_run_status?: string | null;
    latest_cycle_label?: string | null;
    latest_error_message?: string | null;
    latest_invoice_id?: string | null;
    latest_invoice_number?: string | null;
    can_edit: boolean;
    can_run: boolean;
    can_activate: boolean;
    can_pause: boolean;
    can_cancel: boolean;
    can_open_project: boolean;
};

type Props = {
    filters: {
        project_id: string;
        customer_id: string;
        status: string;
        frequency: string;
        auto_invoice: string;
    };
    statuses: string[];
    frequencies: string[];
    projectsFilterOptions: ProjectFilterOption[];
    customersFilterOptions: FilterOption[];
    summary: {
        active_count: number;
        due_now_count: number;
        auto_invoice_count: number;
        active_recurring_amount: number;
    };
    schedules: {
        data: ScheduleRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    abilities: {
        can_create: boolean;
        can_view_projects_workspace: boolean;
    };
};

type CancelDialogState = {
    id: string;
    projectLabel: string;
    scheduleName: string;
    nextRunOn?: string | null;
};

type ScheduleActionContext = {
    action: 'run' | 'activate' | 'pause' | 'cancel';
    id: string;
    projectLabel: string;
    scheduleName: string;
    nextRunOn?: string | null;
};

type RecurringBillingResultPage = {
    props: Props &
        SharedData & {
            errors?: Record<string, string | string[] | undefined | null>;
        };
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectRecurringBillingIndex({
    filters,
    statuses,
    frequencies,
    projectsFilterOptions,
    customersFilterOptions,
    summary,
    schedules,
    abilities,
}: Props) {
    const form = useForm({
        project_id: filters.project_id,
        customer_id: filters.customer_id,
        status: filters.status,
        frequency: filters.frequency,
        auto_invoice: filters.auto_invoice,
    });
    const cancelForm = useForm({
        reason: '',
    });
    const [cancelDialog, setCancelDialog] = useState<CancelDialogState | null>(
        null,
    );
    const [operationFeedback, setOperationFeedback] =
        useRemember<OperationResultFeedback | null>(
            null,
            'projects.recurring-billing.operation-feedback',
        );

    const buildScheduleFeedback = (
        page: RecurringBillingResultPage,
        context: ScheduleActionContext,
    ): OperationResultFeedback => {
        const flash = resolveFlashToast(page.props.flash, {
            includeSuppressed: true,
        });
        const schedule = page.props.schedules.data.find(
            (row) => row.id === context.id,
        );
        const tone =
            flash?.level === 'error'
                ? 'error'
                : flash?.level === 'warning'
                  ? 'warning'
                  : 'success';

        return {
            tone,
            title:
                context.action === 'run'
                    ? tone === 'error'
                        ? 'Recurring run needs attention'
                        : 'Recurring run updated'
                    : 'Recurring schedule updated',
            message:
                flash?.message ??
                (context.action === 'run'
                    ? 'The recurring billing run was processed.'
                    : 'The recurring billing schedule was updated.'),
            details: [
                `${context.projectLabel} | ${context.scheduleName}`,
                schedule?.latest_run_status
                    ? `Latest run: ${formatLabel(schedule.latest_run_status)}`
                    : context.nextRunOn
                      ? `Next run: ${context.nextRunOn}`
                      : 'No upcoming run is currently scheduled on this page',
                schedule?.latest_invoice_number
                    ? `Latest invoice: ${schedule.latest_invoice_number}`
                    : schedule?.status
                      ? `Current status: ${formatLabel(schedule.status)}`
                      : 'Refresh the table filters if the schedule is no longer visible on this page',
            ],
            nextStep:
                context.action === 'run'
                    ? tone === 'error'
                        ? schedule?.latest_error_message
                          ? `Latest run error: ${schedule.latest_error_message}`
                          : 'Review the latest run status in the table and correct the schedule before trying again.'
                        : schedule?.latest_invoice_number
                          ? 'Open the latest invoice or keep monitoring the latest run status from this list.'
                          : 'Review the latest run status from this list before the next billing cycle.'
                    : tone === 'error'
                      ? 'Review the schedule status on this page before trying the action again.'
                      : context.action === 'cancel'
                        ? 'Future recurring billing runs for this schedule are now stopped until a replacement schedule is created.'
                        : 'Review the next run date and automation state from this list.',
        };
    };

    const buildScheduleErrorFeedback = (
        context: ScheduleActionContext,
        errors: Record<string, string | string[] | undefined | null>,
    ): OperationResultFeedback => ({
        tone: 'error',
        title:
            context.action === 'run'
                ? 'Recurring run failed'
                : 'Recurring schedule update failed',
        message:
            firstFormErrorMessage(errors) ??
            'The recurring billing action could not be completed.',
        details: [`${context.projectLabel} | ${context.scheduleName}`],
        nextStep:
            context.action === 'run'
                ? 'Review the schedule setup and latest run details before trying again.'
                : 'Review the schedule state and retry once the blocking issue is resolved.',
    });

    const closeCancelDialog = (open: boolean) => {
        if (cancelForm.processing) {
            return;
        }

        if (!open) {
            cancelForm.reset();
            cancelForm.clearErrors();
            setCancelDialog(null);
        }
    };

    const openCancelDialog = (schedule: ScheduleRow) => {
        cancelForm.reset();
        cancelForm.clearErrors();
        setCancelDialog({
            id: schedule.id,
            projectLabel: schedule.project_code
                ? schedule.project_name
                    ? `${schedule.project_code} - ${schedule.project_name}`
                    : schedule.project_code
                : schedule.project_name ?? 'Unknown project',
            scheduleName: schedule.name,
            nextRunOn: schedule.next_run_on,
        });
    };

    const buildActionContext = (
        schedule: ScheduleRow,
        action: ScheduleActionContext['action'],
    ): ScheduleActionContext => ({
        action,
        id: schedule.id,
        projectLabel: schedule.project_code
            ? schedule.project_name
                ? `${schedule.project_code} - ${schedule.project_name}`
                : schedule.project_code
            : schedule.project_name ?? 'Unknown project',
        scheduleName: schedule.name,
        nextRunOn: schedule.next_run_on,
    });

    const submitCancel = () => {
        if (!cancelDialog) {
            return;
        }

        const actionContext: ScheduleActionContext = {
            action: 'cancel',
            id: cancelDialog.id,
            projectLabel: cancelDialog.projectLabel,
            scheduleName: cancelDialog.scheduleName,
            nextRunOn: cancelDialog.nextRunOn,
        };

        cancelForm.post(
            `/company/projects/recurring-billing/${cancelDialog.id}/cancel`,
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setOperationFeedback(
                        buildScheduleFeedback(
                            page as unknown as RecurringBillingResultPage,
                            actionContext,
                        ),
                    );
                    cancelForm.reset();
                    cancelForm.clearErrors();
                    setCancelDialog(null);
                },
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.projects, {
                    title: 'Recurring Billing',
                    href: '/company/projects/recurring-billing',
                },)}
        >
            <Head title="Project Recurring Billing" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Project recurring billing
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage retainers, service contracts, and scheduled
                            draft-invoice generation across Projects.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <BackLinkAction href="/company/projects" label="Back to projects" variant="outline" />
                        {abilities.can_view_projects_workspace && (
                            <Button variant="outline" asChild>
                                <Link href="/company/projects/workspace">
                                    Workspace
                                </Link>
                            </Button>
                        )}
                        {abilities.can_create && (
                            <Button asChild>
                                <Link href="/company/projects/recurring-billing/create">
                                    New schedule
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {operationFeedback && (
                    <OperationResultPanel
                        feedback={operationFeedback}
                        onDismiss={() => setOperationFeedback(null)}
                        dismissLabel="Dismiss recurring billing feedback"
                    />
                )}

                <form
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get('/company/projects/recurring-billing', {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="project_id">Project</Label>
                        <select
                            id="project_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.project_id}
                            onChange={(event) =>
                                form.setData('project_id', event.target.value)
                            }
                        >
                            <option value="">All projects</option>
                            {projectsFilterOptions.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.project_code} - {project.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="customer_id">Customer</Label>
                        <select
                            id="customer_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.customer_id}
                            onChange={(event) =>
                                form.setData('customer_id', event.target.value)
                            }
                        >
                            <option value="">All customers</option>
                            {customersFilterOptions.map((customer) => (
                                <option key={customer.id} value={customer.id}>
                                    {customer.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                        >
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {formatLabel(status)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="frequency">Frequency</Label>
                        <select
                            id="frequency"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.frequency}
                            onChange={(event) =>
                                form.setData('frequency', event.target.value)
                            }
                        >
                            <option value="">All frequencies</option>
                            {frequencies.map((frequency) => (
                                <option key={frequency} value={frequency}>
                                    {formatLabel(frequency)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="auto_invoice">Auto invoice</Label>
                        <select
                            id="auto_invoice"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.auto_invoice}
                            onChange={(event) =>
                                form.setData('auto_invoice', event.target.value)
                            }
                        >
                            <option value="">All schedules</option>
                            <option value="yes">Auto invoice</option>
                            <option value="no">Manual invoice</option>
                        </select>
                    </div>

                    <div className="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-5">
                        <Button type="submit">Apply filters</Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                const resetFilters = {
                                    project_id: '',
                                    customer_id: '',
                                    status: '',
                                    frequency: '',
                                    auto_invoice: '',
                                };

                                form.setData(resetFilters);
                                router.get('/company/projects/recurring-billing', resetFilters, {
                                    preserveState: true,
                                    replace: true,
                                });
                            }}
                        >
                            Reset
                        </Button>
                    </div>
                </form>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Active schedules"
                        value={String(summary.active_count)}
                    />
                    <MetricCard
                        label="Due now"
                        value={String(summary.due_now_count)}
                    />
                    <MetricCard
                        label="Auto invoice"
                        value={String(summary.auto_invoice_count)}
                    />
                    <MetricCard
                        label="Active recurring amount"
                        value={summary.active_recurring_amount.toFixed(2)}
                    />
                </section>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[1480px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Project</th>
                                <th className="px-4 py-3 font-medium">Schedule</th>
                                <th className="px-4 py-3 font-medium">Frequency</th>
                                <th className="px-4 py-3 font-medium">Amount</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Next run</th>
                                <th className="px-4 py-3 font-medium">Automation</th>
                                <th className="px-4 py-3 font-medium">Latest run</th>
                                <th className="px-4 py-3 font-medium">Invoice</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {schedules.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No recurring billing schedules match the
                                        current filters.
                                    </td>
                                </tr>
                            )}
                            {schedules.data.map((schedule) => (
                                <tr key={schedule.id}>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {schedule.project_code ?? '-'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {schedule.project_name ?? '-'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {schedule.name}
                                        </p>
                                        <p className="max-w-[260px] truncate text-xs text-muted-foreground">
                                            {schedule.customer_name ?? 'No customer'}
                                            {schedule.description
                                                ? ` - ${schedule.description}`
                                                : ''}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatLabel(schedule.frequency)}
                                        <p className="text-xs text-muted-foreground">
                                            Group by{' '}
                                            {formatLabel(
                                                schedule.invoice_grouping,
                                            )}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="font-medium">
                                            {schedule.amount.toFixed(2)}
                                        </span>
                                        <p className="text-xs text-muted-foreground">
                                            {schedule.quantity.toFixed(2)} x{' '}
                                            {schedule.unit_price.toFixed(2)}{' '}
                                            {schedule.currency_code ?? ''}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatLabel(schedule.status)}
                                        {schedule.ends_on && (
                                            <p className="text-xs text-muted-foreground">
                                                Ends {schedule.ends_on}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {schedule.next_run_on ?? '-'}
                                        {schedule.last_run_at && (
                                            <p className="text-xs text-muted-foreground">
                                                Last run{' '}
                                                {new Date(
                                                    schedule.last_run_at,
                                                ).toLocaleString()}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {schedule.auto_create_invoice_draft
                                            ? 'Auto invoice'
                                            : 'Manual queue'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {schedule.latest_run_status ? (
                                            <div className="space-y-1">
                                                <p>
                                                    {formatLabel(
                                                        schedule.latest_run_status,
                                                    )}
                                                </p>
                                                {schedule.latest_cycle_label && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            schedule.latest_cycle_label
                                                        }
                                                    </p>
                                                )}
                                                {schedule.latest_error_message && (
                                                    <p className="max-w-[220px] truncate text-xs text-red-500">
                                                        {
                                                            schedule.latest_error_message
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            'No runs yet'
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {(schedule.last_invoice_number ??
                                            schedule.latest_invoice_number) ? (
                                            schedule.can_open_last_invoice ? (
                                                <Link
                                                    href={`/company/accounting/invoices/${schedule.last_invoice_id ?? schedule.latest_invoice_id}/edit`}
                                                    className="font-medium text-primary"
                                                >
                                                    {schedule.last_invoice_number ??
                                                        schedule.latest_invoice_number}
                                                </Link>
                                            ) : (
                                                schedule.last_invoice_number ??
                                                schedule.latest_invoice_number
                                            )
                                        ) : (
                                            'Not invoiced'
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="inline-flex flex-wrap items-center justify-end gap-3">
                                            {schedule.can_run && (
                                                <button
                                                    type="button"
                                                    className="font-medium text-primary"
                                                    onClick={() => {
                                                        const actionContext =
                                                            buildActionContext(
                                                                schedule,
                                                                'run',
                                                            );

                                                        router.post(
                                                            `/company/projects/recurring-billing/${schedule.id}/run-now`,
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                                onSuccess: (
                                                                    page,
                                                                ) => {
                                                                    setOperationFeedback(
                                                                        buildScheduleFeedback(
                                                                            page as unknown as RecurringBillingResultPage,
                                                                            actionContext,
                                                                        ),
                                                                    );
                                                                },
                                                                onError: (
                                                                    errors,
                                                                ) => {
                                                                    setOperationFeedback(
                                                                        buildScheduleErrorFeedback(
                                                                            actionContext,
                                                                            errors,
                                                                        ),
                                                                    );
                                                                },
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Run now
                                                </button>
                                            )}
                                            {schedule.can_activate && (
                                                <button
                                                    type="button"
                                                    className="font-medium text-primary"
                                                    onClick={() => {
                                                        const actionContext =
                                                            buildActionContext(
                                                                schedule,
                                                                'activate',
                                                            );

                                                        router.post(
                                                            `/company/projects/recurring-billing/${schedule.id}/activate`,
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                                onSuccess: (
                                                                    page,
                                                                ) => {
                                                                    setOperationFeedback(
                                                                        buildScheduleFeedback(
                                                                            page as unknown as RecurringBillingResultPage,
                                                                            actionContext,
                                                                        ),
                                                                    );
                                                                },
                                                                onError: (
                                                                    errors,
                                                                ) => {
                                                                    setOperationFeedback(
                                                                        buildScheduleErrorFeedback(
                                                                            actionContext,
                                                                            errors,
                                                                        ),
                                                                    );
                                                                },
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Activate
                                                </button>
                                            )}
                                            {schedule.can_pause && (
                                                <button
                                                    type="button"
                                                    className="font-medium text-primary"
                                                    onClick={() => {
                                                        const actionContext =
                                                            buildActionContext(
                                                                schedule,
                                                                'pause',
                                                            );

                                                        router.post(
                                                            `/company/projects/recurring-billing/${schedule.id}/pause`,
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                                onSuccess: (
                                                                    page,
                                                                ) => {
                                                                    setOperationFeedback(
                                                                        buildScheduleFeedback(
                                                                            page as unknown as RecurringBillingResultPage,
                                                                            actionContext,
                                                                        ),
                                                                    );
                                                                },
                                                                onError: (
                                                                    errors,
                                                                ) => {
                                                                    setOperationFeedback(
                                                                        buildScheduleErrorFeedback(
                                                                            actionContext,
                                                                            errors,
                                                                        ),
                                                                    );
                                                                },
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Pause
                                                </button>
                                            )}
                                            {schedule.can_cancel && (
                                                <button
                                                    type="button"
                                                    className="font-medium text-primary"
                                                    onClick={() =>
                                                        openCancelDialog(
                                                            schedule,
                                                        )
                                                    }
                                                >
                                                    Cancel
                                                </button>
                                            )}
                                            {schedule.can_edit && (
                                                <Link
                                                    href={`/company/projects/recurring-billing/${schedule.id}/edit`}
                                                    className="font-medium text-primary"
                                                >
                                                    Edit
                                                </Link>
                                            )}
                                            {schedule.can_open_project && (
                                                <Link
                                                    href={`/company/projects/${schedule.project_id}`}
                                                    className="font-medium text-primary"
                                                >
                                                    Open project
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {schedules.links.length > 1 && (
                    <div className="flex flex-wrap gap-2">
                        {schedules.links.map((link) => (
                            <Link
                                key={link.label}
                                href={link.url ?? '#'}
                                className={`rounded-md border px-3 py-1 text-sm ${
                                    link.active
                                        ? 'border-primary text-primary'
                                        : 'text-muted-foreground'
                                } ${
                                    !link.url
                                        ? 'pointer-events-none opacity-50'
                                        : ''
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            <ReasonDialog
                open={cancelDialog !== null}
                onOpenChange={closeCancelDialog}
                title={
                    cancelDialog
                        ? `Cancel ${cancelDialog.scheduleName}?`
                        : 'Cancel recurring schedule?'
                }
                description="This stops future recurring billing runs for the schedule. Add a note if the cancellation should be preserved in the activity trail."
                confirmLabel="Cancel schedule"
                processingLabel="Cancelling..."
                cancelLabel="Keep schedule"
                processing={cancelForm.processing}
                onConfirm={submitCancel}
                reason={cancelForm.data.reason}
                onReasonChange={(value) => cancelForm.setData('reason', value)}
                reasonLabel="Cancellation note"
                reasonPlaceholder="Optional note for why this schedule is being cancelled."
                reasonHelperText={
                    cancelDialog
                        ? `${cancelDialog.projectLabel}${
                              cancelDialog.nextRunOn
                                  ? ` | Next run ${cancelDialog.nextRunOn}`
                                  : ''
                          }`
                        : undefined
                }
                reasonError={cancelForm.errors.reason}
                errors={cancelForm.errors}
            />
        </AppLayout>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}
