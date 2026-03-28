import { Head } from '@inertiajs/react';
import AssignmentForm from './assignment-form';

type Shared = {
    employeeOptions: { id: string; name: string; employee_number?: string | null }[];
    contractOptions: { id: string; employee_id: string; contract_number: string; pay_frequency: string; salary_basis: string }[];
    salaryStructureOptions: { id: string; name: string; code: string }[];
    currencyOptions: { id: string; code: string; name: string }[];
};

type AssignmentFormData = { employee_id: string; contract_id: string; salary_structure_id: string; currency_id: string; effective_from: string; effective_to: string; pay_frequency: string; salary_basis: string; base_salary_amount: string; hourly_rate: string; payroll_group: string; is_active: boolean; notes: string };
export default function HrCompensationAssignmentCreate(props: Shared & { form: AssignmentFormData }) { return <><Head title="New compensation assignment" /><AssignmentForm mode="create" title="New compensation assignment" description="Attach payroll terms to an employee and optionally link them to a contract." submitUrl="/company/hr/payroll/assignments" method="post" {...props} /></>; }
