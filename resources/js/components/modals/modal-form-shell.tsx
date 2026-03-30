import type { ReactNode } from 'react';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ModalFormShellProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
};

export function ModalFormShell({
    open,
    onOpenChange,
    title,
    description,
    children,
    className,
}: ModalFormShellProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={className ?? 'sm:max-w-lg'}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>
                <div className="grid gap-5">{children}</div>
            </DialogContent>
        </Dialog>
    );
}
