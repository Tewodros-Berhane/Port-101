import { Link } from '@inertiajs/react';
import ErrorActions, { type ErrorAction } from '@/components/errors/error-actions';
import { home } from '@/routes';

export default function ErrorShell({
    status,
    surface,
    title,
    message,
    details,
    reference,
    actions,
}: {
    status: number;
    surface: 'app' | 'public';
    title: string;
    message: string;
    details: string;
    reference?: string | null;
    actions: ErrorAction[];
}) {
    return (
        <div className="min-h-screen bg-background text-foreground">
            <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-8 sm:px-8 lg:px-12">
                <div className="flex items-center justify-between gap-4 border-b border-border/70 pb-5">
                    <Link href={home()} className="inline-flex items-center gap-3 text-sm font-medium tracking-[-0.01em]">
                        <span className="flex h-10 w-10 items-center justify-center rounded-2xl border border-border bg-card text-base font-semibold shadow-xs">
                            P
                        </span>
                        <span className="flex flex-col">
                            <span className="text-[15px] font-semibold text-foreground">Port-101</span>
                            <span className="text-xs font-normal text-muted-foreground">
                                {surface === 'app' ? 'Workspace error' : 'Public access'}
                            </span>
                        </span>
                    </Link>

                    <div className="rounded-full border border-border/70 bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
                        Error {status}
                    </div>
                </div>

                <main className="flex flex-1 items-center py-12 sm:py-16">
                    <div className="grid w-full gap-10 lg:grid-cols-[168px_minmax(0,1fr)] lg:gap-16">
                        <div className="space-y-3">
                            <p className="text-sm font-medium uppercase tracking-[0.18em] text-muted-foreground">
                                {surface === 'app' ? 'Application' : 'Public'}
                            </p>
                            <div className="text-6xl font-semibold tracking-[-0.05em] text-foreground sm:text-7xl">
                                {status}
                            </div>
                        </div>

                        <div className="space-y-8">
                            <div className="max-w-3xl space-y-4">
                                <h1 className="text-3xl font-semibold tracking-[-0.03em] text-foreground sm:text-5xl">
                                    {title}
                                </h1>
                                <p className="max-w-2xl text-base leading-7 text-foreground/85 sm:text-lg">
                                    {message}
                                </p>
                                <p className="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                                    {details}
                                </p>
                            </div>

                            <ErrorActions actions={actions} />

                            {reference ? (
                                <div className="max-w-xl border-t border-border/70 pt-5 text-sm text-muted-foreground">
                                    <span className="font-medium text-foreground">Reference</span>
                                    <span className="mx-2 text-muted-foreground/50">/</span>
                                    <span className="font-mono text-xs tracking-[0.04em]">{reference}</span>
                                </div>
                            ) : null}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
