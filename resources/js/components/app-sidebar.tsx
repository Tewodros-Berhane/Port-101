import { Link, usePage } from '@inertiajs/react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { getSidebarSections } from '@/lib/app-navigation';
import type { NavItem, SharedData } from '@/types';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth, notifications } = usePage<SharedData>().props;
    const isSuperAdmin = Boolean(auth?.user?.is_super_admin);
    const homeHref = isSuperAdmin
        ? '/platform/dashboard'
        : '/company/dashboard';
    const unreadNotifications = notifications?.unread_count ?? 0;
    const sidebarSections = getSidebarSections({
        isSuperAdmin,
        unreadNotifications,
    });

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto rounded-[var(--radius-hero)] px-3 py-3 group-data-[collapsible=icon]:size-12! group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0! group-data-[collapsible=icon]:[&>div:last-child]:hidden"
                        >
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarSeparator />

            <SidebarContent>
                {sidebarSections.map((section) => (
                    <NavMain
                        key={section.label}
                        items={section.items}
                        label={section.label}
                    />
                ))}
            </SidebarContent>

            <SidebarSeparator />
            <SidebarFooter>
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
