import { Head } from '@inertiajs/react';
import ClaimForm from './claim-form';

type EmployeeOption = { id: string; name: string; employee_number?: string | null };
type CategoryOption = {
    id: string;
    name: string;
    code?: string | null;
    requires_receipt: boolean;
    is_project_rebillable: boolean;
};
type ProjectOption = { id: string; project_code: string; name: string };
type CurrencyOption = { id: string; code: string; name: string; symbol?: string | null };

type Props = {
    employeeOptions: EmployeeOption[];
    categoryOptions: CategoryOption[];
    projectOptions: ProjectOption[];
    currencyOptions: CurrencyOption[];
    form: {
        employee_id: string;
        currency_id: string;
        project_id: string;
        notes: string;
        action: string;
        lines: {
            id: string;
            category_id: string;
            expense_date: string;
            description: string;
            amount: string;
            tax_amount: string;
            project_id: string;
        }[];
    };
};

export default function HrReimbursementClaimCreate(props: Props) {
    return (
        <>
            <Head title="New reimbursement claim" />
            <ClaimForm
                mode="create"
                title="New reimbursement claim"
                description="Create a reimbursement draft, attach receipts if needed, and then submit it for manager and finance approval."
                submitUrl="/company/hr/reimbursements/claims"
                method="post"
                backHref="/company/hr/reimbursements/claims/create"
                {...props}
            />
        </>
    );
}
