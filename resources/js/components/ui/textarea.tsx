import * as React from 'react';

import { cn } from '@/lib/utils';

function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'border-input placeholder:text-muted-foreground selection:bg-primary/15 selection:text-foreground flex min-h-28 w-full rounded-[var(--radius-control)] border bg-card px-3.5 py-2.5 text-sm text-foreground shadow-[var(--shadow-xs)] transition-[border-color,box-shadow,background-color] duration-150 outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/15 dark:aria-invalid:ring-destructive/25',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
