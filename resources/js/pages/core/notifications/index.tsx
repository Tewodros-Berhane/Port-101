import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

type AppNotification = {
    id: string;
    type: string;
    title: string;
    message: string;
    url?: string | null;
    severity: 'low' | 'medium' | 'high' | 'critical';
    meta?: Record<string, unknown>;
    read_at?: string | null;
    created_at?: string | null;
};

type Props = {
    notifications: {
        data: AppNotification[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function NotificationsIndex({ notifications }: Props) {
    const { hasPermission } = usePermissions();
    const { auth } = usePage<SharedData>().props;
    const isSuperAdmin = Boolean(auth?.user?.is_super_admin);
    const canManage = hasPermission('core.notifications.manage');

    const markReadForm = useForm({});
    const markAllReadForm = useForm({});
    const deleteForm = useForm({});

    const handleMarkRead = (notificationId: string) => {
        markReadForm.post(`/core/notifications/${notificationId}/read`);
    };

    const handleMarkAllRead = () => {
        markAllReadForm.post('/core/notifications/mark-all-read');
    };

    const handleDelete = (notificationId: string) => {
        deleteForm.delete(`/core/notifications/${notificationId}`);
    };

    const unreadCount = notifications.data.filter(
        (notification) => !notification.read_at,
    ).length;

    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: isSuperAdmin ? 'Platform' : 'Company',
                    href: isSuperAdmin
                        ? '/platform/dashboard'
                        : '/company/dashboard',
                },
                { title: 'Notifications', href: '/core/notifications' },
            ]}
        >
            <Head title="Notifications" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Notifications</h1>
                    <p className="text-sm text-muted-foreground">
                        Operational events across company and governance flows.
                    </p>
                </div>
                {canManage && notifications.data.length > 0 && (
                    <Button
                        variant="outline"
                        type="button"
                        onClick={handleMarkAllRead}
                        disabled={markAllReadForm.processing}
                    >
                        Mark all read
                    </Button>
                )}
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-max text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">
                                Notification
                            </th>
                            <th className="px-4 py-3 font-medium">When</th>
                            <th className="px-4 py-3 font-medium">Severity</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            {canManage && (
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {notifications.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={canManage ? 5 : 4}
                                >
                                    No notifications yet.
                                </td>
                            </tr>
                        )}
                        {notifications.data.map((notification) => (
                            <tr
                                key={notification.id}
                                className={
                                    notification.read_at ? '' : 'bg-primary/5'
                                }
                            >
                                <td className="px-4 py-3">
                                    <div className="font-medium">
                                        {notification.title}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {notification.message}
                                    </div>
                                    {notification.url && (
                                        <Link
                                            href={notification.url}
                                            className="text-xs text-primary"
                                        >
                                            Open related page
                                        </Link>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    {formatDate(notification.created_at)}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {notification.severity}
                                </td>
                                <td className="px-4 py-3">
                                    {notification.read_at ? 'Read' : 'Unread'}
                                </td>
                                {canManage && (
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-2">
                                            {!notification.read_at && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleMarkRead(
                                                            notification.id,
                                                        )
                                                    }
                                                    disabled={
                                                        markReadForm.processing
                                                    }
                                                >
                                                    Mark read
                                                </Button>
                                            )}
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                onClick={() =>
                                                    handleDelete(
                                                        notification.id,
                                                    )
                                                }
                                                disabled={deleteForm.processing}
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 text-sm text-muted-foreground">
                Unread on this page: {unreadCount}
            </div>

            {notifications.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {notifications.links.map((link) => (
                        <Link
                            key={link.label}
                            href={link.url ?? '#'}
                            className={`rounded-md border px-3 py-1 text-sm ${
                                link.active
                                    ? 'border-primary text-primary'
                                    : 'text-muted-foreground'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
