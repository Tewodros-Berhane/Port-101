import { PublicSection, PublicSectionHeader } from '@/components/public/public-shell';
import { StatusBadge } from '@/components/ui/status-badge';

export type WorkflowItem = {
    title: string;
    description: string;
};

export default function WorkflowSection({
    items,
}: {
    items: WorkflowItem[];
}) {
    return (
        <PublicSection id="controls">
            <div className="grid gap-12 lg:grid-cols-[0.86fr_1.14fr]">
                <div className="space-y-6">
                    <PublicSectionHeader
                        eyebrow="Execution controls"
                        title="Workflows, approvals, and reporting stay inside the operating model."
                        description="Port-101 is not just a record store. Work enters a queue, ownership is visible, approvals stay explicit, and reporting reflects the same records teams execute against."
                    />
                </div>

                <div className="relative">
                    <div
                        aria-hidden="true"
                        className="absolute top-0 bottom-0 left-3 hidden w-px bg-[color:var(--border-subtle)] sm:block"
                    />
                    <div className="grid gap-6">
                        {items.map((item, index) => (
                            <article
                                key={item.title}
                                className="relative grid gap-3 rounded-[var(--radius-panel)] bg-[color:var(--bg-surface-muted)]/70 p-5 ring-1 ring-[color:var(--border-subtle)]"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-background text-xs font-semibold text-foreground ring-1 ring-[color:var(--border-subtle)]">
                                            {index + 1}
                                        </span>
                                        <h3 className="text-base font-semibold tracking-[-0.02em] text-foreground">
                                            {item.title}
                                        </h3>
                                    </div>
                                    <StatusBadge
                                        status={
                                            index === 0
                                                ? 'queued'
                                                : index === items.length - 1
                                                  ? 'posted'
                                                  : 'in_progress'
                                        }
                                    />
                                </div>
                                <p className="pl-9 text-sm leading-6 text-[color:var(--text-secondary)]">
                                    {item.description}
                                </p>
                            </article>
                        ))}
                    </div>
                </div>
            </div>
        </PublicSection>
    );
}
