import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type AuditLog = {
    id: string;
    action: string;
    record_type: string;
    record_id: string;
    actor?: string | null;
    created_at?: string | null;
    change_keys: string[];
};

type FilterOption = {
    value: string;
    label: string;
};

type ActorOption = {
    id: string;
    name: string;
};

type Filters = {
    action?: string | null;
    record_type?: string | null;
    actor_id?: string | null;
    start_date?: string | null;
    end_date?: string | null;
};

type Props = {
    logs: {
        data: AuditLog[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Filters;
    actions: string[];
    recordTypes: FilterOption[];
    actors: ActorOption[];
};

const formatAction = (action: string) =>
    action ? action.charAt(0).toUpperCase() + action.slice(1) : '—';

const formatChanges = (keys: string[]) => {
    if (!keys || keys.length === 0) {
        return '—';
    }

    const preview = keys.slice(0, 3).join(', ');
    const remaining = keys.length - 3;

    return remaining > 0 ? `${preview} +${remaining}` : preview;
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

const buildQueryString = (filters: Filters) => {
    const params = new URLSearchParams();

    if (filters.action) {
        params.set('action', filters.action);
    }

    if (filters.record_type) {
        params.set('record_type', filters.record_type);
    }

    if (filters.actor_id) {
        params.set('actor_id', filters.actor_id);
    }

    if (filters.start_date) {
        params.set('start_date', filters.start_date);
    }

    if (filters.end_date) {
        params.set('end_date', filters.end_date);
    }

    return params.toString();
};

export default function AuditLogsIndex({
    logs,
    filters,
    actions,
    recordTypes,
    actors,
}: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.audit_logs.view');
    const canManage = hasPermission('core.audit_logs.manage');
    const form = useForm({
        action: filters.action ?? '',
        record_type: filters.record_type ?? '',
        actor_id: filters.actor_id ?? '',
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
    });

    const exportQuery = buildQueryString(form.data);
    const exportBaseUrl = '/core/audit-logs/export';
    const exportCsvUrl = exportQuery
        ? `${exportBaseUrl}?${exportQuery}&format=csv`
        : `${exportBaseUrl}?format=csv`;
    const exportJsonUrl = exportQuery
        ? `${exportBaseUrl}?${exportQuery}&format=json`
        : `${exportBaseUrl}?format=json`;

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Audit Logs', href: '/core/audit-logs' }]}
        >
            <Head title="Audit Logs" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Audit logs</h1>
                    <p className="text-sm text-muted-foreground">
                        Review recent changes across master data.
                    </p>
                </div>
                {canManage && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <a href={exportCsvUrl}>Export CSV</a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={exportJsonUrl}>Export JSON</a>
                        </Button>
                    </div>
                )}
            </div>

            {canView ? (
                <>
                    <form
                        className="mt-6 rounded-xl border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.get('/core/audit-logs', {
                                preserveState: true,
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="grid gap-4 md:grid-cols-5">
                            <div className="grid gap-2">
                                <Label htmlFor="action">Action</Label>
                                <select
                                    id="action"
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                    value={form.data.action}
                                    onChange={(event) =>
                                        form.setData(
                                            'action',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">All actions</option>
                                    {actions.map((action) => (
                                        <option key={action} value={action}>
                                            {formatAction(action)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="record_type">Record type</Label>
                                <select
                                    id="record_type"
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                    value={form.data.record_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'record_type',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">All types</option>
                                    {recordTypes.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="actor_id">Actor</Label>
                                <select
                                    id="actor_id"
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                    value={form.data.actor_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'actor_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">All actors</option>
                                    {actors.map((actor) => (
                                        <option key={actor.id} value={actor.id}>
                                            {actor.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="start_date">Start date</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'start_date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="end_date">End date</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={form.data.end_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'end_date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <Button type="submit" disabled={form.processing}>
                                Apply filters
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href="/core/audit-logs">Reset</Link>
                            </Button>
                        </div>
                    </form>

                    <div className="mt-6 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        When
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Action
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Record
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Actor
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Changes
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {logs.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={5}
                                        >
                                            No audit entries yet.
                                        </td>
                                    </tr>
                                )}
                                {logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td className="px-4 py-3">
                                            {formatDate(log.created_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatAction(log.action)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="font-medium">
                                                {log.record_type}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {' '}
                                                {log.record_id}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {log.actor ?? 'System'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatChanges(log.change_keys)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {logs.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {logs.links.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.url ?? '#'}
                                    className={`rounded-md border px-3 py-1 text-sm ${
                                        link.active
                                            ? 'border-primary text-primary'
                                            : 'text-muted-foreground'
                                    } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div className="mt-6 rounded-xl border p-6 text-sm text-muted-foreground">
                    You do not have access to view audit logs.
                </div>
            )}
        </AppLayout>
    );
}
