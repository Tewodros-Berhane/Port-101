import { Head } from '@inertiajs/react';
import StructureForm from './structure-form';

type FormData = { name: string; code: string; is_active: boolean; notes: string; lines: { id?: string; line_type: string; calculation_type: string; code: string; name: string; amount: string; percentage_rate: string; is_active: boolean }[] };
export default function HrSalaryStructureCreate({ form }: { form: FormData }) { return <><Head title="New salary structure" /><StructureForm mode="create" title="New salary structure" description="Configure earnings and deductions for payroll." submitUrl="/company/hr/payroll/structures" method="post" form={form} /></>; }
