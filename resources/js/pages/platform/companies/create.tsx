import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    defaultTimezone: string;
};

export default function PlatformCompanyCreate({ defaultTimezone }: Props) {
    const form = useForm({
        name: '',
        slug: '',
        timezone: defaultTimezone,
        currency_code: '',
        is_active: true,
        owner_name: '',
        owner_email: '',
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Companies', href: '/platform/companies' },
                { title: 'Create', href: '/platform/companies/create' },
            ]}
        >
            <Head title="Create Company" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New company</h1>
                    <p className="text-sm text-muted-foreground">
                        Create a company and assign an owner.
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
                    form.post('/platform/companies');
                }}
            >
                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Company details</h2>
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
                            <Label htmlFor="slug">Slug (optional)</Label>
                            <Input
                                id="slug"
                                value={form.data.slug}
                                onChange={(event) =>
                                    form.setData('slug', event.target.value)
                                }
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
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Owner details</h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="owner_name">Owner name</Label>
                            <Input
                                id="owner_name"
                                value={form.data.owner_name}
                                onChange={(event) =>
                                    form.setData(
                                        'owner_name',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.owner_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="owner_email">Owner email</Label>
                            <Input
                                id="owner_email"
                                type="email"
                                value={form.data.owner_email}
                                onChange={(event) =>
                                    form.setData(
                                        'owner_email',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.owner_email} />
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Create company
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
