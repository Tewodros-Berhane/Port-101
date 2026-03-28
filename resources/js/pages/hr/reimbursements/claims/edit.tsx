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
    claim: {
        id: string;
        claim_number: string;
        status: string;
        employee_id: string;
        currency_id: string;
        project_id: string;
        notes: string;
        decision_notes?: string | null;
        lines: {
            id: string;
            category_id: string;
            expense_date: string;
            description: string;
            amount: string;
            tax_amount: string;
            project_id: string;
            category_name?: string | null;
            requires_receipt?: boolean;
            receipt_attachment?: {
                id: string;
                original_name: string;
                mime_type?: string | null;
                size: number;
            } | null;
        }[];
    };
};

export default function HrReimbursementClaimEdit({ claim, ...props }: Props) {
    return (
        <>
            <Head title={claim.claim_number} />
            <ClaimForm
                mode="edit"
                title={`Edit ${claim.claim_number}`}
                description="Complete receipts, adjust claim lines, and submit the reimbursement when it is ready for approval."
                submitUrl={`/company/hr/reimbursements/claims/${claim.id}`}
                method="put"
                backHref={`/company/hr/reimbursements/claims/${claim.id}/edit`}
                claimMeta={{
                    id: claim.id,
                    claim_number: claim.claim_number,
                    status: claim.status,
                    decision_notes: claim.decision_notes,
                }}
                form={{
                    employee_id: claim.employee_id,
                    currency_id: claim.currency_id,
                    project_id: claim.project_id,
                    notes: claim.notes,
                    action: 'save',
                    lines: claim.lines,
                }}
                {...props}
            />
        </>
    );
}
