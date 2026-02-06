import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';

type Props = {
    company: {
        id?: string | null;
        name?: string | null;
        slug?: string | null;
        timezone?: string | null;
        currency_code?: string | null;
        owner?: string | null;
        owner_email?: string | null;
    };
};

export default function CompanySettings({ company }: Props) {
    const form = useForm({
        name: company.name ?? '',
        timezone: company.timezone ?? 'UTC',
        currency_code: company.currency_code ?? '',
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Settings', href: '/company/settings' },
            ]}
        >
            <Head title="Company Settings" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Company settings</h1>
                    <p className="text-sm text-muted-foreground">
                        Update company profile and operational defaults.
                    </p>
                </div>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put('/company/settings');
                }}
            >
                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Profile</h2>
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
                                value={company.slug ?? ''}
                                disabled
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="timezone">Timezone</Label>
                            <Input
                                id="timezone"
                                value={form.data.timezone}
                                onChange={(event) =>
                                    form.setData('timezone', event.target.value)
                                }
                                required
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
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Ownership</h2>
                    <div className="mt-3 text-sm text-muted-foreground">
                        <p>Owner: {company.owner ?? '—'}</p>
                        <p>Email: {company.owner_email ?? '—'}</p>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Save settings
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
