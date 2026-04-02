import type {
    PublicFooterGroup,
    PublicNavLink,
} from '@/components/public/public-shell';

export const publicNavLinks: PublicNavLink[] = [
    { label: 'Product', href: '/#product-preview' },
    { label: 'Modules', href: '/#modules' },
    { label: 'Teams', href: '/#teams' },
    { label: 'Controls', href: '/#controls' },
    { label: 'Trust', href: '/#security' },
    { label: 'FAQ', href: '/#faq' },
];

export const publicFooterGroups: PublicFooterGroup[] = [
    {
        title: 'Product',
        links: [
            { label: 'Overview', href: '/#top' },
            { label: 'Product preview', href: '/#product-preview' },
            { label: 'Modules', href: '/#modules' },
        ],
    },
    {
        title: 'Teams',
        links: [
            { label: 'Teams', href: '/#teams' },
            { label: 'Workflow controls', href: '/#controls' },
            { label: 'Proof model', href: '/#proof' },
        ],
    },
    {
        title: 'Trust',
        links: [
            { label: 'Security', href: '/#security' },
            { label: 'FAQ', href: '/#faq' },
        ],
    },
    {
        title: 'Access',
        links: [
            { label: 'Book demo', href: '/book-demo' },
            { label: 'Contact sales', href: '/contact-sales' },
            { label: 'Sign in', href: '/login' },
        ],
    },
];
