import type { BreadcrumbItem } from '@/types';

const COMPANY_ROOT = { title: 'Company', href: '/company/dashboard' } as const;
const PLATFORM_ROOT = {
    title: 'Platform',
    href: '/platform/dashboard',
} as const;
const MASTER_DATA_ROOT = {
    title: 'Master Data',
    href: '/core/partners',
} as const;

export function buildBreadcrumbs(
    ...items: BreadcrumbItem[]
): BreadcrumbItem[] {
    return items;
}

export const companyModuleLinks = {
    sales: { title: 'Sales', href: '/company/sales' },
    purchasing: { title: 'Purchasing', href: '/company/purchasing' },
    accounting: { title: 'Accounting', href: '/company/accounting' },
    inventory: { title: 'Inventory', href: '/company/inventory' },
    projects: { title: 'Projects', href: '/company/projects' },
    hr: { title: 'HR', href: '/company/hr' },
    integrations: { title: 'Integrations', href: '/company/integrations' },
} satisfies Record<string, BreadcrumbItem>;

export function companyBreadcrumbs(
    ...items: BreadcrumbItem[]
): BreadcrumbItem[] {
    return appendBreadcrumbs(COMPANY_ROOT, items);
}

export function moduleBreadcrumbs(
    module: BreadcrumbItem,
    ...items: BreadcrumbItem[]
): BreadcrumbItem[] {
    return [module, ...items];
}

function appendBreadcrumbs(
    root: BreadcrumbItem,
    items: BreadcrumbItem[],
): BreadcrumbItem[] {
    return [root, ...items];
}

export function companyModuleBreadcrumbs(
    module: BreadcrumbItem,
    ...items: BreadcrumbItem[]
): BreadcrumbItem[] {
    return appendBreadcrumbs(COMPANY_ROOT, [module, ...items]);
}

export function platformBreadcrumbs(
    ...items: BreadcrumbItem[]
): BreadcrumbItem[] {
    return appendBreadcrumbs(PLATFORM_ROOT, items);
}

export function masterDataBreadcrumbs(
    ...items: BreadcrumbItem[]
): BreadcrumbItem[] {
    return appendBreadcrumbs(MASTER_DATA_ROOT, items);
}
