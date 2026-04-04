import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DestructiveConfirmDialog } from '@/components/feedback/destructive-confirm-dialog';
import InputError from '@/components/input-error';
import { ModalFormShell } from '@/components/modals/modal-form-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFeedbackToast } from '@/hooks/use-feedback-toast';
import AppLayout from '@/layouts/app-layout';
import { companyBreadcrumbs } from '@/lib/page-navigation';

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
    value ? new Date(value).toLocaleString() : '-';

export default function CompanyInvitesIndex({ invites }: Props) {
    const { clientToastHeaders, showPageFlashToast } = useFeedbackToast();
    const deleteForm = useForm({});
    const resendForm = useForm({});
    const retryDeliveryForm = useForm({});
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [revokeDialog, setRevokeDialog] = useState<Invite | null>(null);
    const createForm = useForm({
        email: '',
        name: '',
        expires_at: '',
    });

    const closeCreateModal = (open: boolean) => {
        setShowCreateModal(open);

        if (!open) {
            createForm.reset();
            createForm.clearErrors();
        }
    };

    const openRevokeDialog = (invite: Invite) => {
        deleteForm.clearErrors();
        setRevokeDialog(invite);
    };

    const closeRevokeDialog = (open: boolean) => {
        if (deleteForm.processing) {
            return;
        }

        if (!open) {
            deleteForm.reset();
            deleteForm.clearErrors();
            setRevokeDialog(null);
        }
    };

    const handleDelete = () => {
        if (!revokeDialog) {
            return;
        }

        deleteForm.delete(`/core/invites/${revokeDialog.id}`, {
            headers: clientToastHeaders,
            preserveScroll: true,
            onSuccess: (page) => {
                showPageFlashToast(page);
                deleteForm.reset();
                deleteForm.clearErrors();
                setRevokeDialog(null);
            },
        });
    };

    const handleResend = (inviteId: string) => {
        resendForm.post(`/core/invites/${inviteId}/resend`, {
            headers: clientToastHeaders,
            preserveScroll: true,
            onSuccess: (page) => showPageFlashToast(page),
        });
    };

    const handleRetryDelivery = (inviteId: string) => {
        retryDeliveryForm.post(`/core/invites/${inviteId}/retry-delivery`, {
            headers: clientToastHeaders,
            preserveScroll: true,
            onSuccess: (page) => showPageFlashToast(page),
        });
    };

    return (
        <AppLayout
            breadcrumbs={companyBreadcrumbs({ title: 'Owner Invites', href: '/core/invites' })}
        >
            <Head title="Owner Invites" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Owner invites</h1>
                    <p className="text-sm text-muted-foreground">
                        Use this screen for company-owner onboarding only.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/company/hr/employees/create">
                            Add employee instead
                        </Link>
                    </Button>
                    <Button type="button" onClick={() => setShowCreateModal(true)}>
                        New owner invite
                    </Button>
                </div>
            </div>

            <ModalFormShell
                open={showCreateModal}
                onOpenChange={closeCreateModal}
                title="New owner invite"
                description="Use this only for additional company-owner access."
            >
                <div className="rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-4 py-3 text-sm text-muted-foreground">
                    Employee and non-owner access starts from HR employees. Use this modal only for company-owner onboarding.
                </div>

                <form
                    className="grid gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        createForm.post('/core/invites', {
                            onSuccess: () => closeCreateModal(false),
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="owner-invite-email">Owner email</Label>
                        <Input
                            id="owner-invite-email"
                            type="email"
                            value={createForm.data.email}
                            onChange={(event) =>
                                createForm.setData('email', event.target.value)
                            }
                            required
                        />
                        <InputError message={createForm.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="owner-invite-name">
                            Owner name (optional)
                        </Label>
                        <Input
                            id="owner-invite-name"
                            value={createForm.data.name}
                            onChange={(event) =>
                                createForm.setData('name', event.target.value)
                            }
                        />
                        <InputError message={createForm.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="owner-invite-expires">
                            Expires on
                        </Label>
                        <Input
                            id="owner-invite-expires"
                            type="date"
                            value={createForm.data.expires_at}
                            onChange={(event) =>
                                createForm.setData(
                                    'expires_at',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={createForm.errors.expires_at} />
                    </div>

                    <div className="flex items-center justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => closeCreateModal(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={createForm.processing}>
                            Create owner invite
                        </Button>
                    </div>
                </form>
            </ModalFormShell>

            <div className="mt-6 rounded-xl border p-4 text-sm text-muted-foreground">
                Employee and non-owner app access now starts from HR employees.
                Use the employee record to provision system access, assign app
                roles, and manage resend, deactivation, reactivation, and role
                changes. Existing legacy member invites remain visible here
                until they are accepted or revoked.
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
                                        {invite.name ?? '-'}
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
                                            onClick={() => openRevokeDialog(invite)}
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
                            } ${
                                !link.url
                                    ? 'pointer-events-none opacity-50'
                                    : ''
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            <DestructiveConfirmDialog
                open={revokeDialog !== null}
                onOpenChange={closeRevokeDialog}
                title="Revoke owner invite?"
                description="This will invalidate the invite link. The recipient will need a new invite before they can join as a company owner."
                confirmLabel="Revoke invite"
                processingLabel="Revoking..."
                cancelLabel="Keep invite"
                processing={deleteForm.processing}
                onConfirm={handleDelete}
                helperText={
                    revokeDialog
                        ? `${revokeDialog.email}${revokeDialog.name ? ` | ${revokeDialog.name}` : ''}${revokeDialog.expires_at ? ` | Expires ${formatDate(revokeDialog.expires_at)}` : ''}`
                        : undefined
                }
            />
        </AppLayout>
    );
}
