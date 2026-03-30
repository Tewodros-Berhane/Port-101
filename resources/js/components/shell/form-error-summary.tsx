import { AlertCircle } from 'lucide-react';
import * as React from 'react';


import { cn } from '@/lib/utils';

type FormErrorSummaryProps = {
    errors: Record<string, string | undefined>;
    title?: string;
    className?: string;
};

export function FormErrorSummary({
    errors,
    title = 'Review the highlighted fields before saving.',
    className,
}: FormErrorSummaryProps) {
    const items = React.useMemo(
        () =>
            Object.entries(errors).filter(
                (entry): entry is [string, string] =>
                    Boolean(entry[0]) && Boolean(entry[1]),
            ),
        [errors],
    );
    const alertRef = React.useRef<HTMLDivElement | null>(null);

    React.useEffect(() => {
        if (items.length > 0) {
            alertRef.current?.focus();
        }
    }, [items.length]);

    if (items.length === 0) {
        return null;
    }

    return (
        <div
            ref={alertRef}
            role="alert"
            tabIndex={-1}
            className={cn(
                'rounded-[var(--radius-panel)] border border-[color:var(--status-danger)]/18 bg-[var(--status-danger-soft)] px-5 py-4 text-sm text-[color:var(--text-primary)] shadow-[var(--shadow-xs)] outline-none',
                className,
            )}
        >
            <div className="flex items-start gap-3">
                <div className="mt-0.5 rounded-full bg-[color:var(--status-danger)]/12 p-1 text-[color:var(--status-danger)]">
                    <AlertCircle className="size-4" />
                </div>

                <div className="min-w-0 flex-1 space-y-2">
                    <p className="font-medium">{title}</p>
                    <ul className="space-y-1.5 text-[13px] leading-5 text-[color:var(--text-secondary)]">
                        {items.map(([field, message]) => (
                            <li key={field}>
                                <span className="font-medium text-[color:var(--text-primary)]">
                                    {field.replace(/_/g, ' ')}:
                                </span>{' '}
                                {message}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}
