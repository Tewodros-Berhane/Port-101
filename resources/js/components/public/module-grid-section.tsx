import type { LucideIcon } from 'lucide-react';
import { PublicSection, PublicSectionHeader } from '@/components/public/public-shell';

export type ModuleCard = {
    title: string;
    description: string;
    capabilities: string[];
    icon: LucideIcon;
};

export default function ModuleGridSection({
    items,
}: {
    items: ModuleCard[];
}) {
    return (
        <PublicSection id="modules">
            <div className="grid gap-12 lg:grid-cols-[0.72fr_1.28fr]">
                <PublicSectionHeader
                    eyebrow="Module coverage"
                    title="Port-101 covers the core operating areas teams manage every day."
                    description="Each module supports a real part of the business, from commercial flow and stock control to finance, projects, HR, and governance."
                />

                <div className="grid gap-6 border-t border-[color:var(--border-subtle)] sm:grid-cols-2">
                    {items.map((item) => (
                        <article
                            key={item.title}
                            className="grid gap-4 border-b border-[color:var(--border-subtle)] py-6"
                        >
                            <div className="flex items-start gap-4">
                                <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-full bg-[color:var(--bg-surface-muted)] text-foreground">
                                    <item.icon className="size-5" />
                                </div>

                                <div className="space-y-2">
                                    <h3 className="text-lg font-semibold tracking-[-0.02em] text-foreground">
                                        {item.title}
                                    </h3>
                                    <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                        {item.description}
                                    </p>
                                </div>
                            </div>

                            <ul className="grid gap-2 pl-14">
                                {item.capabilities.map((capability) => (
                                    <li
                                        key={capability}
                                        className="flex items-start gap-3 text-sm leading-6 text-[color:var(--text-secondary)]"
                                    >
                                        <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                                        <span>{capability}</span>
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </div>
            </div>
        </PublicSection>
    );
}
