import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type KnownStatus =
    | 'draft'
    | 'pending'
    | 'new'
    | 'contacted'
    | 'qualified'
    | 'demo'
    | 'sales'
    | 'demo_scheduled'
    | 'closed'
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
    closed: 'neutral',
    completed: 'success',
    contacted: 'info',
    dead: 'danger',
    demo: 'info',
    demo_scheduled: 'info',
    delivered: 'success',
    draft: 'neutral',
    failed: 'danger',
    inactive: 'neutral',
    in_progress: 'info',
    new: 'warning',
    overdue: 'danger',
    pending: 'warning',
    posted: 'info',
    qualified: 'success',
    queued: 'info',
    rejected: 'danger',
    sales: 'warning',
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
