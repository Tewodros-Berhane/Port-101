import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useMemo, useRef, useState, type KeyboardEvent } from 'react';
import {
    PublicSection,
    PublicSectionHeader,
    type PublicHref,
} from '@/components/public/public-shell';
import { PageHeader } from '@/components/shell/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

type PreviewMetric = {
    label: string;
    value: string;
    description: string;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
};

type PreviewFrameCard = {
    title: string;
    helper: string;
};

type PreviewFrameRow = {
    record: string;
    detail: string;
    owner: string;
    status: string;
};

export type ProductPreviewTab = {
    id: string;
    label: string;
    title: string;
    description: string;
    bullets: string[];
    frameTitle: string;
    frameContext: string;
    frameCards: PreviewFrameCard[];
    frameMetrics: PreviewMetric[];
    frameRows: PreviewFrameRow[];
};

export default function ProductPreviewSection({
    tabs,
    isAuthenticated,
    dashboardHref,
}: {
    tabs: ProductPreviewTab[];
    isAuthenticated: boolean;
    dashboardHref: PublicHref;
}) {
    const [activeTabId, setActiveTabId] = useState(tabs[0]?.id ?? '');
    const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);

    const activeTab = useMemo(
        () => tabs.find((tab) => tab.id === activeTabId) ?? tabs[0],
        [activeTabId, tabs],
    );

    if (!activeTab) {
        return null;
    }

    const activeTabIndex = tabs.findIndex((tab) => tab.id === activeTab.id);

    const focusTab = (index: number) => {
        const target = tabs[index];
        if (!target) {
            return;
        }

        setActiveTabId(target.id);
        tabRefs.current[index]?.focus();
    };

    const handleTabKeyDown = (
        event: KeyboardEvent<HTMLButtonElement>,
        index: number,
    ) => {
        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                event.preventDefault();
                focusTab((index + 1) % tabs.length);
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                event.preventDefault();
                focusTab((index - 1 + tabs.length) % tabs.length);
                break;
            case 'Home':
                event.preventDefault();
                focusTab(0);
                break;
            case 'End':
                event.preventDefault();
                focusTab(tabs.length - 1);
                break;
            default:
                break;
        }
    };

    return (
        <PublicSection id="product-preview" tone="muted">
            <div className="space-y-10">
                <div className="max-w-3xl">
                    <PublicSectionHeader
                        eyebrow="Inside the product"
                        title="See how Port-101 keeps records, workflows, and reporting connected."
                        description="Review the same operational context teams use to move work across sales, inventory, finance, projects, HR, and governance."
                    />
                </div>

                <section className="overflow-hidden rounded-[var(--radius-hero)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface)] shadow-[var(--shadow-md)]">
                    <div className="border-b border-[color:var(--border-subtle)] px-6 py-6 sm:px-8">
                        <div className="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                            <div className="max-w-2xl space-y-4">
                                <div className="flex flex-wrap items-center gap-3">
                                    <Badge variant="secondary">Inside the workspace</Badge>
                                    <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                        One system of record across commercial, operational, finance, project, people, and platform work.
                                    </p>
                                </div>
                                <div className="space-y-3">
                                    <h3 className="max-w-2xl text-2xl font-semibold leading-9 tracking-[-0.03em] text-foreground sm:text-3xl">
                                        {activeTab.title}
                                    </h3>
                                    <p className="max-w-xl text-sm leading-7 text-[color:var(--text-secondary)] sm:text-[15px]">
                                        {activeTab.description}
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                {isAuthenticated ? (
                                    <Button asChild>
                                        <Link href={dashboardHref}>
                                            Open dashboard
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button asChild>
                                        <a href="#modules">
                                            See module coverage
                                            <ArrowRight className="size-4" />
                                        </a>
                                    </Button>
                                )}
                                <Button asChild variant="outline">
                                    <a href="#controls">See workflow controls</a>
                                </Button>
                            </div>
                        </div>

                        <div
                            className="mt-6 flex flex-wrap gap-2"
                            role="tablist"
                            aria-label="Product preview tabs"
                        >
                            {tabs.map((tab, index) => (
                                <button
                                    key={tab.id}
                                    ref={(node) => {
                                        tabRefs.current[index] = node;
                                    }}
                                    id={`product-preview-tab-${tab.id}`}
                                    type="button"
                                    role="tab"
                                    aria-selected={activeTab.id === tab.id}
                                    aria-controls={`product-preview-panel-${tab.id}`}
                                    tabIndex={activeTab.id === tab.id ? 0 : -1}
                                    onClick={() => setActiveTabId(tab.id)}
                                    onKeyDown={(event) => handleTabKeyDown(event, index)}
                                    className={cn(
                                        'rounded-full border px-4 py-2 text-sm font-medium transition',
                                        activeTab.id === tab.id
                                            ? 'border-transparent bg-primary text-primary-foreground'
                                            : 'border-[color:var(--border-subtle)] bg-card text-[color:var(--text-secondary)] hover:text-foreground',
                                    )}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-0 xl:grid-cols-[0.4fr_minmax(0,0.6fr)]">
                        <aside className="border-b border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]/70 px-6 py-6 sm:px-8 xl:border-r xl:border-b-0">
                            <div className="space-y-8">
                                <div className="space-y-2">
                                    <p className="text-xs font-medium tracking-[0.14em] text-[color:var(--text-muted)] uppercase">
                                        Current view
                                    </p>
                                    <p className="text-lg font-semibold leading-8 tracking-[-0.02em] text-foreground">
                                        {activeTab.frameTitle}
                                    </p>
                                    <p className="text-sm leading-7 text-[color:var(--text-secondary)]">
                                        {activeTab.frameContext}
                                    </p>
                                </div>

                                <div className="space-y-4">
                                    {activeTab.bullets.map((bullet, index) => (
                                        <div
                                            key={bullet}
                                            className={cn(
                                                'space-y-2',
                                                index > 0 &&
                                                    'border-t border-[color:var(--border-subtle)] pt-4',
                                            )}
                                        >
                                            <div className="flex items-start gap-3">
                                                <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                                                <p className="text-sm leading-7 text-[color:var(--text-secondary)]">
                                                    {bullet}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div className="space-y-4 border-t border-[color:var(--border-subtle)] pt-5">
                                    <p className="text-xs font-medium tracking-[0.14em] text-[color:var(--text-muted)] uppercase">
                                        What appears in view
                                    </p>
                                    <div className="space-y-4">
                                        {activeTab.frameCards.map((card, index) => (
                                            <div
                                                key={card.title}
                                                className={cn(
                                                    'space-y-1',
                                                    index > 0 &&
                                                        'border-t border-[color:var(--border-subtle)] pt-4',
                                                )}
                                            >
                                                <p className="text-sm font-semibold text-foreground">
                                                    {card.title}
                                                </p>
                                                <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                                    {card.helper}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </aside>

                        <div
                            id={`product-preview-panel-${activeTab.id}`}
                            role="tabpanel"
                            aria-labelledby={`product-preview-tab-${activeTab.id}`}
                            className="px-4 py-4 sm:px-6 sm:py-6"
                        >
                            <div className="overflow-hidden rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)]">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[color:var(--border-subtle)] px-5 py-4">
                                    <div className="space-y-1">
                                        <p className="text-xs font-medium tracking-[0.14em] text-[color:var(--text-muted)] uppercase">
                                            Port-101 workspace
                                        </p>
                                        <div className="flex flex-wrap items-center gap-2 text-sm text-[color:var(--text-secondary)]">
                                            <span>{activeTab.label}</span>
                                            <span aria-hidden="true">|</span>
                                            <span>{activeTab.frameTitle}</span>
                                        </div>
                                    </div>
                                    <StatusBadge status="active" />
                                </div>

                                <div className="space-y-6 px-5 py-5">
                                    <div className="border-b border-[color:var(--border-subtle)] pb-5">
                                        <PageHeader
                                            title={activeTab.frameTitle}
                                            description={activeTab.frameContext}
                                            meta={
                                                <>
                                                    <span>Shared records</span>
                                                    <span aria-hidden="true">|</span>
                                                    <span>Status-aware queues</span>
                                                    <span aria-hidden="true">|</span>
                                                    <span>Cross-module visibility</span>
                                                </>
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-0 overflow-hidden rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] sm:grid-cols-3">
                                        {activeTab.frameMetrics.map((metric, index) => (
                                            <div
                                                key={`${activeTab.id}-${metric.label}`}
                                                className={cn(
                                                    'px-4 py-4',
                                                    index > 0 &&
                                                        'border-t border-[color:var(--border-subtle)] sm:border-t-0 sm:border-l',
                                                )}
                                            >
                                                <p className="text-[11px] font-medium tracking-[0.14em] text-[color:var(--text-muted)] uppercase">
                                                    {metric.label}
                                                </p>
                                                <p className="mt-2 text-base font-semibold text-foreground">
                                                    {metric.value}
                                                </p>
                                                <p className="mt-2 text-sm leading-6 text-[color:var(--text-secondary)]">
                                                    {metric.description}
                                                </p>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="overflow-hidden rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)]">
                                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[color:var(--border-subtle)] px-4 py-3">
                                            <div>
                                                <p className="text-sm font-semibold text-foreground">
                                                    Records in this view
                                                </p>
                                                <p className="text-xs text-[color:var(--text-secondary)]">
                                                    Ownership and statuses keep active work clear at every step.
                                                </p>
                                            </div>
                                            <Badge variant="outline">
                                                {activeTabIndex + 1} / {tabs.length}
                                            </Badge>
                                        </div>

                                        <Table container={false}>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Record</TableHead>
                                                    <TableHead>Context</TableHead>
                                                    <TableHead className="w-32">Owner</TableHead>
                                                    <TableHead className="w-32">Status</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {activeTab.frameRows.map((row) => (
                                                    <TableRow
                                                        key={`${activeTab.id}-${row.record}-${row.status}`}
                                                    >
                                                        <TableCell>
                                                            <p className="font-medium text-foreground">
                                                                {row.record}
                                                            </p>
                                                        </TableCell>
                                                        <TableCell>
                                                            <p className="text-[13px] leading-6 text-[color:var(--text-secondary)]">
                                                                {row.detail}
                                                            </p>
                                                        </TableCell>
                                                        <TableCell className="text-[color:var(--text-secondary)]">
                                                            {row.owner}
                                                        </TableCell>
                                                        <TableCell>
                                                            <StatusBadge status={row.status} />
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </PublicSection>
    );
}
