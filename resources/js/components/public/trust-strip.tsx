import PublicReveal from '@/components/public/public-reveal';
import { Badge } from '@/components/ui/badge';

export default function TrustStrip({
    items,
}: {
    items: string[];
}) {
    return (
        <section className="border-y border-[color:var(--border-subtle)] bg-background/70">
            <PublicReveal className="mx-auto flex w-full max-w-7xl flex-col gap-5 px-6 py-6 lg:flex-row lg:items-start lg:justify-between lg:px-8">
                <div className="max-w-xl">
                    <p className="text-sm font-medium text-foreground">
                        Designed for controlled day-to-day work
                    </p>
                    <p className="mt-1 text-sm leading-6 text-[color:var(--text-secondary)]">
                        Execution, approvals, visibility, and governed records stay in the same ERP model instead of being reconstructed from separate tools.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2 lg:max-w-2xl lg:justify-end">
                    {items.map((item) => (
                        <Badge key={item} variant="outline" className="px-3 py-1">
                            {item}
                        </Badge>
                    ))}
                </div>
            </PublicReveal>
        </section>
    );
}
