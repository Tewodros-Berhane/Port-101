import { Head } from '@inertiajs/react';
import StructureForm from './structure-form';

type Structure = { id: string; name: string; code: string; is_active: boolean; notes: string; lines: { id?: string; line_type: string; calculation_type: string; code: string; name: string; amount: string; percentage_rate: string; is_active: boolean }[] };
export default function HrSalaryStructureEdit({ structure }: { structure: Structure }) { return <><Head title={`Edit ${structure.name}`} /><StructureForm mode="edit" title={`Edit ${structure.name}`} description="Adjust salary lines without leaving payroll." submitUrl={`/company/hr/payroll/structures/${structure.id}`} method="put" form={structure} /></>; }
