import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { buildBreadcrumbs } from '@/lib/page-navigation';
import type { SharedData } from '@/types';

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
    filters: {
        search?: string;
        notification?: string;
    };
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function NotificationsIndex({ notifications, filters }: Props) {
    const { hasPermission } = usePermissions();
    const { auth } = usePage<SharedData>().props;
    const isSuperAdmin = Boolean(auth?.user?.is_super_admin);
    const canManage = hasPermission('core.notifications.manage');
    const notificationsBasePath = isSuperAdmin
        ? '/platform/notifications'
        : '/core/notifications';
    const [search, setSearch] = useState(filters.search ?? '');

    const markReadForm = useForm({});
    const markAllReadForm = useForm({});
    const deleteForm = useForm({});
    const queryParams = new URLSearchParams();

    if (filters.search) {
        queryParams.set('search', filters.search);
    }

    if (filters.notification) {
        queryParams.set('notification', filters.notification);
    }

    const querySuffix = queryParams.toString() ? `?${queryParams.toString()}` : '';

    const applySearch = () => {
        const nextSearch = search.trim();

        router.get(
            notificationsBasePath,
            nextSearch ? { search: nextSearch } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const clearSearch = () => {
        setSearch('');
        router.get(
            notificationsBasePath,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const handleMarkRead = (notificationId: string) => {
        markReadForm.post(`${notificationsBasePath}/${notificationId}/read${querySuffix}`);
    };

    const handleMarkAllRead = () => {
        markAllReadForm.post(`${notificationsBasePath}/mark-all-read${querySuffix}`);
    };

    const handleDelete = (notificationId: string) => {
        deleteForm.delete(`${notificationsBasePath}/${notificationId}${querySuffix}`);
    };

    const unreadCount = notifications.data.filter(
        (notification) => !notification.read_at,
    ).length;

    return (
        <AppLayout
            breadcrumbs={buildBreadcrumbs(
                {
                    title: isSuperAdmin ? 'Platform' : 'Company',
                    href: isSuperAdmin
                        ? '/platform/dashboard'
                        : '/company/dashboard',
                },
                { title: 'Notifications', href: notificationsBasePath },
            )}
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

            <div className="mt-6 flex flex-col gap-3 rounded-xl border border-[color:var(--border-default)] bg-card p-4 md:flex-row md:items-end md:justify-between">
                <div className="min-w-0 flex-1">
                    <label
                        htmlFor="notifications-search"
                        className="text-xs font-medium uppercase tracking-[0.16em] text-[color:var(--text-muted)]"
                    >
                        Search notifications
                    </label>
                    <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                        <Input
                            id="notifications-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    applySearch();
                                }
                            }}
                            placeholder="Search by title, message, type, or notification ID"
                            className="sm:max-w-xl"
                        />
                        <div className="flex gap-2">
                            <Button type="button" onClick={applySearch}>
                                Search
                            </Button>
                            {(filters.search || filters.notification) && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={clearSearch}
                                >
                                    Clear
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
                <div className="text-xs text-[color:var(--text-muted)]">
                    {filters.notification
                        ? 'Showing the notification selected from the header list.'
                        : 'Search stays scoped to your current company or platform access.'}
                </div>
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
