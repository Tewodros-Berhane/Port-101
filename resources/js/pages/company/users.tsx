import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Props = {
    members: {
        id: string;
        name?: string | null;
        email?: string | null;
        role_id?: string | null;
        role?: string | null;
        is_owner: boolean;
    }[];
    roles: {
        id: string;
        name: string;
        slug: string;
    }[];
};

export default function CompanyUsers({ members, roles }: Props) {
    const [roleByMemberId, setRoleByMemberId] = useState<
        Record<string, string>
    >(() =>
        Object.fromEntries(
            members.map((member) => [member.id, member.role_id ?? '']),
        ),
    );

    useEffect(() => {
        setRoleByMemberId(
            Object.fromEntries(
                members.map((member) => [member.id, member.role_id ?? '']),
            ),
        );
    }, [members]);

    const updateRole = (memberId: string) => {
        const roleId = roleByMemberId[memberId];

        if (!roleId) {
            return;
        }

        router.put(
            `/company/users/${memberId}/role`,
            { role_id: roleId },
            {
                preserveScroll: true,
            },
        );
    };

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

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-max text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Role</th>
                            <th className="px-4 py-3 font-medium">Owner</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
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
                                <td className="px-4 py-3 text-right">
                                    {member.is_owner ? (
                                        <span className="text-xs text-muted-foreground">
                                            Owner role is locked
                                        </span>
                                    ) : (
                                        <div className="flex justify-end gap-2">
                                            <select
                                                className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                                value={
                                                    roleByMemberId[member.id] ??
                                                    ''
                                                }
                                                onChange={(event) =>
                                                    setRoleByMemberId(
                                                        (current) => ({
                                                            ...current,
                                                            [member.id]:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Select role
                                                </option>
                                                {roles.map((role) => (
                                                    <option
                                                        key={role.id}
                                                        value={role.id}
                                                    >
                                                        {role.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    updateRole(member.id)
                                                }
                                                disabled={
                                                    !roleByMemberId[
                                                        member.id
                                                    ] ||
                                                    roleByMemberId[
                                                        member.id
                                                    ] === (member.role_id ?? '')
                                                }
                                            >
                                                Update role
                                            </Button>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
