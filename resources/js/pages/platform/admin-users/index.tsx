import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type AdminRow = {
    id: string;
    name: string;
    email: string;
    status: 'active' | 'pending_invite' | 'expired_invite';
    delivery_status?: string | null;
    created_at?: string | null;
    expires_at?: string | null;
};

type Props = {
    admins: {
        data: AdminRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const resolveStatusLabel = (status: AdminRow['status']) => {
    if (status === 'active') {
        return 'Active';
    }

    if (status === 'pending_invite') {
        return 'Pending invite';
    }

    return 'Expired invite';
};

const resolveStatusVariant = (status: AdminRow['status']) => {
    if (status === 'active') {
        return 'default' as const;
    }

    if (status === 'pending_invite') {
        return 'secondary' as const;
    }

    return 'outline' as const;
};

export default function PlatformAdminUsersIndex({ admins }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Platform Admins', href: '/platform/admin-users' },
            ]}
        >
            <Head title="Platform Admins" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Platform admins</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage active superadmins and pending admin invites for
                        the platform.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/platform/invites?role=platform_admin">
                            Open invites
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href="/platform/admin-users/create">
                            Invite platform admin
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4 text-sm text-muted-foreground">
                A platform admin becomes active only after they accept the
                invite and complete onboarding.
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[920px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Delivery</th>
                            <th className="px-4 py-3 font-medium">Created</th>
                            <th className="px-4 py-3 font-medium">Expires</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {admins.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={6}
                                >
                                    No platform admins or invites yet.
                                </td>
                            </tr>
                        )}
                        {admins.data.map((admin) => (
                            <tr key={admin.id}>
                                <td className="px-4 py-3 font-medium">
                                    {admin.name}
                                </td>
                                <td className="px-4 py-3">{admin.email}</td>
                                <td className="px-4 py-3">
                                    <Badge
                                        variant={resolveStatusVariant(admin.status)}
                                    >
                                        {resolveStatusLabel(admin.status)}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 capitalize text-muted-foreground">
                                    {admin.delivery_status
                                        ? admin.delivery_status.replaceAll('_', ' ')
                                        : '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {formatDate(admin.created_at)}
                                </td>
                                <td className="px-4 py-3">
                                    {formatDate(admin.expires_at)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {admins.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {admins.links.map((link) => (
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
