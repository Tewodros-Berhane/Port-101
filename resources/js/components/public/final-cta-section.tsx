import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import {
    PublicSection,
    type PublicHref,
} from '@/components/public/public-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function FinalCtaSection({
    isAuthenticated,
    dashboardHref,
}: {
    isAuthenticated: boolean;
    dashboardHref: PublicHref;
}) {
    return (
        <PublicSection>
            <Card className="rounded-[var(--radius-hero)] py-0 shadow-[var(--shadow-md)]">
                <CardContent className="grid gap-8 px-6 py-10 sm:px-8 lg:grid-cols-[1.1fr_0.9fr] lg:px-10">
                    <div className="space-y-5">
                        <Badge variant="secondary" className="px-3 py-1">
                            Next step
                        </Badge>
                        <div className="space-y-3">
                            <h2 className="text-balance text-3xl font-semibold tracking-[-0.03em] text-foreground sm:text-4xl">
                                See the product model clearly, then move into the right rollout path.
                            </h2>
                            <p className="max-w-2xl text-sm leading-7 text-[color:var(--text-secondary)] sm:text-base">
                                Port-101 brings commercial work, procurement, stock control, finance execution, project delivery, HR, approvals, reporting, and governed integrations into one ERP system.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-center gap-4">
                        <div className="flex flex-wrap gap-3">
                            {isAuthenticated ? (
                                <Button asChild size="lg">
                                    <Link href={dashboardHref}>
                                        Open dashboard
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button asChild size="lg">
                                    <Link href="/book-demo">
                                        Book demo
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                            {!isAuthenticated ? (
                                <Button asChild variant="outline" size="lg">
                                    <Link href="/contact-sales">Contact sales</Link>
                                </Button>
                            ) : (
                                <Button asChild variant="outline" size="lg">
                                    <a href="#product-preview">Revisit product preview</a>
                                </Button>
                            )}
                        </div>
                        <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                            Use the public request flow to book a demo or contact sales. If your team already has access, sign in and continue from the live product.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </PublicSection>
    );
}
