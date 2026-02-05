import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Invite = {
    id: string;
    email: string;
    name?: string | null;
    role: string;
    company?: string | null;
    token: string;
    invite_url: string;
    status: string;
    expires_at?: string | null;
    accepted_at?: string | null;
};

type Filters = {
    status?: string | null;
    role?: string | null;
    search?: string | null;
};

type Props = {
    invites: {
        data: Invite[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Filters;
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

const formatRole = (role: string) =>
    role.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function PlatformInvitesIndex({ invites, filters }: Props) {
    const form = useForm({
        status: filters.status ?? '',
        role: filters.role ?? '',
        search: filters.search ?? '',
    });

    const deleteForm = useForm({});

    const handleDelete = (inviteId: string) => {
        if (!confirm('Delete this invite?')) {
            return;
        }

        deleteForm.delete(`/platform/invites/${inviteId}`);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Invites', href: '/platform/invites' },
            ]}
        >
            <Head title="Invites" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Invites</h1>
                    <p className="text-sm text-muted-foreground">
                        Issue and track invite links for onboarding.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/platform/invites/create">New invite</Link>
                </Button>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/platform/invites', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            placeholder="Email or name"
                            value={form.data.search}
                            onChange={(event) =>
                                form.setData('search', event.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="role">Role</Label>
                        <select
                            id="role"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.role}
                            onChange={(event) =>
                                form.setData('role', event.target.value)
                            }
                        >
                            <option value="">All roles</option>
                            <option value="platform_admin">
                                Platform admin
                            </option>
                            <option value="company_owner">Company owner</option>
                            <option value="company_member">
                                Company member
                            </option>
                        </select>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Apply filters
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/platform/invites">Reset</Link>
                    </Button>
                </div>
            </form>

            <div className="mt-6 overflow-hidden rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Role</th>
                            <th className="px-4 py-3 font-medium">Company</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Expires</th>
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
                                <td className="px-4 py-3">
                                    {invite.company ?? '—'}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {invite.status}
                                </td>
                                <td className="px-4 py-3">
                                    {formatDate(invite.expires_at)}
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
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={() => handleDelete(invite.id)}
                                        disabled={deleteForm.processing}
                                    >
                                        Delete
                                    </Button>
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
