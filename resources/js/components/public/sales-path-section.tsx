import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import {
    PublicSection,
    PublicSectionHeader,
    type PublicHref,
} from '@/components/public/public-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type SalesPathProfile = {
    title: string;
    fit: string;
    scope: string[];
    support: string;
    highlight?: boolean;
};

export default function SalesPathSection({
    profiles,
    isAuthenticated,
    dashboardHref,
}: {
    profiles: SalesPathProfile[];
    isAuthenticated: boolean;
    dashboardHref: PublicHref;
}) {
    return (
        <PublicSection id="sales-path" tone="muted">
            <PublicSectionHeader
                eyebrow="Deployment paths"
                title="Adopt Port-101 in the order your operation actually needs."
                description="Choose the rollout path that matches your operating complexity, team structure, and control requirements."
            />

            <div className="mt-12 grid gap-6 lg:grid-cols-3">
                {profiles.map((profile) => (
                    <Card
                        key={profile.title}
                        className={`rounded-[var(--radius-hero)] py-0 ${
                            profile.highlight
                                ? 'border-[color:var(--border-strong)] shadow-[var(--shadow-md)]'
                                : ''
                        }`}
                    >
                        <CardHeader className="px-6 pt-6 pb-3">
                            <CardTitle className="text-lg leading-7 tracking-[-0.02em]">
                                {profile.title}
                            </CardTitle>
                            <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                {profile.fit}
                            </p>
                        </CardHeader>
                        <CardContent className="grid gap-5 px-6 pb-6">
                            <ul className="space-y-2">
                                {profile.scope.map((item) => (
                                    <li
                                        key={item}
                                        className="flex items-start gap-3 text-sm leading-6 text-[color:var(--text-secondary)]"
                                    >
                                        <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] p-4">
                                <p className="text-xs font-semibold tracking-[0.12em] text-[color:var(--text-muted)] uppercase">
                                    Support style
                                </p>
                                <p className="mt-2 text-sm leading-6 text-[color:var(--text-secondary)]">
                                    {profile.support}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="mt-10 flex flex-wrap gap-3">
                {isAuthenticated ? (
                    <Button asChild>
                        <Link href={dashboardHref}>
                            Open dashboard
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                ) : (
                    <Button asChild>
                        <Link href="/book-demo">
                            Book demo
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                )}
                <Button asChild variant="outline">
                    {isAuthenticated ? (
                        <a href="#security">Review control model</a>
                    ) : (
                        <Link href="/contact-sales">Contact sales</Link>
                    )}
                </Button>
            </div>
        </PublicSection>
    );
}
