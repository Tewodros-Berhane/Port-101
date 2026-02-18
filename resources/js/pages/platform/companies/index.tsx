import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Company = {
    id: string;
    name: string;
    slug: string;
    owner?: string | null;
    owner_email?: string | null;
    is_active: boolean;
    users_count: number;
};

type OwnerOption = {
    id: string;
    name: string;
    email: string;
};

type Filters = {
    search?: string | null;
    status?: string | null;
    owner_id?: string | null;
};

type Props = {
    companyRegistry: {
        data: Company[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Filters;
    owners: OwnerOption[];
};

export default function PlatformCompaniesIndex({
    companyRegistry,
    filters,
    owners,
}: Props) {
    const form = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
        owner_id: filters.owner_id ?? '',
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Companies', href: '/platform/companies' },
            ]}
        >
            <Head title="Companies" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Companies</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage company accounts and ownership.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/platform/companies/create">New company</Link>
                </Button>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/platform/companies', {
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
                            placeholder="Name, slug, owner"
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
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
                            <option value="">All owners</option>
                            {owners.map((owner) => (
                                <option key={owner.id} value={owner.id}>
                                    {owner.name} ({owner.email})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Apply filters
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/platform/companies">Reset</Link>
                    </Button>
                </div>
            </form>

            <div className="mt-6 overflow-hidden rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Owner</th>
                            <th className="px-4 py-3 font-medium">Members</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {companyRegistry.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={5}
                                >
                                    No companies found.
                                </td>
                            </tr>
                        )}
                        {companyRegistry.data.map((company) => (
                            <tr key={company.id}>
                                <td className="px-4 py-3 font-medium">
                                    <div>{company.name}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {company.slug}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <div>{company.owner ?? '—'}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {company.owner_email ?? '—'}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    {company.users_count}
                                </td>
                                <td className="px-4 py-3">
                                    {company.is_active ? 'Active' : 'Inactive'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/platform/companies/${company.id}`}
                                        className="text-sm font-medium text-primary"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {companyRegistry.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {companyRegistry.links.map((link) => (
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
