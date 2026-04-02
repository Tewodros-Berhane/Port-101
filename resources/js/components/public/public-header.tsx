import { Link } from '@inertiajs/react';
import { ArrowRight, Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogo from '@/components/app-logo';
import type { PublicHref, PublicNavLink } from '@/components/public/public-shell';
import PublicThemeToggle from '@/components/public/public-theme-toggle';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { home, login } from '@/routes';

export default function PublicHeader({
    navLinks,
    isAuthenticated,
    dashboardHref,
}: {
    navLinks: PublicNavLink[];
    isAuthenticated: boolean;
    dashboardHref: PublicHref;
}) {
    const [isScrolled, setIsScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setIsScrolled(window.scrollY > 10);

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <header
            className={cn(
                'sticky top-0 z-50 border-b transition-colors duration-200',
                isScrolled
                    ? 'border-[color:var(--border-subtle)] bg-background/92 shadow-[var(--shadow-xs)] backdrop-blur supports-[backdrop-filter]:bg-background/82'
                    : 'border-transparent bg-background/88 supports-[backdrop-filter]:bg-background/72',
            )}
        >
            <div className="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-6 py-4 lg:px-8">
                <Link href={home()} className="inline-flex items-center">
                    <AppLogo />
                </Link>

                <nav className="hidden items-center gap-6 lg:flex">
                    {navLinks.map((link) => (
                        <a
                            key={link.href}
                            href={link.href}
                            className="text-sm font-medium text-[color:var(--text-secondary)] transition-colors hover:text-foreground"
                        >
                            {link.label}
                        </a>
                    ))}
                </nav>

                <div className="hidden items-center gap-3 lg:flex">
                    <PublicThemeToggle />
                    {!isAuthenticated ? (
                        <>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={login()}>Sign in</Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href="/contact-sales">Contact sales</Link>
                            </Button>
                            <Button asChild>
                                <Link href="/book-demo">
                                    Book demo
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <Button asChild>
                            <Link href={dashboardHref}>
                                Open dashboard
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex items-center gap-2 lg:hidden">
                    <PublicThemeToggle compact />
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Open navigation menu"
                            >
                                <Menu className="size-4" />
                            </Button>
                        </SheetTrigger>
                    <SheetContent side="right" className="w-full max-w-sm">
                        <SheetHeader className="border-b border-[color:var(--border-subtle)]">
                            <SheetTitle className="flex items-center">
                                <Link href={home()} className="inline-flex items-center">
                                    <AppLogo />
                                </Link>
                            </SheetTitle>
                            <SheetDescription>
                                Navigate the product sections and choose the right access path.
                            </SheetDescription>
                        </SheetHeader>

                        <div className="flex flex-1 flex-col gap-6 overflow-y-auto p-5">
                            <nav className="grid gap-2">
                                {navLinks.map((link) => (
                                    <SheetClose key={link.href} asChild>
                                        <a
                                            href={link.href}
                                            className="rounded-[var(--radius-control)] px-3 py-2 text-sm font-medium text-[color:var(--text-secondary)] transition-colors hover:bg-[color:var(--bg-surface-muted)] hover:text-foreground"
                                        >
                                            {link.label}
                                        </a>
                                    </SheetClose>
                                ))}
                            </nav>

                            <div className="mt-auto grid gap-3">
                                {!isAuthenticated ? (
                                    <>
                                        <PublicThemeToggle className="w-full justify-center" />
                                        <SheetClose asChild>
                                            <Button asChild variant="outline" className="w-full">
                                                <Link href="/contact-sales">
                                                    Contact sales
                                                </Link>
                                            </Button>
                                        </SheetClose>
                                        <SheetClose asChild>
                                            <Button asChild className="w-full">
                                                <Link href="/book-demo">
                                                    Book demo
                                                    <ArrowRight className="size-4" />
                                                </Link>
                                            </Button>
                                        </SheetClose>
                                        <SheetClose asChild>
                                            <Button asChild variant="ghost" className="w-full">
                                                <Link href={login()}>Sign in</Link>
                                            </Button>
                                        </SheetClose>
                                    </>
                                ) : (
                                    <>
                                        <PublicThemeToggle className="w-full justify-center" />
                                        <SheetClose asChild>
                                            <Button asChild className="w-full">
                                                <Link href={dashboardHref}>
                                                    Open dashboard
                                                    <ArrowRight className="size-4" />
                                                </Link>
                                            </Button>
                                        </SheetClose>
                                    </>
                                )}
                            </div>
                        </div>
                    </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
