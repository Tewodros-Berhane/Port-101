import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

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
};

export default function ContactsIndex({ contacts }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.contacts.view');
    const canManage = hasPermission('core.contacts.manage');

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Contacts', href: '/core/contacts' }]}
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
                    <Button asChild>
                        <Link href="/core/contacts/create">New contact</Link>
                    </Button>
                )}
            </div>

            {canView ? (
                <>
                    <div className="mt-6 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
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
                                            {contact.partner ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.email ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.phone ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.title ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.is_primary
                                                ? 'Primary'
                                                : '—'}
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
