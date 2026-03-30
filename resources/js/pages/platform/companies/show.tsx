import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { DetailHero } from '@/components/shell/detail-hero';
import { TabbedDetailShell } from '@/components/shell/tabbed-detail-shell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { platformBreadcrumbs } from '@/lib/page-navigation';

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
    value ? new Date(value).toLocaleString() : '-';

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

    const tabs = [
        {
            id: 'overview',
            label: 'Overview',
            content: (
                <Card>
                    <CardHeader>
                        <CardTitle>Company profile</CardTitle>
                        <CardDescription>
                            Update the company profile, ownership, and
                            operational defaults without leaving the detail
                            record.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-6"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.put(`/platform/companies/${company.id}`);
                            }}
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Company name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
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
                                            form.setData(
                                                'slug',
                                                event.target.value,
                                            )
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
                                            form.setData(
                                                'timezone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.timezone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="currency_code">
                                        Currency code
                                    </Label>
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
                                    <InputError
                                        message={form.errors.currency_code}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="owner_id">Owner</Label>
                                    <select
                                        id="owner_id"
                                        className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                        value={form.data.owner_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'owner_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Select owner</option>
                                        {owners.map((owner) => (
                                            <option
                                                key={owner.id}
                                                value={owner.id}
                                            >
                                                {owner.name} ({owner.email})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.owner_id} />
                                </div>

                                <div className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3">
                                    <Checkbox
                                        id="is_active"
                                        checked={form.data.is_active}
                                        onCheckedChange={(value) =>
                                            form.setData(
                                                'is_active',
                                                Boolean(value),
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_active">Active</Label>
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save changes
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            ),
        },
        {
            id: 'members',
            label: 'Members',
            content: (
                <Card>
                    <CardHeader>
                        <CardTitle>Members</CardTitle>
                        <CardDescription>
                            Active company memberships and owner assignment for
                            this tenant.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table className="min-w-[760px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Owner</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {memberships.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No members yet.
                                        </TableCell>
                                    </TableRow>
                                )}

                                {memberships.map((membership) => (
                                    <TableRow key={membership.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {membership.user.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {membership.user.email}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {membership.role ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {membership.is_owner ? 'Yes' : '-'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={platformBreadcrumbs({ title: 'Companies', href: '/platform/companies' },
                {
                    title: company.name,
                    href: `/platform/companies/${company.id}`,
                },)}
        >
            <Head title={company.name} />

            <TabbedDetailShell
                hero={
                    <DetailHero
                        title={company.name}
                        description="Platform-level tenant profile and membership overview."
                        status={
                            <StatusBadge
                                status={
                                    company.is_active ? 'active' : 'inactive'
                                }
                            />
                        }
                        meta={
                            <>
                                <span>Slug {company.slug}</span>
                                <span>|</span>
                                <span>
                                    Owner {company.owner ?? 'Unassigned'}
                                </span>
                                <span>|</span>
                                <span>Created {formatDate(company.created_at)}</span>
                            </>
                        }
                        actions={
                            <BackLinkAction href="/platform/companies" label="Back to companies
                                " variant="outline" />
                        }
                        metrics={[
                            {
                                label: 'Members',
                                value: memberships.length,
                            },
                            {
                                label: 'Owner',
                                value: company.owner ?? 'Unassigned',
                                description:
                                    company.owner_email ?? 'No owner email',
                            },
                            {
                                label: 'Timezone',
                                value: company.timezone ?? '-',
                            },
                            {
                                label: 'Currency',
                                value: company.currency_code ?? '-',
                            },
                        ]}
                    />
                }
                tabs={tabs}
                defaultTab="overview"
            />
        </AppLayout>
    );
}
