import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type CompanyOption = {
    id: string;
    name: string;
};

type Props = {
    companies: CompanyOption[];
};

export default function PlatformInviteCreate({ companies }: Props) {
    const form = useForm({
        email: '',
        name: '',
        role: 'company_owner',
        company_id: '',
        expires_at: '',
    });

    const requiresCompany = ['company_owner', 'company_member'].includes(
        form.data.role,
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Invites', href: '/platform/invites' },
                { title: 'Create', href: '/platform/invites/create' },
            ]}
        >
            <Head title="New Invite" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New invite</h1>
                    <p className="text-sm text-muted-foreground">
                        Generate an invite link for onboarding.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/platform/invites">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/platform/invites');
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="name">Name (optional)</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="role">Role</Label>
                    <select
                        id="role"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.role}
                        onChange={(event) =>
                            form.setData('role', event.target.value)
                        }
                    >
                        <option value="platform_admin">Platform admin</option>
                        <option value="company_owner">Company owner</option>
                        <option value="company_member">Company member</option>
                    </select>
                    <InputError message={form.errors.role} />
                </div>

                {requiresCompany && (
                    <div className="grid gap-2">
                        <Label htmlFor="company_id">Company</Label>
                        <select
                            id="company_id"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.company_id}
                            onChange={(event) =>
                                form.setData('company_id', event.target.value)
                            }
                            required
                        >
                            <option value="">Select company</option>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>
                                    {company.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.company_id} />
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="expires_at">Expires on</Label>
                    <Input
                        id="expires_at"
                        type="date"
                        value={form.data.expires_at}
                        onChange={(event) =>
                            form.setData('expires_at', event.target.value)
                        }
                    />
                    <InputError message={form.errors.expires_at} />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Create invite
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
