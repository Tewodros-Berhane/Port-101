import { Head } from '@inertiajs/react';
import type { ErrorAction } from '@/components/errors/error-actions';
import ErrorShell from '@/components/errors/error-shell';

export default function ErrorPage({
    status,
    surface,
    title,
    message,
    details,
    reference,
    actions,
}: {
    status: number;
    surface: 'app' | 'public';
    title: string;
    message: string;
    details: string;
    reference?: string | null;
    actions: ErrorAction[];
}) {
    return (
        <>
            <Head title={`${title} - Port-101`} />
            <ErrorShell
                status={status}
                surface={surface}
                title={title}
                message={message}
                details={details}
                reference={reference}
                actions={actions}
            />
        </>
    );
}
