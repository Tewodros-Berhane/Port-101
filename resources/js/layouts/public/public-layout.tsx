import type { PropsWithChildren } from 'react';
import PublicFooter from '@/components/public/public-footer';
import PublicHeader from '@/components/public/public-header';
import type {
    PublicFooterGroup,
    PublicHref,
    PublicNavLink,
} from '@/components/public/public-shell';

export default function PublicLayout({
    children,
    navLinks,
    footerGroups,
    isAuthenticated,
    dashboardHref,
}: PropsWithChildren<{
    navLinks: PublicNavLink[];
    footerGroups: PublicFooterGroup[];
    isAuthenticated: boolean;
    dashboardHref: PublicHref;
}>) {
    return (
        <div id="top" className="min-h-screen bg-background text-foreground">
            <PublicHeader
                navLinks={navLinks}
                isAuthenticated={isAuthenticated}
                dashboardHref={dashboardHref}
            />
            <main>{children}</main>
            <PublicFooter
                groups={footerGroups}
                isAuthenticated={isAuthenticated}
                dashboardHref={dashboardHref}
            />
        </div>
    );
}
