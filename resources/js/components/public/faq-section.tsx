import { PublicSection, PublicSectionHeader } from '@/components/public/public-shell';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type FaqItem = {
    question: string;
    answer: string;
};

export default function FaqSection({
    items,
}: {
    items: FaqItem[];
}) {
    return (
        <PublicSection id="faq">
            <div className="grid gap-12 lg:grid-cols-[0.82fr_1.18fr]">
                <PublicSectionHeader
                    eyebrow="FAQ"
                    title="Questions a serious ERP buyer will ask early."
                    description="Answers here are constrained to what the current product surface actually supports."
                />

                <div className="grid gap-4">
                    {items.map((item) => (
                        <Card key={item.question} className="rounded-[var(--radius-hero)] py-0">
                            <details className="group">
                                <summary className="list-none cursor-pointer">
                                    <CardHeader className="px-6 py-5">
                                        <CardTitle className="flex items-center justify-between gap-4 text-base leading-7 tracking-[-0.02em]">
                                            <span>{item.question}</span>
                                            <span className="text-[color:var(--text-secondary)] transition-transform group-open:rotate-45">
                                                +
                                            </span>
                                        </CardTitle>
                                    </CardHeader>
                                </summary>
                                <CardContent className="px-6 pb-6 pt-0">
                                    <p className="text-sm leading-7 text-[color:var(--text-secondary)]">
                                        {item.answer}
                                    </p>
                                </CardContent>
                            </details>
                        </Card>
                    ))}
                </div>
            </div>
        </PublicSection>
    );
}
