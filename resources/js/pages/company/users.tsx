import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { companyBreadcrumbs } from '@/lib/page-navigation';

type Props = {
    members: {
        id: string;
        name?: string | null;
        email?: string | null;
        role?: string | null;
        is_owner: boolean;
        employee?: {
            id: string;
            display_name?: string | null;
        } | null;
    }[];
    canManageOwnerInvites: boolean;
};

export default function CompanyUsers({
    members,
    canManageOwnerInvites,
}: Props) {
    return (
        <AppLayout
            breadcrumbs={companyBreadcrumbs({ title: 'Users', href: '/company/users' })}
        >
            <Head title="Company Users" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Company users</h1>
                    <p className="text-sm text-muted-foreground">
                        Review active system users. Employee onboarding and access lifecycle changes should be managed from HR employees.
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <Link
                        href="/company/hr/employees"
                        className="text-sm font-medium text-primary"
                    >
                        Open employee directory
                    </Link>
                    {canManageOwnerInvites && (
                        <Link
                            href="/core/invites"
                            className="text-sm font-medium text-primary"
                        >
                            Manage owner invites
                        </Link>
                    )}
                </div>
            </div>

            <div className="mt-4 rounded-xl border p-4 text-sm text-muted-foreground">
                Use the employee record as the primary onboarding flow. Create or update the employee in HR, decide whether they need system access, and manage invite, deactivation, reactivation, and role changes there.
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-max text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Employee</th>
                            <th className="px-4 py-3 font-medium">Role</th>
                            <th className="px-4 py-3 font-medium">Owner</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {members.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={5}
                                >
                                    No users found.
                                </td>
                            </tr>
                        )}
                        {members.map((member) => (
                            <tr key={member.id}>
                                <td className="px-4 py-3 font-medium">
                                    {member.name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {member.email ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {member.employee ? (
                                        <Link
                                            href={`/company/hr/employees/${member.employee.id}`}
                                            className="text-primary underline-offset-4 hover:underline"
                                        >
                                            {member.employee.display_name ??
                                                'Open employee'}
                                        </Link>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            No employee record
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    {member.role ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {member.is_owner ? 'Yes' : '-'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
