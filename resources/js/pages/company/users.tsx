import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    members: {
        id: string;
        name?: string | null;
        email?: string | null;
        role?: string | null;
        is_owner: boolean;
    }[];
};

export default function CompanyUsers({ members }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Users', href: '/company/users' },
            ]}
        >
            <Head title="Company Users" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Company users</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage active members in your company.
                    </p>
                </div>
                <Link
                    href="/core/invites"
                    className="text-sm font-medium text-primary"
                >
                    Manage invites
                </Link>
            </div>

            <div className="mt-6 overflow-hidden rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Role</th>
                            <th className="px-4 py-3 font-medium">Owner</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {members.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={4}
                                >
                                    No users found.
                                </td>
                            </tr>
                        )}
                        {members.map((member) => (
                            <tr key={member.id}>
                                <td className="px-4 py-3 font-medium">
                                    {member.name ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {member.email ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {member.role ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {member.is_owner ? 'Yes' : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
