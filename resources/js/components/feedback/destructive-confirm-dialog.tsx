import {
    ConfirmDialog,
    type ConfirmDialogProps,
} from '@/components/feedback/confirm-dialog';

export function DestructiveConfirmDialog(
    props: Omit<ConfirmDialogProps, 'tone'>,
) {
    return <ConfirmDialog tone="danger" {...props} />;
}
