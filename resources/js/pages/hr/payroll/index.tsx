import { Head, Link } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Props = {
    summary: {
        open_periods: number;
        prepared_runs: number;
        pending_my_approvals: number;
        posted_30d: number;
        payslips_30d: number;
        net_posted_30d: number;
    };
    structures: { id: string; name: string; code: string; line_count: number; is_active: boolean; can_edit: boolean }[];
    assignments: { id: string; employee_name?: string | null; salary_structure_name?: string | null; pay_frequency: string; salary_basis: string; base_salary_amount?: number | null; hourly_rate?: number | null; currency_code?: string | null; can_edit: boolean }[];
    periods: { id: string; name: string; pay_frequency: string; start_date?: string | null; end_date?: string | null; payment_date?: string | null; status: string; can_edit: boolean }[];
    runs: { id: string; run_number: string; period_name?: string | null; approver_name?: string | null; status: string; total_net: number; entry_number?: string | null; can_view: boolean }[];
    payslips: { data: { id: string; payslip_number: string; employee_name?: string | null; period_name?: string | null; status: string; net_pay: number; can_view: boolean }[]; links: { url: string | null; label: string; active: boolean }[] };
    abilities: { can_manage_payroll: boolean };
};

const labelize = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function HrPayrollIndex({ summary, structures, assignments, periods, runs, payslips, abilities }: Props) {
    return (
        <AppLayout breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.hr, { title: 'Payroll', href: '/company/hr/payroll' })}>
            <Head title="Payroll workspace" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Payroll workspace</h1>
                        <p className="text-sm text-muted-foreground">Salary setup, payroll runs, approvals, postings, and employee payslips.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <BackLinkAction href="/company/hr" label="Back to HR" variant="outline" />
                        {abilities.can_manage_payroll && <Button variant="outline" asChild><Link href="/company/hr/payroll/structures/create">New structure</Link></Button>}
                        {abilities.can_manage_payroll && <Button variant="outline" asChild><Link href="/company/hr/payroll/assignments/create">New assignment</Link></Button>}
                        {abilities.can_manage_payroll && <Button variant="outline" asChild><Link href="/company/hr/payroll/periods/create">New period</Link></Button>}
                        {abilities.can_manage_payroll && <Button asChild><Link href="/company/hr/payroll/runs/create">New run</Link></Button>}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <Metric label="Open periods" value={summary.open_periods} />
                    <Metric label="Prepared" value={summary.prepared_runs} />
                    <Metric label="My approvals" value={summary.pending_my_approvals} />
                    <Metric label="Posted 30d" value={summary.posted_30d} />
                    <Metric label="Payslips 30d" value={summary.payslips_30d} />
                    <Metric label="Net 30d" value={summary.net_posted_30d.toFixed(2)} />
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Section title="Payroll runs" description="Prepare, approve, and post payroll cycles.">
                        <GridTable headers={['Run', 'Period', 'Approver', 'Status', 'Net', 'Entry', 'Actions']} empty="No payroll runs yet.">
                            {runs.map((run) => (
                                <tr key={run.id}>
                                    <td className="px-3 py-2 font-medium">{run.run_number}</td>
                                    <td className="px-3 py-2">{run.period_name ?? '-'}</td>
                                    <td className="px-3 py-2">{run.approver_name ?? '-'}</td>
                                    <td className="px-3 py-2">{labelize(run.status)}</td>
                                    <td className="px-3 py-2">{run.total_net.toFixed(2)}</td>
                                    <td className="px-3 py-2">{run.entry_number ?? '-'}</td>
                                    <td className="px-3 py-2">{run.can_view && <Button variant="outline" size="sm" asChild><Link href={`/company/hr/payroll/runs/${run.id}`}>Open</Link></Button>}</td>
                                </tr>
                            ))}
                        </GridTable>
                    </Section>

                    <Section title="Payslips" description="Employees only see the payslips they are allowed to view.">
                        <GridTable headers={['Payslip', 'Employee', 'Period', 'Status', 'Net', 'Actions']} empty="No payslips found.">
                            {payslips.data.map((payslip) => (
                                <tr key={payslip.id}>
                                    <td className="px-3 py-2 font-medium">{payslip.payslip_number}</td>
                                    <td className="px-3 py-2">{payslip.employee_name ?? '-'}</td>
                                    <td className="px-3 py-2">{payslip.period_name ?? '-'}</td>
                                    <td className="px-3 py-2">{labelize(payslip.status)}</td>
                                    <td className="px-3 py-2">{payslip.net_pay.toFixed(2)}</td>
                                    <td className="px-3 py-2">{payslip.can_view && <Button variant="outline" size="sm" asChild><Link href={`/company/hr/payroll/payslips/${payslip.id}`}>Open</Link></Button>}</td>
                                </tr>
                            ))}
                        </GridTable>
                        {payslips.links.length > 3 && <div className="mt-4 flex flex-wrap gap-2">{payslips.links.map((link, index) => <Button key={`${link.label}-${index}`} type="button" variant={link.active ? 'default' : 'outline'} disabled={!link.url} asChild={Boolean(link.url)}>{link.url ? <Link href={link.url} preserveScroll preserveState dangerouslySetInnerHTML={{ __html: link.label }} /> : <span dangerouslySetInnerHTML={{ __html: link.label }} />}</Button>)}</div>}
                    </Section>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <Section title="Structures" description="Reusable earnings and deductions.">
                        <GridTable headers={['Name', 'Code', 'Lines', 'State', 'Actions']} empty="No salary structures yet.">
                            {structures.map((structure) => (
                                <tr key={structure.id}>
                                    <td className="px-3 py-2 font-medium">{structure.name}</td>
                                    <td className="px-3 py-2">{structure.code}</td>
                                    <td className="px-3 py-2">{structure.line_count}</td>
                                    <td className="px-3 py-2">{structure.is_active ? 'Active' : 'Inactive'}</td>
                                    <td className="px-3 py-2">{structure.can_edit && <Button variant="outline" size="sm" asChild><Link href={`/company/hr/payroll/structures/${structure.id}/edit`}>Edit</Link></Button>}</td>
                                </tr>
                            ))}
                        </GridTable>
                    </Section>

                    <Section title="Assignments" description="Employee compensation terms in force.">
                        <GridTable headers={['Employee', 'Structure', 'Freq', 'Basis', 'Amount', 'Actions']} empty="No assignments yet.">
                            {assignments.map((assignment) => (
                                <tr key={assignment.id}>
                                    <td className="px-3 py-2">{assignment.employee_name ?? '-'}</td>
                                    <td className="px-3 py-2">{assignment.salary_structure_name ?? '-'}</td>
                                    <td className="px-3 py-2 capitalize">{assignment.pay_frequency}</td>
                                    <td className="px-3 py-2 capitalize">{assignment.salary_basis}</td>
                                    <td className="px-3 py-2">
                                        {assignment.base_salary_amount != null
                                            ? `${assignment.currency_code ?? ''} ${assignment.base_salary_amount.toFixed(2)}`
                                            : assignment.hourly_rate != null
                                              ? `${assignment.currency_code ?? ''} ${assignment.hourly_rate.toFixed(2)}/hr`
                                              : '-'}
                                    </td>
                                    <td className="px-3 py-2">{assignment.can_edit && <Button variant="outline" size="sm" asChild><Link href={`/company/hr/payroll/assignments/${assignment.id}/edit`}>Edit</Link></Button>}</td>
                                </tr>
                            ))}
                        </GridTable>
                    </Section>

                    <Section title="Periods" description="Payroll windows and payment dates.">
                        <GridTable headers={['Name', 'Freq', 'Range', 'Payment', 'State', 'Actions']} empty="No payroll periods yet.">
                            {periods.map((period) => (
                                <tr key={period.id}>
                                    <td className="px-3 py-2 font-medium">{period.name}</td>
                                    <td className="px-3 py-2 capitalize">{period.pay_frequency}</td>
                                    <td className="px-3 py-2">{period.start_date ?? '-'} to {period.end_date ?? '-'}</td>
                                    <td className="px-3 py-2">{period.payment_date ?? '-'}</td>
                                    <td className="px-3 py-2">{labelize(period.status)}</td>
                                    <td className="px-3 py-2">{period.can_edit && <Button variant="outline" size="sm" asChild><Link href={`/company/hr/payroll/periods/${period.id}/edit`}>Edit</Link></Button>}</td>
                                </tr>
                            ))}
                        </GridTable>
                    </Section>
                </div>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string | number }) { return <div className="rounded-xl border p-4"><p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p><p className="mt-2 text-2xl font-semibold">{value}</p></div>; }
function Section({ title, description, children }: { title: string; description: string; children: React.ReactNode }) { return <div className="rounded-xl border p-4"><div><h2 className="text-sm font-semibold">{title}</h2><p className="text-xs text-muted-foreground">{description}</p></div><div className="mt-4">{children}</div></div>; }
function GridTable({ headers, empty, children }: { headers: string[]; empty: string; children: React.ReactNode }) { const rows = Array.isArray(children) ? children : [children]; return <div className="overflow-x-auto rounded-lg border"><table className="w-full min-w-[760px] text-sm"><thead className="bg-muted/60 text-left"><tr>{headers.map((header) => <th key={header} className="px-3 py-2 font-medium">{header}</th>)}</tr></thead><tbody className="divide-y">{rows.filter(Boolean).length === 0 ? <tr><td className="px-3 py-6 text-center text-muted-foreground" colSpan={headers.length}>{empty}</td></tr> : children}</tbody></table></div>; }
