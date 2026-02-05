import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Company = {
    id: string;
    name: string;
    slug: string;
    timezone?: string | null;
    currency_code?: string | null;
    is_active: boolean;
    owner_id?: string | null;
    owner?: string | null;
    owner_email?: string | null;
    created_at?: string | null;
};

type OwnerOption = {
    id: string;
    name: string;
    email: string;
};

type Membership = {
    id: string;
    user: {
        id: string;
        name: string;
        email: string;
    };
    role?: string | null;
    is_owner: boolean;
};

type Props = {
    company: Company;
    owners: OwnerOption[];
    memberships: Membership[];
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

export default function PlatformCompanyShow({
    company,
    owners,
    memberships,
}: Props) {
    const form = useForm({
        name: company.name ?? '',
        slug: company.slug ?? '',
        timezone: company.timezone ?? '',
        currency_code: company.currency_code ?? '',
        is_active: company.is_active ?? true,
        owner_id: company.owner_id ?? '',
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Companies', href: '/platform/companies' },
                {
                    title: company.name,
                    href: `/platform/companies/${company.id}`,
                },
            ]}
        >
            <Head title={company.name} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">{company.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        Created {formatDate(company.created_at)}
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/platform/companies">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/platform/companies/${company.id}`);
                }}
            >
                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Company profile</h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Company name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                value={form.data.slug}
                                onChange={(event) =>
                                    form.setData('slug', event.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.slug} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="timezone">Timezone</Label>
                            <Input
                                id="timezone"
                                value={form.data.timezone}
                                onChange={(event) =>
                                    form.setData('timezone', event.target.value)
                                }
                            />
                            <InputError message={form.errors.timezone} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="currency_code">Currency code</Label>
                            <Input
                                id="currency_code"
                                value={form.data.currency_code}
                                onChange={(event) =>
                                    form.setData(
                                        'currency_code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                maxLength={3}
                            />
                            <InputError message={form.errors.currency_code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="owner_id">Owner</Label>
                            <select
                                id="owner_id"
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                value={form.data.owner_id}
                                onChange={(event) =>
                                    form.setData('owner_id', event.target.value)
                                }
                            >
                                <option value="">Select owner</option>
                                {owners.map((owner) => (
                                    <option key={owner.id} value={owner.id}>
                                        {owner.name} ({owner.email})
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.owner_id} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(value) =>
                                    form.setData('is_active', Boolean(value))
                                }
                            />
                            <Label htmlFor="is_active">Active</Label>
                        </div>
                    </div>

                    <div className="mt-4 flex items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    </div>
                </div>
            </form>

            <div className="mt-8">
                <h2 className="text-lg font-semibold">Members</h2>
                <div className="mt-4 overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">User</th>
                                <th className="px-4 py-3 font-medium">Role</th>
                                <th className="px-4 py-3 font-medium">Owner</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {memberships.length === 0 && (
                                <tr>
                                    <td
                                        className="px-4 py-8 text-center text-muted-foreground"
                                        colSpan={3}
                                    >
                                        No members yet.
                                    </td>
                                </tr>
                            )}
                            {memberships.map((membership) => (
                                <tr key={membership.id}>
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {membership.user.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {membership.user.email}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {membership.role ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {membership.is_owner ? 'Yes' : '—'}
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
