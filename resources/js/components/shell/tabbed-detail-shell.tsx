import * as React from 'react';

import { cn } from '@/lib/utils';

type DetailTab = {
    id: string;
    label: string;
    content: React.ReactNode;
};

type TabbedDetailShellProps = {
    hero: React.ReactNode;
    tabs: DetailTab[];
    defaultTab?: string;
    tabParamName?: string;
    className?: string;
};

export function TabbedDetailShell({
    hero,
    tabs,
    defaultTab,
    tabParamName = 'tab',
    className,
}: TabbedDetailShellProps) {
    const resolvedDefaultTab = defaultTab ?? tabs[0]?.id ?? 'overview';
    const validTabIds = React.useMemo(() => tabs.map((tab) => tab.id), [tabs]);
    const [activeTab, setActiveTab] = React.useState(resolvedDefaultTab);

    React.useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const requestedTab = new URLSearchParams(window.location.search).get(
            tabParamName,
        );

        if (requestedTab && validTabIds.includes(requestedTab)) {
            setActiveTab(requestedTab);
            return;
        }

        setActiveTab(resolvedDefaultTab);
    }, [resolvedDefaultTab, tabParamName, validTabIds]);

    React.useEffect(() => {
        if (typeof window === 'undefined' || !activeTab) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set(tabParamName, activeTab);
        window.history.replaceState(window.history.state, '', url.toString());
    }, [activeTab, tabParamName]);

    const activeTabConfig =
        tabs.find((tab) => tab.id === activeTab) ??
        tabs.find((tab) => tab.id === resolvedDefaultTab) ??
        tabs[0];

    return (
        <div className={cn('space-y-6', className)}>
            {hero}

            <div className="overflow-x-auto">
                <div
                    role="tablist"
                    aria-label="Detail sections"
                    className="inline-flex min-w-full items-center gap-1 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] p-1"
                >
                    {tabs.map((tab) => {
                        const isActive = tab.id === activeTabConfig?.id;

                        return (
                            <button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                onClick={() => setActiveTab(tab.id)}
                                className={cn(
                                    'inline-flex min-w-fit items-center justify-center rounded-[calc(var(--radius-panel)-4px)] px-4 py-2.5 text-sm font-medium transition-colors',
                                    isActive
                                        ? 'bg-[color:var(--bg-surface-elevated)] text-foreground shadow-[var(--shadow-xs)]'
                                        : 'text-[color:var(--text-secondary)] hover:text-foreground',
                                )}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                </div>
            </div>

            {activeTabConfig && (
                <section role="tabpanel">{activeTabConfig.content}</section>
            )}
        </div>
    );
}
