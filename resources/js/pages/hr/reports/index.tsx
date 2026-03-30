import { Head, Link, useForm } from '@inertiajs/react';
import { FileSpreadsheet, FileText } from 'lucide-react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type ReportCatalogItem = {
    key: string;
    title: string;
    description: string;
    row_count: number;
};

type Props = {
    filters: {
        trend_window: number;
        start_date?: string | null;
        end_date?: string | null;
    };
    reportCatalog: ReportCatalogItem[];
    canExport: boolean;
};

export default function HrReportsIndex({ filters, reportCatalog, canExport }: Props) {
    const filterForm = useForm({
        trend_window: String(filters.trend_window ?? 30),
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
    });

    const buildQuery = () =>
        new URLSearchParams({
            trend_window: filterForm.data.trend_window,
            start_date: filterForm.data.start_date,
            end_date: filterForm.data.end_date,
        }).toString();

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.hr, { title: 'Reports', href: '/company/hr/reports' },)}
        >
            <Head title="HR Reports" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">HR reports</h1>
                    <p className="text-sm text-muted-foreground">
                        Headcount, leave, attendance, reimbursements, and payroll exports for the current HR scope.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/hr" label="Back to HR" variant="outline" />
                    <Button variant="outline" asChild>
                        <Link href="/company/hr/payroll">Payroll workspace</Link>
                    </Button>
                </div>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    filterForm.get('/company/hr/reports', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="trend_window">Trend window</Label>
                        <select
                            id="trend_window"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={filterForm.data.trend_window}
                            onChange={(event) => filterForm.setData('trend_window', event.target.value)}
                        >
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="start_date">Start date</Label>
                        <Input
                            id="start_date"
                            type="date"
                            value={filterForm.data.start_date}
                            onChange={(event) => filterForm.setData('start_date', event.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="end_date">End date</Label>
                        <Input
                            id="end_date"
                            type="date"
                            value={filterForm.data.end_date}
                            onChange={(event) => filterForm.setData('end_date', event.target.value)}
                        />
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <Button type="submit" disabled={filterForm.processing}>
                        Apply filters
                    </Button>
                    <Button variant="outline" type="button" asChild>
                        <Link href="/company/hr/reports">Reset</Link>
                    </Button>
                </div>
            </form>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {reportCatalog.map((report) => {
                    const query = buildQuery();
                    const pdfUrl = `/company/hr/reports/export/${report.key}?${query}&format=pdf`;
                    const xlsxUrl = `/company/hr/reports/export/${report.key}?${query}&format=xlsx`;

                    return (
                        <div key={report.key} className="rounded-xl border bg-card p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold">{report.title}</p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {report.description}
                                    </p>
                                </div>
                                <span className="rounded-md border px-2 py-1 text-xs text-muted-foreground">
                                    {report.row_count} rows
                                </span>
                            </div>

                            <div className="mt-4 inline-flex items-center gap-2">
                                <Button variant="outline" asChild disabled={!canExport}>
                                    <a href={pdfUrl}>
                                        <FileText className="size-4" />
                                        PDF
                                    </a>
                                </Button>
                                <Button variant="outline" asChild disabled={!canExport}>
                                    <a href={xlsxUrl}>
                                        <FileSpreadsheet className="size-4" />
                                        Excel
                                    </a>
                                </Button>
                            </div>
                        </div>
                    );
                })}
            </div>
        </AppLayout>
    );
}
