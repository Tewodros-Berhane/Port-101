import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    stats: {
        companies: number;
        active_companies: number;
        users: number;
        audit_logs: number;
    };
    recentCompanies: {
        id: string;
        name: string;
        slug: string;
        owner?: string | null;
        is_active: boolean;
        created_at?: string | null;
    }[];
    recentInvites: {
        id: string;
        email: string;
        role: string;
        company?: string | null;
        status: string;
        delivery_status?: string | null;
        created_by?: string | null;
        created_at?: string | null;
    }[];
    recentAdminActions: {
        id: string;
        action: string;
        record_type: string;
        record_id: string;
        company?: string | null;
        actor?: string | null;
        created_at?: string | null;
    }[];
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

const formatRole = (role: string) =>
    role.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function PlatformDashboard({
    stats,
    recentCompanies,
    recentInvites,
    recentAdminActions,
}: Props) {
    return (
        <AppLayout
            breadcrumbs={[{ title: 'Platform', href: '/platform/dashboard' }]}
        >
            <Head title="Platform Dashboard" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">
                        Platform dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Overview of platform activity and recent companies.
                    </p>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-4">
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">Companies</p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.companies}
                    </p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">Active</p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.active_companies}
                    </p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">Users</p>
                    <p className="mt-2 text-2xl font-semibold">{stats.users}</p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">Audit logs</p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.audit_logs}
                    </p>
                </div>
            </div>

            <div className="mt-8">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Recent companies</h2>
                    <Link
                        href="/platform/companies"
                        className="text-sm font-medium text-primary"
                    >
                        View all
                    </Link>
                </div>

                <div className="mt-4 overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Owner</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Created
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {recentCompanies.length === 0 && (
                                <tr>
                                    <td
                                        className="px-4 py-8 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No companies yet.
                                    </td>
                                </tr>
                            )}
                            {recentCompanies.map((company) => (
                                <tr key={company.id}>
                                    <td className="px-4 py-3 font-medium">
                                        {company.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {company.owner ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {company.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatDate(company.created_at)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-2">
                <div>
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            Recent invites
                        </h2>
                        <Link
                            href="/platform/invites"
                            className="text-sm font-medium text-primary"
                        >
                            View all
                        </Link>
                    </div>

                    <div className="mt-4 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Delivery
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentInvites.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={4}
                                        >
                                            No invites yet.
                                        </td>
                                    </tr>
                                )}
                                {recentInvites.map((invite) => (
                                    <tr key={invite.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {invite.email}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {invite.company ?? '—'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRole(invite.role)}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {invite.status}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {invite.delivery_status ??
                                                'pending'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h2 className="text-lg font-semibold">
                        Recent admin actions
                    </h2>

                    <div className="mt-4 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Action
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Record
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Actor
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentAdminActions.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={4}
                                        >
                                            No admin actions yet.
                                        </td>
                                    </tr>
                                )}
                                {recentAdminActions.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3 capitalize">
                                            {item.action}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {item.record_type}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {item.company ?? 'Platform'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.actor ?? 'System'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDate(item.created_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
