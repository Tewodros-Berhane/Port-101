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
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

export default function PlatformDashboard({ stats, recentCompanies }: Props) {
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
        </AppLayout>
    );
}
