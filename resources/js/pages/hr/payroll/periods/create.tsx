import { Head } from '@inertiajs/react';
import PeriodForm from './period-form';

type FormData = { name: string; pay_frequency: string; start_date: string; end_date: string; payment_date: string; status: string };
export default function HrPayrollPeriodCreate({ form }: { form: FormData }) { return <><Head title="New payroll period" /><PeriodForm mode="create" title="New payroll period" description="Define the earning window and payment date for a payroll cycle." submitUrl="/company/hr/payroll/periods" method="post" form={form} /></>; }
