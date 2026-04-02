import {
    ConfirmDialog,
    type ConfirmDialogProps,
    type ConfirmDialogTone,
} from '@/components/feedback/confirm-dialog';
import InputError from '@/components/input-error';
import { FormErrorSummary } from '@/components/shell/form-error-summary';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type ReasonDialogProps = Omit<
    ConfirmDialogProps,
    'children' | 'error' | 'tone'
> & {
    tone?: ConfirmDialogTone;
    reason: string;
    onReasonChange: (value: string) => void;
    reasonLabel?: string;
    reasonPlaceholder?: string;
    reasonHelperText?: string;
    reasonError?: string;
    errors?: Record<string, string | undefined>;
    required?: boolean;
};

export function ReasonDialog({
    tone = 'warning',
    reason,
    onReasonChange,
    reasonLabel = 'Reason',
    reasonPlaceholder,
    reasonHelperText,
    reasonError,
    errors,
    required = false,
    ...props
}: ReasonDialogProps) {
    const hasErrors = Boolean(
        errors && Object.values(errors).some((message) => Boolean(message)),
    );

    return (
        <ConfirmDialog tone={tone} {...props}>
            {hasErrors ? <FormErrorSummary errors={errors ?? {}} /> : null}

            <div className="grid gap-2">
                <Label htmlFor="reason-dialog-textarea">
                    {reasonLabel}
                    {required ? (
                        <span className="ml-1 text-[color:var(--status-danger)]">
                            *
                        </span>
                    ) : null}
                </Label>
                <Textarea
                    id="reason-dialog-textarea"
                    value={reason}
                    onChange={(event) => onReasonChange(event.target.value)}
                    placeholder={reasonPlaceholder}
                    aria-invalid={reasonError ? 'true' : 'false'}
                    autoFocus
                />
                {reasonHelperText ? (
                    <p className="text-xs leading-5 text-[color:var(--text-secondary)]">
                        {reasonHelperText}
                    </p>
                ) : null}
                <InputError message={reasonError} />
            </div>
        </ConfirmDialog>
    );
}
