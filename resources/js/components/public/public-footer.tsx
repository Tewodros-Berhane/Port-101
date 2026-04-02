import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import type {
    PublicFooterGroup,
    PublicHref,
} from '@/components/public/public-shell';
import { Button } from '@/components/ui/button';
import { home, login } from '@/routes';

export default function PublicFooter({
    groups,
    isAuthenticated,
    dashboardHref,
}: {
    groups: PublicFooterGroup[];
    isAuthenticated: boolean;
    dashboardHref: PublicHref;
}) {
    return (
        <footer className="border-t border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]">
            <div className="mx-auto grid w-full max-w-7xl gap-10 px-6 py-12 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div className="space-y-5">
                    <Link href={home()} className="inline-flex items-center">
                        <AppLogo />
                    </Link>
                    <div className="max-w-md space-y-2">
                        <p className="text-sm font-medium text-foreground">
                            Port-101
                        </p>
                        <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                            ERP workspace for finance, operations, projects,
                            people, approvals, reporting, and governed integrations.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {!isAuthenticated ? (
                            <>
                                <Button asChild variant="outline" size="sm">
                                    <Link href="/contact-sales">Contact sales</Link>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href="/book-demo">
                                        Book demo
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={login()}>
                                        Sign in
                                    </Link>
                                </Button>
                            </>
                        ) : (
                            <Button asChild size="sm">
                                <Link href={dashboardHref}>
                                    Open dashboard
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-8 sm:grid-cols-2 xl:grid-cols-4">
                    {groups.map((group) => (
                        <div key={group.title} className="space-y-3">
                            <h3 className="text-sm font-semibold text-foreground">
                                {group.title}
                            </h3>
                            <ul className="space-y-2">
                                {group.links.map((link) => (
                                    <li key={`${group.title}-${link.href}`}>
                                        <a
                                            href={link.href}
                                            className="text-sm text-[color:var(--text-secondary)] transition-colors hover:text-foreground"
                                        >
                                            {link.label}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>

            <div className="border-t border-[color:var(--border-subtle)]">
                <div className="mx-auto flex w-full max-w-7xl flex-col gap-2 px-6 py-4 text-sm text-[color:var(--text-secondary)] sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <span>Built for controlled day-to-day work across finance, operations, projects, and people.</span>
                    <span>(c) 2026 Port-101</span>
                </div>
            </div>
        </footer>
    );
}
