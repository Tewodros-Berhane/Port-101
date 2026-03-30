import type { LucideIcon } from 'lucide-react';
import { PublicSection, PublicSectionHeader } from '@/components/public/public-shell';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type SecurityItem = {
    title: string;
    description: string;
    icon: LucideIcon;
};

export default function SecuritySection({
    items,
    evidence,
}: {
    items: SecurityItem[];
    evidence: string[];
}) {
    return (
        <PublicSection id="security" tone="muted">
            <div className="grid gap-12 lg:grid-cols-[0.84fr_1.16fr]">
                <div className="space-y-6">
                    <PublicSectionHeader
                        eyebrow="Trust and governance"
                        title="Credibility comes from control surfaces already in the product."
                        description="Port-101 already includes permission-aware navigation, approval states, audit visibility, reporting workspaces, notifications, and governed webhook delivery history. This section stays inside those implemented capabilities."
                    />

                    <Card className="rounded-[var(--radius-hero)] py-0">
                        <CardHeader className="px-6 py-5">
                            <CardTitle className="text-base leading-7 tracking-[-0.02em]">
                                Control surfaces already present
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-6 pb-6">
                            <div className="flex flex-wrap gap-2">
                                {evidence.map((item) => (
                                    <Badge key={item} variant="outline" className="px-3 py-1">
                                        {item}
                                    </Badge>
                                ))}
                            </div>
                            <p className="mt-4 text-sm leading-6 text-[color:var(--text-secondary)]">
                                The page does not claim certifications or compliance programs that are not exposed by the product. It shows the control model that buyers can already see in the application itself.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 sm:grid-cols-2">
                    {items.map((item) => (
                        <Card key={item.title} className="rounded-[var(--radius-hero)] py-0">
                            <CardHeader className="px-6 pt-6 pb-3">
                                <div className="flex size-11 items-center justify-center rounded-[var(--radius-control)] border border-[color:var(--border-subtle)] bg-card">
                                    <item.icon className="size-5 text-foreground" />
                                </div>
                                <CardTitle className="pt-4 text-lg leading-7 tracking-[-0.02em]">
                                    {item.title}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-6 pb-6">
                                <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                    {item.description}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </PublicSection>
    );
}
