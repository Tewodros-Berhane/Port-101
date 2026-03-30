import { usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

import type { SharedData } from '@/types';

export function CompanySwitcher() {
    const { company } = usePage<SharedData>().props;

    if (!company) {
        return null;
    }

    return (
        <div
            className="flex h-10 min-w-0 items-center gap-2 rounded-[var(--radius-control)] border border-[color:var(--border-default)] bg-card/80 px-3 shadow-[var(--shadow-xs)]"
            aria-label={`Current company: ${company.name}`}
            title={company.name}
            data-test="company-switcher"
        >
            <Building2 className="size-4 shrink-0 text-[color:var(--text-muted)]" />
            <span className="hidden max-w-[160px] truncate text-foreground sm:inline">
                {company.name}
            </span>
        </div>
    );
}
