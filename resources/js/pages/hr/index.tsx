import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Summary = {
    employees: number;
    active_employees: number;
    draft_employees: number;
    active_contracts: number;
    documents: number;
    documents_expiring_30d: number;
};

type EmployeeRow = {
    id: string;
    employee_number: string;
    display_name: string;
    employment_status: string;
    employment_type: string;
    department_name?: string | null;
    designation_name?: string | null;
    linked_user_name?: string | null;
    hire_date?: string | null;
    can_view: boolean;
};

type ContractRow = {
    id: string;
    employee_id: string;
    employee_name?: string | null;
    employee_number?: string | null;
    contract_number: string;
    status: string;
    end_date?: string | null;
};

type Props = {
    summary: Summary;
    recentEmployees: EmployeeRow[];
    contractsEndingSoon: ContractRow[];
    abilities: {
        can_create_employee: boolean;
    };
};

export default function HrIndex({ summary, recentEmployees, contractsEndingSoon, abilities }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'HR', href: '/company/hr' },
            ]}
        >
            <Head title="HR" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">HR module</h1>
                    <p className="text-sm text-muted-foreground">
                        Employee records, leave controls, attendance operations, and people workflows.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {abilities.can_create_employee && (
                        <Button asChild>
                            <Link href="/company/hr/employees/create">New employee</Link>
                        </Button>
                    )}
                    <Button variant="outline" asChild>
                        <Link href="/company/hr/leave">Leave workspace</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/hr/attendance">Attendance workspace</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/hr/employees">Employee workspace</Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <Metric label="Employees" value={summary.employees} />
                <Metric label="Active" value={summary.active_employees} />
                <Metric label="Draft" value={summary.draft_employees} />
                <Metric label="Contracts" value={summary.active_contracts} />
                <Metric label="Documents" value={summary.documents} />
                <Metric label="Docs expiring" value={summary.documents_expiring_30d} />
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-2">
                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Recent employees</h2>
                        <Button variant="ghost" asChild>
                            <Link href="/company/hr/employees">Open workspace</Link>
                        </Button>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Number</th>
                                    <th className="px-3 py-2 font-medium">Name</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Type</th>
                                    <th className="px-3 py-2 font-medium">Department</th>
                                    <th className="px-3 py-2 font-medium">Hire date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentEmployees.length === 0 && (
                                    <tr>
                                        <td className="px-3 py-6 text-center text-muted-foreground" colSpan={6}>
                                            No employees yet.
                                        </td>
                                    </tr>
                                )}
                                {recentEmployees.map((employee) => (
                                    <tr key={employee.id}>
                                        <td className="px-3 py-2">{employee.employee_number}</td>
                                        <td className="px-3 py-2">
                                            {employee.can_view ? (
                                                <Link href={`/company/hr/employees/${employee.id}`} className="font-medium text-primary">
                                                    {employee.display_name}
                                                </Link>
                                            ) : (
                                                employee.display_name
                                            )}
                                        </td>
                                        <td className="px-3 py-2 capitalize">{employee.employment_status.replaceAll('_', ' ')}</td>
                                        <td className="px-3 py-2 capitalize">{employee.employment_type.replaceAll('_', ' ')}</td>
                                        <td className="px-3 py-2">{employee.department_name ?? '-'}</td>
                                        <td className="px-3 py-2">{employee.hire_date ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Contracts ending soon</h2>
                        <Button variant="ghost" asChild>
                            <Link href="/company/hr/employees">View employees</Link>
                        </Button>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[620px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Employee</th>
                                    <th className="px-3 py-2 font-medium">Contract</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">End date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {contractsEndingSoon.length === 0 && (
                                    <tr>
                                        <td className="px-3 py-6 text-center text-muted-foreground" colSpan={4}>
                                            No contracts ending in the next 60 days.
                                        </td>
                                    </tr>
                                )}
                                {contractsEndingSoon.map((contract) => (
                                    <tr key={contract.id}>
                                        <td className="px-3 py-2">
                                            <Link href={`/company/hr/employees/${contract.employee_id}`} className="font-medium text-primary">
                                                {contract.employee_name ?? contract.employee_number ?? '-'}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2">{contract.contract_number}</td>
                                        <td className="px-3 py-2 capitalize">{contract.status}</td>
                                        <td className="px-3 py-2">{contract.end_date ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}
