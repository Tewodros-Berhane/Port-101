import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type PartnerOption = {
    id: string;
    name: string;
    code?: string | null;
};

type Props = {
    partners: PartnerOption[];
};

export default function ContactCreate({ partners }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('core.contacts.manage');
    const form = useForm({
        partner_id: '',
        name: '',
        email: '',
        phone: '',
        title: '',
        is_primary: false,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Contacts', href: '/core/contacts' },
                { title: 'Create', href: '/core/contacts/create' },
            ]}
        >
            <Head title="New Contact" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New contact</h1>
                    <p className="text-sm text-muted-foreground">
                        Add a contact for a partner.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/core/contacts">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/core/contacts');
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="partner_id">Partner</Label>
                    <select
                        id="partner_id"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.partner_id}
                        onChange={(event) =>
                            form.setData('partner_id', event.target.value)
                        }
                        required
                    >
                        <option value="">Select partner</option>
                        {partners.map((partner) => (
                            <option key={partner.id} value={partner.id}>
                                {partner.name}
                                {partner.code ? ` (${partner.code})` : ''}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.partner_id} />
                </div>

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
                    />
                    <InputError message={form.errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="phone">Phone</Label>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                    />
                    <InputError message={form.errors.phone} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="title">Title</Label>
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                    <InputError message={form.errors.title} />
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="is_primary"
                        checked={form.data.is_primary}
                        onCheckedChange={(value) =>
                            form.setData('is_primary', Boolean(value))
                        }
                    />
                    <Label htmlFor="is_primary">Primary contact</Label>
                </div>

                {canManage && (
                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Create contact
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
