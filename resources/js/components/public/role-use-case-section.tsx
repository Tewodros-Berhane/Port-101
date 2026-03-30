import { PublicSection, PublicSectionHeader } from '@/components/public/public-shell';

export type RoleUseCase = {
    title: string;
    manages: string;
    removes: string;
    improves: string;
};

export default function RoleUseCaseSection({
    items,
}: {
    items: RoleUseCase[];
}) {
    return (
        <PublicSection id="teams" tone="muted">
            <div className="grid gap-12 lg:grid-cols-[0.72fr_1.28fr]">
                <PublicSectionHeader
                    eyebrow="Who it is for"
                    title="Designed for the teams that keep the company moving."
                    description="Each role works inside the same system of record, but sees the records, queues, and control points that matter to its side of the operation."
                />

                <div className="grid gap-6 sm:grid-cols-2">
                    {items.map((item, index) => (
                        <article
                            key={item.title}
                            className="grid gap-4 border-t border-[color:var(--border-subtle)] pt-5"
                        >
                            <div className="flex items-center gap-3">
                                <span className="text-sm font-semibold text-[color:var(--text-muted)]">
                                    0{index + 1}
                                </span>
                                <h3 className="text-lg font-semibold tracking-[-0.02em] text-foreground">
                                    {item.title}
                                </h3>
                            </div>

                            <dl className="grid gap-4 pl-8">
                                <div>
                                    <dt className="text-[11px] font-semibold tracking-[0.12em] text-[color:var(--text-muted)] uppercase">
                                        Manage
                                    </dt>
                                    <dd className="mt-1 text-sm leading-6 text-[color:var(--text-secondary)]">
                                        {item.manages}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] font-semibold tracking-[0.12em] text-[color:var(--text-muted)] uppercase">
                                        Remove friction
                                    </dt>
                                    <dd className="mt-1 text-sm leading-6 text-[color:var(--text-secondary)]">
                                        {item.removes}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] font-semibold tracking-[0.12em] text-[color:var(--text-muted)] uppercase">
                                        Improve decisions
                                    </dt>
                                    <dd className="mt-1 text-sm leading-6 text-[color:var(--text-secondary)]">
                                        {item.improves}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    ))}
                </div>
            </div>
        </PublicSection>
    );
}
