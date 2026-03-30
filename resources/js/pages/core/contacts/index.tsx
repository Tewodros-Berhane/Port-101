import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DrawerFormShell } from '@/components/drawers/drawer-form-shell';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { masterDataBreadcrumbs } from '@/lib/page-navigation';

type Contact = {
    id: string;
    name: string;
    partner?: string | null;
    email?: string | null;
    phone?: string | null;
    title?: string | null;
    is_primary: boolean;
};

type Props = {
    contacts: {
        data: Contact[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    partners: PartnerOption[];
};

type PartnerOption = {
    id: string;
    name: string;
    code?: string | null;
};

export default function ContactsIndex({ contacts, partners }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.contacts.view');
    const canManage = hasPermission('core.contacts.manage');
    const [showCreateDrawer, setShowCreateDrawer] = useState(false);
    const createForm = useForm({
        partner_id: '',
        name: '',
        email: '',
        phone: '',
        title: '',
        is_primary: false,
    });

    const closeCreateDrawer = (open: boolean) => {
        setShowCreateDrawer(open);

        if (!open) {
            createForm.reset();
            createForm.clearErrors();
        }
    };

    return (
        <AppLayout
            breadcrumbs={masterDataBreadcrumbs({ title: 'Contacts', href: '/core/contacts' },)}
        >
            <Head title="Contacts" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Contacts</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage partner contacts.
                    </p>
                </div>
                {canManage && (
                    <Button
                        type="button"
                        onClick={() => setShowCreateDrawer(true)}
                    >
                        New contact
                    </Button>
                )}
            </div>

            {canManage && (
                <DrawerFormShell
                    open={showCreateDrawer}
                    onOpenChange={closeCreateDrawer}
                    title="New contact"
                    description="Add a partner contact without leaving the current directory."
                    footer={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeCreateDrawer(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                form="contact-create-drawer-form"
                                disabled={createForm.processing}
                            >
                                Create contact
                            </Button>
                        </>
                    }
                >
                    <form
                        id="contact-create-drawer-form"
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createForm.post('/core/contacts', {
                                onSuccess: () => closeCreateDrawer(false),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="contact-create-partner">Partner</Label>
                            <select
                                id="contact-create-partner"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={createForm.data.partner_id}
                                onChange={(event) =>
                                    createForm.setData(
                                        'partner_id',
                                        event.target.value,
                                    )
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
                            <InputError message={createForm.errors.partner_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="contact-create-name">Name</Label>
                            <Input
                                id="contact-create-name"
                                value={createForm.data.name}
                                onChange={(event) =>
                                    createForm.setData('name', event.target.value)
                                }
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="contact-create-email">Email</Label>
                                <Input
                                    id="contact-create-email"
                                    type="email"
                                    value={createForm.data.email}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={createForm.errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact-create-phone">Phone</Label>
                                <Input
                                    id="contact-create-phone"
                                    value={createForm.data.phone}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={createForm.errors.phone} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="contact-create-title">Title</Label>
                            <Input
                                id="contact-create-title"
                                value={createForm.data.title}
                                onChange={(event) =>
                                    createForm.setData('title', event.target.value)
                                }
                            />
                            <InputError message={createForm.errors.title} />
                        </div>

                        <div className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3">
                            <Checkbox
                                id="contact-create-primary"
                                checked={createForm.data.is_primary}
                                onCheckedChange={(value) =>
                                    createForm.setData('is_primary', Boolean(value))
                                }
                            />
                            <Label htmlFor="contact-create-primary">
                                Primary contact
                            </Label>
                        </div>
                    </form>
                </DrawerFormShell>
            )}

            {canView ? (
                <>
                    <div className="mt-6 overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-max text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Partner
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Phone
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Title
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Primary
                                    </th>
                                    {canManage && (
                                        <th className="px-4 py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {contacts.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 7 : 6}
                                        >
                                            No contacts yet.
                                        </td>
                                    </tr>
                                )}
                                {contacts.data.map((contact) => (
                                    <tr key={contact.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {contact.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.partner ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.email ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.phone ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.title ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.is_primary
                                                ? 'Primary'
                                                : '-'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/contacts/${contact.id}/edit`}
                                                    className="text-sm font-medium text-primary"
                                                >
                                                    Edit
                                                </Link>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {contacts.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {contacts.links.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.url ?? '#'}
                                    className={`rounded-md border px-3 py-1 text-sm ${
                                        link.active
                                            ? 'border-primary text-primary'
                                            : 'text-muted-foreground'
                                    } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div className="mt-6 rounded-xl border p-6 text-sm text-muted-foreground">
                    You do not have access to view contacts.
                </div>
            )}
        </AppLayout>
    );
}
