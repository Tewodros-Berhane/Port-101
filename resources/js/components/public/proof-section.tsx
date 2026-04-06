import { PublicSection, PublicSectionHeader } from '@/components/public/public-shell';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type ProofItem = {
    title: string;
    description: string;
};

export default function ProofSection({
    items,
}: {
    items: ProofItem[];
}) {
    return (
        <PublicSection id="proof">
            <PublicSectionHeader
                eyebrow="Operational proof"
                title="Port-101 is designed to keep execution, ownership, and oversight in one system."
                description="The product brings connected records, visible responsibility, governed execution, and phased rollout into the same operating model."
            />

            <div className="mt-12 grid gap-6 lg:grid-cols-3">
                {items.map((item) => (
                    <Card key={item.title} className="rounded-[var(--radius-hero)] py-0">
                        <CardHeader className="px-6 pt-6 pb-3">
                            <CardTitle className="text-lg leading-7 tracking-[-0.02em]">
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
        </PublicSection>
    );
}
