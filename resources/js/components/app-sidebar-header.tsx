import { usePage } from '@inertiajs/react';
import { AppCommandPalette } from '@/components/app-command-palette';
import { AppNotificationsPanel } from '@/components/app-notifications-panel';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { CompanySwitcher } from '@/components/company-switcher';
import ThemeToggle from '@/components/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { findNavigationItem } from '@/lib/app-navigation';
import type { BreadcrumbItem as BreadcrumbItemType, SharedData } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage<SharedData>();
    const isSuperAdmin = Boolean(page.props.auth?.user?.is_super_admin);
    const unreadNotifications = page.props.notifications?.unread_count ?? 0;
    const currentPath = new URL(page.url, 'http://localhost').pathname;
    const currentNavigationItem = findNavigationItem(currentPath, {
        isSuperAdmin,
        unreadNotifications,
    });
    const currentTitle =
        breadcrumbs.at(-1)?.title ??
        currentNavigationItem?.title ??
        (isSuperAdmin ? 'Platform' : 'Workspace');
    const resolvedBreadcrumbs =
        breadcrumbs.length > 0
            ? breadcrumbs
            : [
                  {
                      title: currentTitle,
                      href: currentPath,
                  },
              ];

    return (
        <header className="sticky top-0 z-20 border-b border-[color:var(--border-subtle)] bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <div className="flex min-h-16 items-center justify-between gap-4 px-6 py-3 md:px-4">
                <div className="flex min-w-0 items-center gap-3">
                    <SidebarTrigger className="shrink-0" />
                    <div className="hidden min-w-0 md:block">
                        <Breadcrumbs breadcrumbs={resolvedBreadcrumbs} />
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <ThemeToggle compact />
                    <AppCommandPalette />
                    <AppNotificationsPanel />
                    <div className="hidden md:block">
                        <CompanySwitcher />
                    </div>
                </div>
            </div>
        </header>
    );
}
