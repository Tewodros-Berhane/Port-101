import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Lead = {
    id: string;
    title: string;
    stage: string;
    partner_name?: string | null;
    estimated_value: number;
    expected_close_date?: string | null;
    converted_at?: string | null;
};

type Props = {
    leads: {
        data: Lead[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function SalesLeadsIndex({ leads }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('sales.leads.manage');

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Sales', href: '/company/sales' },
                { title: 'Leads', href: '/company/sales/leads' },
            ]}
        >
            <Head title="Sales Leads" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Leads</h1>
                    <p className="text-sm text-muted-foreground">
                        Track lead progression from discovery to conversion.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href="/company/sales/leads/create">New lead</Link>
                    </Button>
                )}
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[900px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Title</th>
                            <th className="px-4 py-3 font-medium">Stage</th>
                            <th className="px-4 py-3 font-medium">Partner</th>
                            <th className="px-4 py-3 font-medium">
                                Est. value
                            </th>
                            <th className="px-4 py-3 font-medium">
                                Expected close
                            </th>
                            <th className="px-4 py-3 font-medium">
                                Converted
                            </th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {leads.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={7}
                                >
                                    No leads yet.
                                </td>
                            </tr>
                        )}
                        {leads.data.map((lead) => (
                            <tr key={lead.id}>
                                <td className="px-4 py-3 font-medium">
                                    {lead.title}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {lead.stage}
                                </td>
                                <td className="px-4 py-3">
                                    {lead.partner_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {lead.estimated_value.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {lead.expected_close_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {lead.converted_at ? 'Yes' : 'No'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <div className="inline-flex items-center gap-3">
                                        {canManage && (
                                            <Link
                                                href={`/company/sales/leads/${lead.id}/edit`}
                                                className="text-sm font-medium text-primary"
                                            >
                                                Edit
                                            </Link>
                                        )}
                                        <Link
                                            href={`/company/sales/quotes/create?lead=${lead.id}`}
                                            className="text-sm font-medium text-primary"
                                        >
                                            Quote
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {leads.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {leads.links.map((link) => (
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
