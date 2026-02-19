import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Invite = {
    id: string;
    email: string;
    name?: string | null;
    role: string;
    status: string;
    invite_url: string;
    expires_at?: string | null;
    delivery_status?: string | null;
    delivery_attempts?: number;
    last_delivery_at?: string | null;
    last_delivery_error?: string | null;
};

type Props = {
    invites: {
        data: Invite[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const formatRole = (role: string) =>
    role.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

export default function CompanyInvitesIndex({ invites }: Props) {
    const deleteForm = useForm({});
    const resendForm = useForm({});
    const retryDeliveryForm = useForm({});

    const handleDelete = (inviteId: string) => {
        if (!confirm('Revoke this invite?')) {
            return;
        }

        deleteForm.delete(`/core/invites/${inviteId}`);
    };

    const handleResend = (inviteId: string) => {
        resendForm.post(`/core/invites/${inviteId}/resend`);
    };

    const handleRetryDelivery = (inviteId: string) => {
        retryDeliveryForm.post(`/core/invites/${inviteId}/retry-delivery`);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Invites', href: '/core/invites' },
            ]}
        >
            <Head title="Invites" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Invites</h1>
                    <p className="text-sm text-muted-foreground">
                        Invite team members to your company.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/core/invites/create">New invite</Link>
                </Button>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-max text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Role</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Expires</th>
                            <th className="px-4 py-3 font-medium">Delivery</th>
                            <th className="px-4 py-3 font-medium">
                                Invite URL
                            </th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {invites.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={7}
                                >
                                    No invites yet.
                                </td>
                            </tr>
                        )}
                        {invites.data.map((invite) => (
                            <tr key={invite.id}>
                                <td className="px-4 py-3">
                                    <div className="font-medium">
                                        {invite.email}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {invite.name ?? '—'}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    {formatRole(invite.role)}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {invite.status}
                                </td>
                                <td className="px-4 py-3">
                                    {formatDate(invite.expires_at)}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="capitalize">
                                        {invite.delivery_status ?? 'pending'}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Attempts:{' '}
                                        {invite.delivery_attempts ?? 0}
                                    </div>
                                    {invite.last_delivery_at && (
                                        <div className="text-xs text-muted-foreground">
                                            Sent{' '}
                                            {formatDate(
                                                invite.last_delivery_at,
                                            )}
                                        </div>
                                    )}
                                    {invite.last_delivery_error && (
                                        <div className="text-xs text-rose-600">
                                            {invite.last_delivery_error}
                                        </div>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <a
                                        href={invite.invite_url}
                                        className="text-sm text-primary"
                                    >
                                        {invite.invite_url}
                                    </a>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <div className="flex justify-end gap-2">
                                        {invite.status === 'pending' && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    handleResend(invite.id)
                                                }
                                                disabled={
                                                    resendForm.processing ||
                                                    retryDeliveryForm.processing
                                                }
                                            >
                                                Resend
                                            </Button>
                                        )}
                                        {invite.status !== 'accepted' &&
                                            invite.delivery_status ===
                                                'failed' && (
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        handleRetryDelivery(
                                                            invite.id,
                                                        )
                                                    }
                                                    disabled={
                                                        retryDeliveryForm.processing ||
                                                        resendForm.processing
                                                    }
                                                >
                                                    Retry delivery
                                                </Button>
                                            )}
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() =>
                                                handleDelete(invite.id)
                                            }
                                            disabled={deleteForm.processing}
                                        >
                                            Revoke
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {invites.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {invites.links.map((link) => (
                        <Link
                            key={link.label}
                            href={link.url ?? '#'}
                            className={`rounded-md border px-3 py-1 text-sm ${
                                link.active
                                    ? 'border-primary text-primary'
                                    : 'text-muted-foreground'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
