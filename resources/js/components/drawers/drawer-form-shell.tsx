import type { ReactNode } from 'react';

import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type DrawerFormShellProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
};

export function DrawerFormShell({
    open,
    onOpenChange,
    title,
    description,
    children,
    footer,
    className,
}: DrawerFormShellProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className={className ?? 'w-full sm:max-w-xl'}
            >
                <SheetHeader className="border-b border-[color:var(--border-subtle)] pb-4">
                    <SheetTitle>{title}</SheetTitle>
                    {description ? (
                        <SheetDescription>{description}</SheetDescription>
                    ) : null}
                </SheetHeader>
                <div className="flex-1 overflow-y-auto px-5 pb-5">{children}</div>
                {footer ? <SheetFooter>{footer}</SheetFooter> : null}
            </SheetContent>
        </Sheet>
    );
}
