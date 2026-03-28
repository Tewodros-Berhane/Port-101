import { Head } from '@inertiajs/react';
import AssignmentForm from './assignment-form';

type Shared = {
    employeeOptions: { id: string; name: string; employee_number?: string | null }[];
    contractOptions: { id: string; employee_id: string; contract_number: string; pay_frequency: string; salary_basis: string }[];
    salaryStructureOptions: { id: string; name: string; code: string }[];
    currencyOptions: { id: string; code: string; name: string }[];
};

type Assignment = { id: string; employee_id: string; contract_id: string; salary_structure_id: string; currency_id: string; effective_from: string; effective_to: string; pay_frequency: string; salary_basis: string; base_salary_amount: string; hourly_rate: string; payroll_group: string; is_active: boolean; notes: string };
export default function HrCompensationAssignmentEdit({ assignment, ...rest }: Shared & { assignment: Assignment }) { return <><Head title="Edit compensation assignment" /><AssignmentForm mode="edit" title="Edit compensation assignment" description="Adjust the payroll terms applied to this employee." submitUrl={`/company/hr/payroll/assignments/${assignment.id}`} method="put" form={assignment} {...rest} /></>; }
