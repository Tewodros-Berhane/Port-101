import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type KnownStatus =
    | 'draft'
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'active'
    | 'inactive'
    | 'posted'
    | 'failed'
    | 'delivered'
    | 'dead'
    | 'cancelled'
    | 'in_progress'
    | 'overdue'
    | 'completed'
    | 'queued'
    | 'suspended';

const STATUS_VARIANTS = {
    active: 'success',
    approved: 'success',
    cancelled: 'neutral',
    completed: 'success',
    dead: 'danger',
    delivered: 'success',
    draft: 'neutral',
    failed: 'danger',
    inactive: 'neutral',
    in_progress: 'info',
    overdue: 'danger',
    pending: 'warning',
    posted: 'info',
    queued: 'info',
    rejected: 'danger',
    suspended: 'neutral',
} as const;

function normalizeStatus(status: string): string {
    return status.trim().toLowerCase().replace(/[\s-]+/g, '_');
}

function labelizeStatus(status: string): string {
    return status
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label?: string;
    className?: string;
}) {
    const normalizedStatus = normalizeStatus(status);
    const variant =
        STATUS_VARIANTS[normalizedStatus as KnownStatus] ?? 'outline';

    return (
        <Badge variant={variant} className={cn('capitalize', className)}>
            {label ?? labelizeStatus(normalizedStatus)}
        </Badge>
    );
}
