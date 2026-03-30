import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { StatusBadge } from '@/components/ui/status-badge';
import type { SharedData } from '@/types';

const SEVERITY_STATUS: Record<string, string> = {
    error: 'failed',
    info: 'in_progress',
    success: 'approved',
    warning: 'pending',
};

const formatNotificationDate = (value?: string | null) => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const elapsed = Date.now() - date.getTime();
    const minute = 60_000;
    const hour = 60 * minute;
    const day = 24 * hour;

    if (elapsed < minute) {
        return 'Just now';
    }

    if (elapsed < hour) {
        return `${Math.floor(elapsed / minute)}m ago`;
    }

    if (elapsed < day) {
        return `${Math.floor(elapsed / hour)}h ago`;
    }

    return date.toLocaleString();
};

export function AppNotificationsPanel() {
    const page = usePage<SharedData>();
    const unreadNotifications = page.props.notifications?.unread_count ?? 0;
    const recentNotifications = page.props.notifications?.recent ?? [];
    const canViewNotifications = (page.props.permissions ?? []).includes(
        'core.notifications.view',
    );

    if (!canViewNotifications) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="relative inline-flex size-10 items-center justify-center rounded-[var(--radius-control)] border border-[color:var(--border-default)] bg-card/80 text-[color:var(--text-secondary)] shadow-[var(--shadow-xs)] transition-[background-color,border-color,color,box-shadow] duration-150 hover:border-[color:var(--border-strong)] hover:bg-card hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    aria-label="Notifications"
                >
                    <Bell className="size-4" />
                    {unreadNotifications > 0 && (
                        <span className="absolute -top-1 -right-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold leading-none text-primary-foreground shadow-[var(--shadow-xs)]">
                            {unreadNotifications > 99 ? '99+' : unreadNotifications}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[25rem] max-w-[calc(100vw-1rem)] overflow-hidden p-0"
            >
                <DropdownMenuLabel className="border-b border-[color:var(--border-subtle)] px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <div className="text-sm font-semibold text-foreground">Notifications</div>
                            <div className="mt-0.5 text-xs font-normal text-[color:var(--text-muted)]">
                                {unreadNotifications > 0
                                    ? `${unreadNotifications} unread notifications`
                                    : 'No unread notifications'}
                            </div>
                        </div>
                        <Link
                            href="/core/notifications"
                            className="text-xs font-medium text-primary transition-colors hover:text-[var(--action-primary-hover)]"
                        >
                            View all
                        </Link>
                    </div>
                </DropdownMenuLabel>
                <div className="max-h-[26rem] overflow-y-auto px-2 py-2">
                    {recentNotifications.length === 0 ? (
                        <div className="rounded-[var(--radius-panel)] border border-dashed border-[color:var(--border-default)] bg-[color:var(--bg-surface-muted)] px-4 py-8 text-center">
                            <p className="text-sm font-medium text-foreground">All caught up</p>
                            <p className="mt-1 text-xs text-[color:var(--text-muted)]">
                                New operational alerts and workflow events will appear here.
                            </p>
                        </div>
                    ) : (
                        recentNotifications.map((notification) => (
                            <DropdownMenuItem
                                key={notification.id}
                                asChild
                                className="rounded-[var(--radius-panel)] p-0 focus:bg-transparent"
                            >
                                <Link
                                    href={notification.url ?? '/core/notifications'}
                                    className="block w-full rounded-[var(--radius-panel)] border border-transparent px-3 py-3 transition-[background-color,border-color] hover:border-[color:var(--border-subtle)] hover:bg-[color:var(--bg-surface-muted)]"
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <p className="truncate text-sm font-medium text-foreground">
                                                    {notification.title}
                                                </p>
                                                {!notification.read_at && (
                                                    <span className="inline-flex size-2.5 shrink-0 rounded-full bg-primary" />
                                                )}
                                            </div>
                                            {notification.message && (
                                                <p className="mt-1 line-clamp-2 text-xs leading-5 text-[color:var(--text-secondary)]">
                                                    {notification.message}
                                                </p>
                                            )}
                                            <div className="mt-2 flex items-center gap-2">
                                                <StatusBadge
                                                    status={
                                                        SEVERITY_STATUS[
                                                            notification.severity
                                                                ?.toLowerCase()
                                                                ?.trim() ?? 'info'
                                                        ] ?? 'in_progress'
                                                    }
                                                    label={notification.severity}
                                                    className="px-2 py-0.5 text-[10px]"
                                                />
                                                <span className="text-[11px] text-[color:var(--text-muted)]">
                                                    {formatNotificationDate(
                                                        notification.created_at,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            </DropdownMenuItem>
                        ))
                    )}
                </div>
                <DropdownMenuSeparator className="my-0" />
                <div className="px-3 py-3 text-[11px] text-[color:var(--text-muted)]">
                    Notifications respect the current company and permission scope.
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
