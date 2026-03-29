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
    BarChart3,
    Bell,
    Building2,
    ClipboardCheck,
    ClipboardList,
    FileSpreadsheet,
    FolderKanban,
    Handshake,
    PlugZap,
    LayoutGrid,
    ListChecks,
    Mail,
    Package,
    Scale,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Tag,
    UserCog,
    UserRoundCheck,
    Users,
    Warehouse,
} from 'lucide-react';
import AppLogo from './app-logo';

const companyNavItems: NavItem[] = [
    {
        title: 'Company Dashboard',
        href: '/company/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Settings',
        href: '/company/settings',
        icon: Settings,
        permission: 'core.company.view',
    },
    {
        title: 'Users',
        href: '/company/users',
        icon: Users,
        permission: 'core.users.manage',
    },
    {
        title: 'Roles',
        href: '/company/roles',
        icon: ShieldCheck,
        permission: 'core.roles.view',
    },
    {
        title: 'Owner Invites',
        href: '/core/invites',
        icon: Mail,
        permission: 'core.users.manage',
    },
    {
        title: 'Notifications',
        href: '/core/notifications',
        icon: Bell,
        permission: 'core.notifications.view',
    },
];

const companyModuleNavItems: NavItem[] = [
    {
        title: 'Sales',
        href: '/company/sales',
        icon: Handshake,
        permission: 'sales.leads.view',
    },
    {
        title: 'Inventory',
        href: '/company/inventory',
        icon: Warehouse,
        permission: 'inventory.stock.view',
    },
    {
        title: 'Purchasing',
        href: '/company/purchasing',
        icon: ShoppingCart,
        permission: 'purchasing.rfq.view',
    },
    {
        title: 'Accounting',
        href: '/company/accounting',
        icon: FileSpreadsheet,
        permission: 'accounting.invoices.view',
    },
    {
        title: 'Projects',
        href: '/company/projects',
        icon: FolderKanban,
        permission: 'projects.projects.view',
    },
    {
        title: 'HR',
        href: '/company/hr',
        icon: UserRoundCheck,
        permission: 'hr.employees.view',
    },
    {
        title: 'Approvals',
        href: '/company/approvals',
        icon: ClipboardCheck,
        permission: 'approvals.requests.view',
    },
    {
        title: 'Reports',
        href: '/company/reports',
        icon: BarChart3,
        permission: 'reports.view',
    },
    {
        title: 'Integrations',
        href: '/company/integrations',
        icon: PlugZap,
        permission: 'integrations.webhooks.view',
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
        title: 'Reports',
        href: '/platform/reports',
        icon: BarChart3,
    },
    {
        title: 'Queue Health',
        href: '/platform/operations/queue-health',
        icon: ClipboardList,
    },
    {
        title: 'Governance',
        href: '/platform/governance',
        icon: Settings,
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
    {
        title: 'Notifications',
        href: '/core/notifications',
        icon: Bell,
        permission: 'core.notifications.view',
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth, notifications } = usePage<SharedData>().props;
    const isSuperAdmin = Boolean(auth?.user?.is_super_admin);
    const homeHref = isSuperAdmin
        ? '/platform/dashboard'
        : '/company/dashboard';
    const unreadNotifications = notifications?.unread_count ?? 0;
    const resolvedCompanyNavItems = companyNavItems.map((item) =>
        item.title === 'Notifications'
            ? {
                  ...item,
                  badge:
                      unreadNotifications > 99
                          ? '99+'
                          : unreadNotifications > 0
                            ? unreadNotifications
                            : null,
              }
            : item,
    );
    const resolvedPlatformNavItems = platformAdminNavItems.map((item) =>
        item.title === 'Notifications'
            ? {
                  ...item,
                  badge:
                      unreadNotifications > 99
                          ? '99+'
                          : unreadNotifications > 0
                            ? unreadNotifications
                            : null,
              }
            : item,
    );

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
                    <>
                        <NavMain
                            items={resolvedCompanyNavItems}
                            label="Company"
                        />
                        <NavMain
                            items={companyModuleNavItems}
                            label="Modules"
                        />
                    </>
                )}
                {isSuperAdmin && (
                    <NavMain
                        items={resolvedPlatformNavItems}
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
