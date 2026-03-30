import { router, usePage } from '@inertiajs/react';
import { CornerDownLeft, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { getCommandItems } from '@/lib/app-navigation';
import { cn, toUrl } from '@/lib/utils';
import type { SharedData } from '@/types';

function getShortcutLabel() {
    if (
        typeof navigator !== 'undefined' &&
        /Mac|iPhone|iPad|iPod/.test(navigator.platform)
    ) {
        return 'Cmd K';
    }

    return 'Ctrl K';
}

export function AppCommandPalette() {
    const page = usePage<SharedData>();
    const currentPath = new URL(page.url, 'http://localhost').pathname;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const isSuperAdmin = Boolean(page.props.auth?.user?.is_super_admin);
    const unreadNotifications = page.props.notifications?.unread_count ?? 0;
    const shortcutLabel = getShortcutLabel();

    const items = useMemo(
        () =>
            getCommandItems({
                isSuperAdmin,
                unreadNotifications,
            }).filter(
                (item) =>
                    !item.permission ||
                    (page.props.permissions ?? []).includes(item.permission),
            ),
        [isSuperAdmin, page.props.permissions, unreadNotifications],
    );

    const filteredGroups = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();
        const filteredItems = normalizedQuery
            ? items.filter((item) => {
                  const searchText = [
                      item.title,
                      item.description,
                      ...(item.keywords ?? []),
                  ]
                      .filter(Boolean)
                      .join(' ')
                      .toLowerCase();

                  return searchText.includes(normalizedQuery);
              })
            : items;

        return Array.from(
            filteredItems.reduce((groups, item) => {
                const currentItems = groups.get(item.group) ?? [];
                currentItems.push(item);
                groups.set(item.group, currentItems);
                return groups;
            }, new Map<string, typeof filteredItems>()),
        );
    }, [items, query]);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((current) => !current);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    const handleSelect = (href: string) => {
        setOpen(false);
        setQuery('');
        router.visit(href);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="group inline-flex h-10 min-w-0 items-center gap-3 rounded-[var(--radius-control)] border border-[color:var(--border-default)] bg-card/80 px-3 text-left text-[13px] text-[color:var(--text-secondary)] shadow-[var(--shadow-xs)] transition-[background-color,border-color,color,box-shadow] duration-150 hover:border-[color:var(--border-strong)] hover:bg-card hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                aria-label="Open command palette"
            >
                <Search className="size-4 text-[color:var(--text-muted)] transition-colors group-hover:text-foreground" />
                <span className="hidden min-w-0 truncate sm:inline">
                    Search, jump, or create...
                </span>
                <span className="inline text-sm sm:hidden">Search</span>
                <span className="ml-1 hidden rounded-md border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-1.5 py-0.5 text-[11px] font-medium tracking-[0.04em] text-[color:var(--text-muted)] lg:inline-flex">
                    {shortcutLabel}
                </span>
            </button>
            <Dialog
                open={open}
                onOpenChange={(nextOpen) => {
                    setOpen(nextOpen);
                    if (!nextOpen) {
                        setQuery('');
                    }
                }}
            >
                <DialogContent className="max-w-2xl gap-0 overflow-hidden border-[color:var(--border-default)] p-0 [&>button]:hidden">
                    <DialogHeader className="sr-only">
                        <DialogTitle>Command palette</DialogTitle>
                    </DialogHeader>
                    <div className="border-b border-[color:var(--border-subtle)] bg-[color:var(--bg-surface)] px-5 py-4">
                        <div className="flex items-center gap-3">
                            <Search className="size-4 text-[color:var(--text-muted)]" />
                            <Input
                                autoFocus
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search modules, settings, and common actions..."
                                className="h-auto border-0 bg-transparent px-0 py-0 text-sm shadow-none focus-visible:ring-0"
                            />
                            <span className="hidden rounded-md border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-1.5 py-0.5 text-[11px] font-medium text-[color:var(--text-muted)] sm:inline-flex">
                                {shortcutLabel}
                            </span>
                        </div>
                    </div>
                    <div className="max-h-[28rem] overflow-y-auto p-3">
                        {filteredGroups.length === 0 ? (
                            <div className="rounded-[var(--radius-panel)] border border-dashed border-[color:var(--border-default)] bg-[color:var(--bg-surface-muted)] px-4 py-8 text-center">
                                <p className="text-sm font-medium text-foreground">
                                    No matching commands
                                </p>
                                <p className="mt-1 text-xs text-[color:var(--text-muted)]">
                                    Try searching by module, action, or record type.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {filteredGroups.map(([group, groupItems]) => (
                                    <div key={group} className="space-y-2">
                                        <div className="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-[color:var(--text-muted)]">
                                            {group}
                                        </div>
                                        <div className="space-y-1">
                                            {groupItems.map((item) => {
                                                const Icon = item.icon;

                                                return (
                                                    <button
                                                        key={`${group}-${item.title}-${item.href.toString()}`}
                                                        type="button"
                                                        onClick={() =>
                                                            handleSelect(
                                                                toUrl(item.href),
                                                            )
                                                        }
                                                        className={cn(
                                                            'flex w-full items-center gap-3 rounded-[var(--radius-panel)] border border-transparent px-3 py-3 text-left transition-[background-color,border-color,color] duration-150 hover:border-[color:var(--border-subtle)] hover:bg-[color:var(--bg-surface-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                                            currentPath ===
                                                                toUrl(item.href) &&
                                                                'border-[color:var(--border-subtle)] bg-[color:var(--action-primary-soft)]',
                                                        )}
                                                    >
                                                        {Icon && (
                                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-[color:var(--bg-surface-muted)] text-[color:var(--text-secondary)]">
                                                                <Icon className="size-4" />
                                                            </span>
                                                        )}
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block truncate text-sm font-medium text-foreground">
                                                                {item.title}
                                                            </span>
                                                            {item.description && (
                                                                <span className="mt-0.5 block truncate text-xs text-[color:var(--text-muted)]">
                                                                    {item.description}
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="hidden items-center gap-1 rounded-md border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface)] px-1.5 py-1 text-[11px] font-medium text-[color:var(--text-muted)] sm:inline-flex">
                                                            Open
                                                            <CornerDownLeft className="size-3.5" />
                                                        </span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
