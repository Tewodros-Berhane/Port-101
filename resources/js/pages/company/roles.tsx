import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

type Props = {
    roles: {
        id: string;
        name: string;
        slug: string;
        description?: string | null;
        permission_count: number;
    }[];
};

export default function CompanyRoles({ roles }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Roles', href: '/company/roles' },
            ]}
        >
            <Head title="Company Roles" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Roles</h1>
                    <p className="text-sm text-muted-foreground">
                        Review role definitions and permission coverage.
                    </p>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-max text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Slug</th>
                            <th className="px-4 py-3 font-medium">
                                Description
                            </th>
                            <th className="px-4 py-3 font-medium">
                                Permissions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {roles.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={4}
                                >
                                    No roles found.
                                </td>
                            </tr>
                        )}
                        {roles.map((role) => (
                            <tr key={role.id}>
                                <td className="px-4 py-3 font-medium">
                                    {role.name}
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {role.slug}
                                </td>
                                <td className="px-4 py-3">
                                    {role.description ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {role.permission_count}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
