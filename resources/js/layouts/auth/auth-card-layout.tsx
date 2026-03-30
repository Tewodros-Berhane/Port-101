import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogo from '@/components/app-logo';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh items-center justify-center bg-background px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
            <div className="w-full max-w-[30rem]">
                <Card className="rounded-[var(--radius-hero)] border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] shadow-[var(--shadow-md)]">
                    <CardHeader className="gap-3 px-6 pt-6 pb-1 sm:px-7 sm:pt-7">
                        <Link
                            href={home()}
                            className="inline-flex w-fit items-center"
                        >
                            <AppLogo />
                        </Link>
                        <CardTitle className="text-[1.55rem] leading-8 tracking-[-0.03em] text-foreground">
                            {title}
                        </CardTitle>
                        {description ? (
                            <CardDescription className="max-w-sm text-[13.5px] leading-6 text-[color:var(--text-secondary)]">
                                {description}
                            </CardDescription>
                        ) : null}
                    </CardHeader>
                    <CardContent className="px-6 pt-1 pb-6 sm:px-7 sm:pb-7">
                        {children}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
