import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function PlatformAdminUserCreate() {
    const form = useForm({
        name: '',
        email: '',
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Platform Admins', href: '/platform/admin-users' },
                { title: 'Create', href: '/platform/admin-users/create' },
            ]}
        >
            <Head title="Invite Platform Admin" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">
                        Invite platform admin
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Send an invite so the admin can accept access and set up
                        their account.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/platform/admin-users">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/platform/admin-users');
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
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

                <div className="rounded-xl border p-4 text-sm text-muted-foreground">
                    The invite link expires after 14 days. Accepted invites
                    promote the recipient to platform admin during onboarding.
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Send invite
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
