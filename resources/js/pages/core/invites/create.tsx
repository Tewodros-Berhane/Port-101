import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyBreadcrumbs } from '@/lib/page-navigation';

export default function CompanyInviteCreate() {
    const form = useForm({
        email: '',
        name: '',
        expires_at: '',
    });

    return (
        <AppLayout
            breadcrumbs={companyBreadcrumbs({ title: 'Owner Invites', href: '/core/invites' }, { title: 'Create', href: '/core/invites/create' })}
        >
            <Head title="New Owner Invite" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New owner invite</h1>
                    <p className="text-sm text-muted-foreground">
                        Use this screen only for additional company-owner
                        access.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/company/hr/employees/create">
                            Add employee instead
                        </Link>
                    </Button>
                    <BackLinkAction href="/core/invites" label="Back to invites" variant="ghost" />
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4 text-sm text-muted-foreground">
                Employee and non-owner app access is now created from HR
                employees. Create the employee record first, enable system
                access there, and let the employee flow issue the invite with
                the correct app role.
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/core/invites');
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="email">Owner email</Label>
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
                    <Label htmlFor="name">Owner name (optional)</Label>
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
                        Create owner invite
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
