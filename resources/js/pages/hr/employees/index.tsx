import { Head, Link, router, useForm } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { companyModuleLinks, moduleBreadcrumbs } from '@/lib/page-navigation';

type DepartmentOption = {
    id: string;
    name: string;
    code?: string | null;
};

type EmployeeRow = {
    id: string;
    employee_number: string;
    display_name: string;
    work_email?: string | null;
    employment_status: string;
    employment_type: string;
    department_name?: string | null;
    designation_name?: string | null;
    manager_name?: string | null;
    linked_user_name?: string | null;
    hire_date?: string | null;
    can_view: boolean;
    can_edit: boolean;
};

type EmployeesResponse = {
    data: EmployeeRow[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

type Props = {
    filters: {
        search: string;
        status: string;
        department_id: string;
    };
    statuses: string[];
    departments: DepartmentOption[];
    employees: EmployeesResponse;
    abilities: {
        can_create_employee: boolean;
    };
};

export default function HrEmployeesIndex({ filters, statuses, departments, employees, abilities }: Props) {
    const filterForm = useForm(filters);

    return (
        <AppLayout
            breadcrumbs={moduleBreadcrumbs(companyModuleLinks.hr, { title: 'Employees', href: '/company/hr/employees' },)}
        >
            <Head title="Employees" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Employees</h1>
                    <p className="text-sm text-muted-foreground">
                        Employee records, linked users, departments, and contract readiness.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/hr" label="Back to HR" variant="outline" />
                    {abilities.can_create_employee && (
                        <Button asChild>
                            <Link href="/company/hr/employees/create">New employee</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <label className="text-sm font-medium" htmlFor="search">Search</label>
                        <Input
                            id="search"
                            value={filterForm.data.search}
                            onChange={(event) => filterForm.setData('search', event.target.value)}
                            placeholder="Employee number, name, or email"
                        />
                    </div>
                    <div className="grid gap-2">
                        <label className="text-sm font-medium" htmlFor="status">Status</label>
                        <select
                            id="status"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={filterForm.data.status}
                            onChange={(event) => filterForm.setData('status', event.target.value)}
                        >
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status.replaceAll('_', ' ')}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <label className="text-sm font-medium" htmlFor="department_id">Department</label>
                        <select
                            id="department_id"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={filterForm.data.department_id}
                            onChange={(event) => filterForm.setData('department_id', event.target.value)}
                        >
                            <option value="">All departments</option>
                            {departments.map((department) => (
                                <option key={department.id} value={department.id}>{department.name}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                    <Button
                        onClick={() =>
                            filterForm.get('/company/hr/employees', {
                                preserveState: true,
                                preserveScroll: true,
                                replace: true,
                            })
                        }
                        disabled={filterForm.processing}
                    >
                        Apply filters
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => {
                            filterForm.setData({ search: '', status: '', department_id: '' });
                            router.get('/company/hr/employees');
                        }}
                    >
                        Reset
                    </Button>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1100px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-3 py-2 font-medium">Number</th>
                            <th className="px-3 py-2 font-medium">Name</th>
                            <th className="px-3 py-2 font-medium">Status</th>
                            <th className="px-3 py-2 font-medium">Type</th>
                            <th className="px-3 py-2 font-medium">Department</th>
                            <th className="px-3 py-2 font-medium">Designation</th>
                            <th className="px-3 py-2 font-medium">Manager</th>
                            <th className="px-3 py-2 font-medium">Linked user</th>
                            <th className="px-3 py-2 font-medium">Hire date</th>
                            <th className="px-3 py-2 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {employees.data.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-muted-foreground" colSpan={10}>
                                    No employee records match the current filters.
                                </td>
                            </tr>
                        )}
                        {employees.data.map((employee) => (
                            <tr key={employee.id}>
                                <td className="px-3 py-2">{employee.employee_number}</td>
                                <td className="px-3 py-2 font-medium">{employee.display_name}</td>
                                <td className="px-3 py-2 capitalize">{employee.employment_status.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2 capitalize">{employee.employment_type.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2">{employee.department_name ?? '-'}</td>
                                <td className="px-3 py-2">{employee.designation_name ?? '-'}</td>
                                <td className="px-3 py-2">{employee.manager_name ?? '-'}</td>
                                <td className="px-3 py-2">{employee.linked_user_name ?? '-'}</td>
                                <td className="px-3 py-2">{employee.hire_date ?? '-'}</td>
                                <td className="px-3 py-2">
                                    <div className="flex flex-wrap gap-2">
                                        {employee.can_view && (
                                            <Button variant="ghost" asChild>
                                                <Link href={`/company/hr/employees/${employee.id}`}>View</Link>
                                            </Button>
                                        )}
                                        {employee.can_edit && (
                                            <Button variant="ghost" asChild>
                                                <Link href={`/company/hr/employees/${employee.id}/edit`}>Edit</Link>
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {employees.links.length > 3 && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {employees.links.map((link, index) => (
                        <Button
                            key={`${link.label}-${index}`}
                            variant={link.active ? 'default' : 'outline'}
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url)}
                        >
                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        </Button>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
