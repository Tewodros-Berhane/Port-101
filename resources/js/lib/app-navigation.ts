import {
    BarChart3,
    Bell,
    Building2,
    ClipboardCheck,
    ClipboardList,
    FileSpreadsheet,
    FolderKanban,
    Handshake,
    LayoutGrid,
    ListChecks,
    Mail,
    Package,
    PlugZap,
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
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export type AppNavSection = {
    label: string;
    items: NavItem[];
};

export type AppCommandItem = NavItem & {
    group: string;
    description?: string;
    keywords?: string[];
};

const companyWorkspaceNavItems: NavItem[] = [
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
        title: 'Notifications',
        href: '/core/notifications',
        icon: Bell,
        permission: 'core.notifications.view',
    },
];

const companyOperationsNavItems: NavItem[] = [
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

const companyGovernanceNavItems: NavItem[] = [
    {
        title: 'Owner Invites',
        href: '/core/invites',
        icon: Mail,
        permission: 'core.users.manage',
    },
    {
        title: 'Audit Logs',
        href: '/core/audit-logs',
        permission: 'core.audit_logs.view',
        icon: ShieldCheck,
    },
];

const platformGovernanceNavItems: NavItem[] = [
    {
        title: 'Audit Logs',
        href: '/core/audit-logs',
        permission: 'core.audit_logs.view',
        icon: ShieldCheck,
    },
];

const platformNavItems: NavItem[] = [
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
        title: 'Contact Requests',
        href: '/platform/contact-requests',
        icon: Mail,
    },
    {
        title: 'Notifications',
        href: '/core/notifications',
        icon: Bell,
        permission: 'core.notifications.view',
    },
];

const companyCreateCommands: AppCommandItem[] = [
    {
        title: 'New lead',
        href: '/company/sales/leads/create',
        icon: Handshake,
        permission: 'sales.leads.manage',
        group: 'Create',
        description: 'Start a new sales lead',
        keywords: ['crm', 'lead', 'pipeline'],
    },
    {
        title: 'New quote',
        href: '/company/sales/quotes/create',
        icon: ClipboardList,
        permission: 'sales.quotes.manage',
        group: 'Create',
        description: 'Create a customer quote',
        keywords: ['sales', 'estimate', 'quotation'],
    },
    {
        title: 'New order',
        href: '/company/sales/orders/create',
        icon: ShoppingCart,
        permission: 'sales.orders.manage',
        group: 'Create',
        description: 'Create a sales order',
        keywords: ['sales', 'order'],
    },
    {
        title: 'New stock move',
        href: '/company/inventory/moves/create',
        icon: Warehouse,
        permission: 'inventory.moves.manage',
        group: 'Create',
        description: 'Record an inventory move',
        keywords: ['inventory', 'transfer', 'receipt', 'delivery'],
    },
    {
        title: 'New RFQ',
        href: '/company/purchasing/rfqs/create',
        icon: ClipboardList,
        permission: 'purchasing.rfq.manage',
        group: 'Create',
        description: 'Create a request for quotation',
        keywords: ['procurement', 'vendor', 'rfq'],
    },
    {
        title: 'New purchase order',
        href: '/company/purchasing/orders/create',
        icon: ShoppingCart,
        permission: 'purchasing.po.manage',
        group: 'Create',
        description: 'Create a purchase order',
        keywords: ['procurement', 'po', 'vendor'],
    },
    {
        title: 'New invoice',
        href: '/company/accounting/invoices/create',
        icon: FileSpreadsheet,
        permission: 'accounting.invoices.manage',
        group: 'Create',
        description: 'Create an invoice or bill',
        keywords: ['finance', 'invoice', 'billing'],
    },
    {
        title: 'New payment',
        href: '/company/accounting/payments/create',
        icon: FileSpreadsheet,
        permission: 'accounting.payments.manage',
        group: 'Create',
        description: 'Record a payment',
        keywords: ['finance', 'payment'],
    },
    {
        title: 'New project',
        href: '/company/projects/create',
        icon: FolderKanban,
        permission: 'projects.projects.manage',
        group: 'Create',
        description: 'Create a project workspace',
        keywords: ['project', 'services'],
    },
    {
        title: 'New employee',
        href: '/company/hr/employees/create',
        icon: UserRoundCheck,
        permission: 'hr.employees.manage',
        group: 'Create',
        description: 'Create an employee record',
        keywords: ['hr', 'people', 'staff'],
    },
    {
        title: 'New reimbursement claim',
        href: '/company/hr/reimbursements/claims/create',
        icon: ClipboardCheck,
        permission: 'hr.reimbursements.manage',
        group: 'Create',
        description: 'Create an expense claim',
        keywords: ['expense', 'claim', 'reimbursement'],
    },
    {
        title: 'New partner',
        href: '/core/partners/create',
        icon: Users,
        permission: 'core.partners.manage',
        group: 'Create',
        description: 'Create a partner record',
        keywords: ['customer', 'vendor', 'partner'],
    },
    {
        title: 'New product',
        href: '/core/products/create',
        icon: Package,
        permission: 'core.products.manage',
        group: 'Create',
        description: 'Create a product',
        keywords: ['item', 'sku', 'inventory'],
    },
    {
        title: 'New webhook endpoint',
        href: '/company/integrations/webhooks/create',
        icon: PlugZap,
        permission: 'integrations.webhooks.manage',
        group: 'Create',
        description: 'Register an outbound webhook',
        keywords: ['integration', 'webhook', 'endpoint'],
    },
];

const platformCreateCommands: AppCommandItem[] = [
    {
        title: 'New company',
        href: '/platform/companies/create',
        icon: Building2,
        group: 'Create',
        description: 'Create a tenant company',
        keywords: ['tenant', 'company'],
    },
    {
        title: 'New platform admin',
        href: '/platform/admin-users/create',
        icon: ShieldCheck,
        group: 'Create',
        description: 'Invite a platform administrator',
        keywords: ['admin', 'platform'],
    },
];

function withNotificationBadge(items: NavItem[], unreadNotifications: number) {
    return items.map((item) =>
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
}

export function getSidebarSections({
    isSuperAdmin,
    unreadNotifications = 0,
}: {
    isSuperAdmin: boolean;
    unreadNotifications?: number;
}): AppNavSection[] {
    if (isSuperAdmin) {
        return [
            {
                label: 'Platform',
                items: withNotificationBadge(
                    platformNavItems,
                    unreadNotifications,
                ),
            },
            { label: 'Master Data', items: masterDataNavItems },
            { label: 'Governance', items: platformGovernanceNavItems },
        ];
    }

    return [
        {
            label: 'Workspace',
            items: withNotificationBadge(
                companyWorkspaceNavItems,
                unreadNotifications,
            ),
        },
        {
            label: 'Operations',
            items: companyOperationsNavItems,
        },
        { label: 'Master Data', items: masterDataNavItems },
        { label: 'Governance', items: companyGovernanceNavItems },
    ];
}

export function getCommandItems({
    isSuperAdmin,
    unreadNotifications = 0,
}: {
    isSuperAdmin: boolean;
    unreadNotifications?: number;
}): AppCommandItem[] {
    const sections = getSidebarSections({
        isSuperAdmin,
        unreadNotifications,
    });

    const navigationItems = sections.flatMap((section) =>
        section.items.map((item) => ({
            ...item,
            group: section.label,
            description: `Open ${item.title}`,
            keywords: [section.label.toLowerCase(), item.title.toLowerCase()],
        })),
    );

    return [
        ...navigationItems,
        ...(isSuperAdmin ? platformCreateCommands : companyCreateCommands),
    ];
}

export function findNavigationItem(
    currentPath: string,
    options: {
        isSuperAdmin: boolean;
        unreadNotifications?: number;
    },
): AppCommandItem | null {
    const items = getCommandItems(options);

    const exactMatch = items.find((item) => item.href === currentPath);

    if (exactMatch) {
        return exactMatch;
    }

    const prefixedMatch = items
        .filter((item) =>
            currentPath === toUrl(item.href) ||
            currentPath.startsWith(`${toUrl(item.href)}/`),
        )
        .sort((left, right) => toUrl(right.href).length - toUrl(left.href).length)[0];

    return prefixedMatch ?? null;
}
