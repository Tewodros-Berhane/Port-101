import { SidebarInset } from '@/components/ui/sidebar';
import * as React from 'react';

type Props = React.ComponentProps<'main'> & {
    variant?: 'header' | 'sidebar';
};

export function AppContent({ variant = 'header', children, ...props }: Props) {
    if (variant === 'sidebar') {
        return <SidebarInset {...props}>{children}</SidebarInset>;
    }

    return (
        <main
            className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl px-6 py-6 md:px-4"
            {...props}
        >
            {children}
        </main>
    );
}
