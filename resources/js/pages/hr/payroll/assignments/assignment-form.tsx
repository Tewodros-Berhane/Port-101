import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

type Props = {
    mode: 'create' | 'edit';
    title: string;
    description: string;
    submitUrl: string;
    method: 'post' | 'put';
    employeeOptions: { id: string; name: string; employee_number?: string | null }[];
    contractOptions: { id: string; employee_id: string; contract_number: string; pay_frequency: string; salary_basis: string }[];
    salaryStructureOptions: { id: string; name: string; code: string }[];
    currencyOptions: { id: string; code: string; name: string }[];
    form: { employee_id: string; contract_id: string; salary_structure_id: string; currency_id: string; effective_from: string; effective_to: string; pay_frequency: string; salary_basis: string; base_salary_amount: string; hourly_rate: string; payroll_group: string; is_active: boolean; notes: string };
};

export default function AssignmentForm({ mode, title, description, submitUrl, method, employeeOptions, contractOptions, salaryStructureOptions, currencyOptions, form: defaults }: Props) {
    const form = useForm(defaults);
    const filteredContracts = useMemo(() => !form.data.employee_id ? contractOptions : contractOptions.filter((contract) => contract.employee_id === form.data.employee_id), [contractOptions, form.data.employee_id]);
    const submit = () => method === 'put' ? form.put(submitUrl) : form.post(submitUrl);

    return (
        <AppLayout breadcrumbs={[{ title: 'HR', href: '/company/hr' }, { title: 'Payroll', href: '/company/hr/payroll' }, { title, href: submitUrl }]}>
            <div className="max-w-5xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">{title}</h1><p className="text-sm text-muted-foreground">{description}</p></div><Button variant="outline" asChild><Link href="/company/hr/payroll">Back to payroll</Link></Button></div>
                <form className="space-y-6 rounded-xl border p-4" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Field label="Employee" error={form.errors.employee_id}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)}><option value="">Select employee</option>{employeeOptions.map((employee) => <option key={employee.id} value={employee.id}>{employee.name}{employee.employee_number ? ` (${employee.employee_number})` : ''}</option>)}</select></Field>
                        <Field label="Contract" error={form.errors.contract_id}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.contract_id} onChange={(event) => form.setData('contract_id', event.target.value)}><option value="">No linked contract</option>{filteredContracts.map((contract) => <option key={contract.id} value={contract.id}>{contract.contract_number} · {contract.pay_frequency} · {contract.salary_basis}</option>)}</select></Field>
                        <Field label="Structure" error={form.errors.salary_structure_id}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.salary_structure_id} onChange={(event) => form.setData('salary_structure_id', event.target.value)}><option value="">No structure</option>{salaryStructureOptions.map((structure) => <option key={structure.id} value={structure.id}>{structure.name} ({structure.code})</option>)}</select></Field>
                        <Field label="Currency" error={form.errors.currency_id}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.currency_id} onChange={(event) => form.setData('currency_id', event.target.value)}><option value="">Use contract/default</option>{currencyOptions.map((currency) => <option key={currency.id} value={currency.id}>{currency.code} · {currency.name}</option>)}</select></Field>
                        <Field label="Effective from" error={form.errors.effective_from}><Input type="date" value={form.data.effective_from} onChange={(event) => form.setData('effective_from', event.target.value)} /></Field>
                        <Field label="Effective to" error={form.errors.effective_to}><Input type="date" value={form.data.effective_to} onChange={(event) => form.setData('effective_to', event.target.value)} /></Field>
                        <Field label="Pay frequency" error={form.errors.pay_frequency}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.pay_frequency} onChange={(event) => form.setData('pay_frequency', event.target.value)}><option value="monthly">Monthly</option><option value="biweekly">Biweekly</option><option value="weekly">Weekly</option></select></Field>
                        <Field label="Salary basis" error={form.errors.salary_basis}><select className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm" value={form.data.salary_basis} onChange={(event) => form.setData('salary_basis', event.target.value)}><option value="fixed">Fixed</option><option value="hourly">Hourly</option></select></Field>
                        <Field label="Base salary" error={form.errors.base_salary_amount}><Input value={form.data.base_salary_amount} onChange={(event) => form.setData('base_salary_amount', event.target.value)} placeholder="0.00" /></Field>
                        <Field label="Hourly rate" error={form.errors.hourly_rate}><Input value={form.data.hourly_rate} onChange={(event) => form.setData('hourly_rate', event.target.value)} placeholder="0.00" /></Field>
                        <Field label="Payroll group" error={form.errors.payroll_group}><Input value={form.data.payroll_group} onChange={(event) => form.setData('payroll_group', event.target.value)} placeholder="Optional grouping key" /></Field>
                        <div className="flex items-center gap-3 rounded-lg border px-3 py-3 text-sm"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', Boolean(checked))} /><span>Assignment is active</span></div>
                    </div>
                    <Field label="Notes" error={form.errors.notes}><textarea className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></Field>
                    <Button type="submit" disabled={form.processing}>{mode === 'create' ? 'Create assignment' : 'Save assignment'}</Button>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) { return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>; }
