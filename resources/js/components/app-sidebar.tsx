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
} from '@/components/ui/sidebar';
import type { NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid } from 'lucide-react';
import AppLogo from './app-logo';

const companyNavItems: NavItem[] = [
    {
        title: 'Company Dashboard',
        href: '/company/dashboard',
        icon: LayoutGrid,
    },
];

const masterDataNavItems: NavItem[] = [
    {
        title: 'Partners',
        href: '/core/partners',
        permission: 'core.partners.view',
    },
    {
        title: 'Contacts',
        href: '/core/contacts',
        permission: 'core.contacts.view',
    },
    {
        title: 'Addresses',
        href: '/core/addresses',
        permission: 'core.addresses.view',
    },
    {
        title: 'Products',
        href: '/core/products',
        permission: 'core.products.view',
    },
    {
        title: 'Taxes',
        href: '/core/taxes',
        permission: 'core.taxes.view',
    },
    {
        title: 'Currencies',
        href: '/core/currencies',
        permission: 'core.currencies.view',
    },
    {
        title: 'Units',
        href: '/core/uoms',
        permission: 'core.uoms.view',
    },
    {
        title: 'Price Lists',
        href: '/core/price-lists',
        permission: 'core.price_lists.view',
    },
];

const governanceNavItems: NavItem[] = [
    {
        title: 'Audit Logs',
        href: '/core/audit-logs',
        permission: 'core.audit_logs.view',
    },
];

const platformAdminNavItems: NavItem[] = [
    {
        title: 'Platform Dashboard',
        href: '/platform/dashboard',
    },
    {
        title: 'Companies',
        href: '/platform/companies',
    },
    {
        title: 'Platform Admins',
        href: '/platform/admin-users',
    },
    {
        title: 'Invites',
        href: '/platform/invites',
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const isSuperAdmin = Boolean(auth?.user?.is_super_admin);
    const homeHref = isSuperAdmin
        ? '/platform/dashboard'
        : '/company/dashboard';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {!isSuperAdmin && (
                    <NavMain items={companyNavItems} label="Company" />
                )}
                {isSuperAdmin && (
                    <NavMain
                        items={platformAdminNavItems}
                        label="Platform Admin"
                    />
                )}
                <NavMain items={masterDataNavItems} label="Master Data" />
                <NavMain items={governanceNavItems} label="Governance" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
