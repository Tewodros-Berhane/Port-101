import { Head, Link } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type JournalRow = {
    id: string;
    entry_number: string;
    status: string;
    requires_approval: boolean;
    approval_status: string;
    entry_date?: string | null;
    reference?: string | null;
    description: string;
    journal_name?: string | null;
    journal_code?: string | null;
    line_count: number;
    total_debit: number;
    total_credit: number;
};

type Props = {
    journals: {
        data: JournalRow[];
        links: Array<{
            url?: string | null;
            label: string;
            active: boolean;
        }>;
    };
};

export default function AccountingManualJournalsIndex({ journals }: Props) {
    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, {
                    title: 'Manual Journals',
                    href: '/company/accounting/manual-journals',
                },)}
        >
            <Head title="Manual Journals" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Manual journals</h1>
                    <p className="text-sm text-muted-foreground">
                        Post balanced adjustments into the general journal.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/accounting" label="Back to accounting" variant="outline" />
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/ledger">Ledger</Link>
                    </Button>
                    <Button asChild>
                        <Link href="/company/accounting/manual-journals/create">
                            New manual journal
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1040px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Entry</th>
                            <th className="px-4 py-3 font-medium">Date</th>
                            <th className="px-4 py-3 font-medium">Journal</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">
                                Approval
                            </th>
                            <th className="px-4 py-3 font-medium">Reference</th>
                            <th className="px-4 py-3 font-medium">
                                Description
                            </th>
                            <th className="px-4 py-3 font-medium">Lines</th>
                            <th className="px-4 py-3 font-medium">Debit</th>
                            <th className="px-4 py-3 font-medium">Credit</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {journals.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={10}
                                >
                                    No manual journals created yet.
                                </td>
                            </tr>
                        )}
                        {journals.data.map((manualJournal) => (
                            <tr key={manualJournal.id}>
                                <td className="px-4 py-3 font-medium">
                                    <Link
                                        href={`/company/accounting/manual-journals/${manualJournal.id}/edit`}
                                        className="text-primary"
                                    >
                                        {manualJournal.entry_number}
                                    </Link>
                                </td>
                                <td className="px-4 py-3">
                                    {manualJournal.entry_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {manualJournal.journal_code
                                        ? `${manualJournal.journal_code} - ${manualJournal.journal_name}`
                                        : '-'}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {manualJournal.status}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {manualJournal.requires_approval
                                        ? manualJournal.approval_status.replace(
                                              /_/g,
                                              ' ',
                                          )
                                        : 'Not required'}
                                </td>
                                <td className="px-4 py-3">
                                    {manualJournal.reference ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {manualJournal.description}
                                </td>
                                <td className="px-4 py-3">
                                    {manualJournal.line_count}
                                </td>
                                <td className="px-4 py-3">
                                    {manualJournal.total_debit.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {manualJournal.total_credit.toFixed(2)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {journals.links.length > 3 && (
                <div className="mt-4 flex flex-wrap gap-2 text-sm">
                    {journals.links
                        .filter(
                            (link) =>
                                link.label !== '&laquo; Previous' &&
                                link.label !== 'Next &raquo;',
                        )
                        .map((link, index) => (
                            <Button
                                key={`${link.label}-${index}`}
                                variant={link.active ? 'default' : 'outline'}
                                asChild={Boolean(link.url)}
                                disabled={!link.url}
                            >
                                {link.url ? (
                                    <Link href={link.url}>{link.label}</Link>
                                ) : (
                                    <span>{link.label}</span>
                                )}
                            </Button>
                        ))}
                </div>
            )}
        </AppLayout>
    );
}
