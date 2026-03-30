import { Link, type InertiaLinkProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type BackLinkActionProps = {
    href: NonNullable<InertiaLinkProps['href']>;
    label: string;
    variant?: 'outline' | 'ghost';
    className?: string;
};

export function BackLinkAction({
    href,
    label,
    variant = 'outline',
    className,
}: BackLinkActionProps) {
    return (
        <Button variant={variant} asChild className={cn('gap-2', className)}>
            <Link href={href}>
                <ArrowLeft className="size-4" />
                <span>{label}</span>
            </Link>
        </Button>
    );
}
