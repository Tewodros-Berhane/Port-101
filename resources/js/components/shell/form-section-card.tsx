import * as React from 'react';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type FormSectionCardProps = {
    title: string;
    description?: string;
    children: React.ReactNode;
    className?: string;
    contentClassName?: string;
};

export function FormSectionCard({
    title,
    description,
    children,
    className,
    contentClassName,
}: FormSectionCardProps) {
    return (
        <Card className={cn('py-0', className)}>
            <CardHeader className="border-b border-[color:var(--border-subtle)] py-5">
                <CardTitle>{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className={cn('px-6 py-5', contentClassName)}>
                {children}
            </CardContent>
        </Card>
    );
}
