import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type AdminUser = {
    id: string;
    name: string;
    email: string;
    created_at?: string | null;
};

type Props = {
    admins: {
        data: AdminUser[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

export default function PlatformAdminUsersIndex({ admins }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Platform Admins', href: '/platform/admin-users' },
            ]}
        >
            <Head title="Platform Admins" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Platform admins</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage super admin accounts for the platform.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/platform/admin-users/create">
                        New platform admin
                    </Link>
                </Button>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-max text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {admins.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={3}
                                >
                                    No platform admins yet.
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
                                    {formatDate(admin.created_at)}
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
