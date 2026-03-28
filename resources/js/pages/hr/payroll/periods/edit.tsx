import { Head } from '@inertiajs/react';
import PeriodForm from './period-form';

type Period = { id: string; name: string; pay_frequency: string; start_date: string; end_date: string; payment_date: string; status: string };
export default function HrPayrollPeriodEdit({ period }: { period: Period }) { return <><Head title={`Edit ${period.name}`} /><PeriodForm mode="edit" title={`Edit ${period.name}`} description="Adjust the dates or status for this payroll period." submitUrl={`/company/hr/payroll/periods/${period.id}`} method="put" form={period} /></>; }
