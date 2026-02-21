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

const formatNotificationDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

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
                        <DropdownMenuContent align="end" className="w-80 p-0">
                            <DropdownMenuLabel className="space-y-0.5 px-3 py-2">
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
                            <div className="max-h-80 overflow-y-auto p-1">
                                {recentNotifications.length === 0 && (
                                    <p className="px-2 py-3 text-xs text-muted-foreground">
                                        No notifications yet.
                                    </p>
                                )}
                                {recentNotifications.map((notification) => (
                                    <DropdownMenuItem
                                        key={notification.id}
                                        asChild
                                        className="items-start px-2 py-2"
                                    >
                                        <Link
                                            href={
                                                notification.url ??
                                                '/core/notifications'
                                            }
                                            className="block w-full min-w-0"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <span className="truncate text-sm font-medium">
                                                    {notification.title}
                                                </span>
                                                {!notification.read_at && (
                                                    <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                                )}
                                            </div>
                                            {notification.message && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {notification.message}
                                                </p>
                                            )}
                                            <p className="mt-1 text-[11px] text-muted-foreground">
                                                {formatNotificationDate(
                                                    notification.created_at,
                                                )}
                                            </p>
                                        </Link>
                                    </DropdownMenuItem>
                                ))}
                            </div>
                            <DropdownMenuSeparator className="my-0" />
                            <DropdownMenuItem asChild className="rounded-none">
                                <Link
                                    href="/core/notifications"
                                    className="block w-full px-2 py-1.5 text-sm font-medium"
                                >
                                    See all
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
