import { Breadcrumbs } from '@/components/breadcrumbs';
import { CompanySwitcher } from '@/components/company-switcher';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { logout } from '@/routes';
import type { BreadcrumbItem as BreadcrumbItemType, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, LogOut } from 'lucide-react';

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

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage<SharedData>();
    const unreadNotifications = page.props.notifications?.unread_count ?? 0;
    const recentNotifications = page.props.notifications?.recent ?? [];
    const canViewNotifications = (page.props.permissions ?? []).includes(
        'core.notifications.view',
    );

    const handleLogout = () => {
        router.flushAll();
    };

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2">
                {canViewNotifications && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="relative"
                                aria-label="Notifications"
                            >
                                <Bell className="h-4 w-4" />
                                {unreadNotifications > 0 && (
                                    <span className="absolute -top-1.5 -right-1.5 min-w-4 rounded-full bg-primary px-1 text-center text-[10px] leading-4 text-primary-foreground">
                                        {unreadNotifications > 99
                                            ? '99+'
                                            : unreadNotifications}
                                    </span>
                                )}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="w-[22rem] max-w-[calc(100vw-1rem)] overflow-hidden p-0"
                        >
                            <DropdownMenuLabel className="space-y-0.5 px-4 py-3">
                                <div className="text-sm font-semibold">
                                    Notifications
                                </div>
                                <div className="text-xs font-normal text-muted-foreground">
                                    {unreadNotifications > 0
                                        ? `${unreadNotifications} unread`
                                        : 'All caught up'}
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator className="my-0" />
                            <div className="max-h-96 space-y-2 overflow-x-hidden overflow-y-auto px-2 py-2">
                                {recentNotifications.length === 0 && (
                                    <p className="px-2 py-3 text-xs text-muted-foreground">
                                        No notifications yet.
                                    </p>
                                )}
                                {recentNotifications.map((notification) => (
                                    <DropdownMenuItem
                                        key={notification.id}
                                        asChild
                                        className="cursor-pointer rounded-lg p-0 focus:bg-transparent"
                                    >
                                        <Link
                                            href={
                                                notification.url ??
                                                '/core/notifications'
                                            }
                                            className="block w-full rounded-lg border border-border/60 bg-background/60 px-3 py-2.5 transition-colors hover:bg-accent/35"
                                        >
                                            <div className="flex items-start gap-2">
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm leading-5 font-medium">
                                                        {notification.title}
                                                    </p>
                                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                                        {formatNotificationDate(
                                                            notification.created_at,
                                                        )}
                                                    </p>
                                                </div>
                                                {!notification.read_at && (
                                                    <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                                )}
                                            </div>
                                        </Link>
                                    </DropdownMenuItem>
                                ))}
                            </div>
                            <DropdownMenuSeparator className="my-0" />
                            <DropdownMenuItem asChild className="m-2 mb-2 p-0">
                                <Link
                                    href="/core/notifications"
                                    className="block w-full rounded-md border border-border/60 px-3 py-2 text-center text-sm font-medium"
                                >
                                    See all notifications
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
                <Button variant="outline" size="sm" asChild>
                    <Link
                        href={logout()}
                        as="button"
                        onClick={handleLogout}
                        data-test="logout-button"
                    >
                        <LogOut className="h-4 w-4" />
                        <span className="hidden sm:inline">Log out</span>
                    </Link>
                </Button>
                <CompanySwitcher />
            </div>
        </header>
    );
}
