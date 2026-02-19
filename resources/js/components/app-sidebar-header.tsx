import { Breadcrumbs } from '@/components/breadcrumbs';
import { CompanySwitcher } from '@/components/company-switcher';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage<SharedData>();
    const unreadNotifications = page.props.notifications?.unread_count ?? 0;
    const canViewNotifications = (
        page.props.permissions ?? []
    ).includes('core.notifications.view');

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2">
                {canViewNotifications && (
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href="/core/notifications"
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
                        </Link>
                    </Button>
                )}
                <CompanySwitcher />
            </div>
        </header>
    );
}
