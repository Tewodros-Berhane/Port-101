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
import {
    Building2,
    ClipboardList,
    LayoutGrid,
    ListChecks,
    Mail,
    Package,
    Scale,
    ShieldCheck,
    Tag,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

const companyNavItems: NavItem[] = [
    {
        title: 'Company Dashboard',
        href: '/company/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Invites',
        href: '/core/invites',
        icon: Mail,
        permission: 'core.users.manage',
    },
];

const masterDataNavItems: NavItem[] = [
    {
        title: 'Partners',
        href: '/core/partners',
        permission: 'core.partners.view',
        icon: Users,
    },
    {
        title: 'Contacts',
        href: '/core/contacts',
        permission: 'core.contacts.view',
        icon: UserCog,
    },
    {
        title: 'Addresses',
        href: '/core/addresses',
        permission: 'core.addresses.view',
        icon: Building2,
    },
    {
        title: 'Products',
        href: '/core/products',
        permission: 'core.products.view',
        icon: Package,
    },
    {
        title: 'Taxes',
        href: '/core/taxes',
        permission: 'core.taxes.view',
        icon: Scale,
    },
    {
        title: 'Currencies',
        href: '/core/currencies',
        permission: 'core.currencies.view',
        icon: Tag,
    },
    {
        title: 'Units',
        href: '/core/uoms',
        permission: 'core.uoms.view',
        icon: ListChecks,
    },
    {
        title: 'Price Lists',
        href: '/core/price-lists',
        permission: 'core.price_lists.view',
        icon: ClipboardList,
    },
];

const governanceNavItems: NavItem[] = [
    {
        title: 'Audit Logs',
        href: '/core/audit-logs',
        permission: 'core.audit_logs.view',
        icon: ShieldCheck,
    },
];

const platformAdminNavItems: NavItem[] = [
    {
        title: 'Platform Dashboard',
        href: '/platform/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Companies',
        href: '/platform/companies',
        icon: Building2,
    },
    {
        title: 'Platform Admins',
        href: '/platform/admin-users',
        icon: ShieldCheck,
    },
    {
        title: 'Invites',
        href: '/platform/invites',
        icon: Mail,
    },
];

const footerNavItems: NavItem[] = [];

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
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
