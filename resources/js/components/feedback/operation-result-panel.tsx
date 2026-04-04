import { AlertCircle, CheckCircle2, Info, TriangleAlert, X } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { cn } from '@/lib/utils';

export type OperationResultFeedback = {
    tone: 'success' | 'warning' | 'info' | 'error';
    title: string;
    message: string;
    nextStep?: string;
    details?: string[];
};

type OperationResultPanelProps = {
    feedback: OperationResultFeedback;
    onDismiss: () => void;
    dismissLabel?: string;
    className?: string;
};

export function OperationResultPanel({
    feedback,
    onDismiss,
    dismissLabel = 'Dismiss operational feedback',
    className,
}: OperationResultPanelProps) {
    const Icon =
        feedback.tone === 'success'
            ? CheckCircle2
            : feedback.tone === 'warning'
              ? TriangleAlert
              : feedback.tone === 'error'
                ? AlertCircle
                : Info;

    return (
        <Alert
            variant={feedback.tone === 'error' ? 'destructive' : 'default'}
            className={cn(
                'border',
                feedback.tone === 'success'
                    ? 'border-[color:var(--status-success)]/20 bg-[color:var(--status-success-soft)] text-[color:var(--status-success-foreground)]'
                    : feedback.tone === 'warning'
                      ? 'border-[color:var(--status-warning)]/20 bg-[color:var(--status-warning-soft)] text-[color:var(--status-warning-foreground)]'
                      : feedback.tone === 'info'
                        ? 'border-[color:var(--status-info)]/20 bg-[color:var(--status-info-soft)] text-[color:var(--status-info-foreground)]'
                        : 'border-destructive/30 bg-destructive/10 text-destructive',
                className,
            )}
        >
            <Icon className="size-4" />
            <div className="flex items-start justify-between gap-4">
                <div className="space-y-1">
                    <AlertTitle>{feedback.title}</AlertTitle>
                    <AlertDescription className="space-y-1">
                        <p>{feedback.message}</p>
                        {feedback.details && feedback.details.length > 0 && (
                            <ul className="list-disc space-y-1 pl-5">
                                {feedback.details.map((detail) => (
                                    <li key={detail}>{detail}</li>
                                ))}
                            </ul>
                        )}
                        {feedback.nextStep && <p>{feedback.nextStep}</p>}
                    </AlertDescription>
                </div>
                <button
                    type="button"
                    className="rounded-[var(--radius-control)] p-1 opacity-70 transition hover:bg-black/5 hover:opacity-100 dark:hover:bg-white/10"
                    onClick={onDismiss}
                    aria-label={dismissLabel}
                >
                    <X className="size-4" />
                </button>
            </div>
        </Alert>
    );
}
