import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type AuditLog = {
    id: string;
    action: string;
    record_type: string;
    record_id: string;
    actor?: string | null;
    created_at?: string | null;
    change_keys: string[];
};

type Props = {
    logs: {
        data: AuditLog[];
        links: { url: string | null; label: string; active: boolean }[];
    };
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

export default function AuditLogsIndex({ logs }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.audit_logs.view');

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
            </div>

            {canView ? (
                <>
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
