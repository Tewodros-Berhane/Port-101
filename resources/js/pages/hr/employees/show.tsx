import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Contract = {
    id: string;
    contract_number: string;
    status: string;
    start_date?: string | null;
    end_date?: string | null;
    pay_frequency: string;
    salary_basis: string;
    base_salary_amount?: number | null;
    hourly_rate?: number | null;
    currency_id?: string | null;
    currency_code?: string | null;
    working_days_per_week: number;
    standard_hours_per_day: number;
    is_payroll_eligible: boolean;
    notes?: string | null;
};

type Document = {
    id: string;
    document_type: string;
    document_name: string;
    is_private: boolean;
    valid_until?: string | null;
    original_name?: string | null;
    mime_type?: string | null;
    size?: number | null;
    download_url: string;
};

type Currency = {
    id: string;
    code: string;
    name: string;
};

type Option = {
    id: string;
    name: string;
};

type Props = {
    employee: {
        id: string;
        employee_number: string;
        display_name: string;
        first_name: string;
        last_name: string;
        employment_status: string;
        employment_type: string;
        work_email?: string | null;
        personal_email?: string | null;
        work_phone?: string | null;
        personal_phone?: string | null;
        date_of_birth?: string | null;
        hire_date?: string | null;
        termination_date?: string | null;
        timezone: string;
        country_code?: string | null;
        work_location?: string | null;
        bank_account_reference?: string | null;
        emergency_contact_name?: string | null;
        emergency_contact_phone?: string | null;
        notes?: string | null;
        department?: { id: string; name: string; code?: string | null } | null;
        designation?: { id: string; name: string; code?: string | null } | null;
        manager?: { id: string; display_name: string; employee_number?: string | null } | null;
        linked_user?: { id: string; name: string; email?: string | null } | null;
        attendance_approver?: { id: string; name: string; email?: string | null } | null;
        leave_approver?: { id: string; name: string; email?: string | null } | null;
        reimbursement_approver?: { id: string; name: string; email?: string | null } | null;
        contracts: Contract[];
        documents: Document[];
        attachment_count: number;
    };
    abilities: {
        can_edit_employee: boolean;
        can_view_private: boolean;
        can_manage_private: boolean;
    };
    contractStatuses: string[];
    payFrequencies: string[];
    salaryBases: string[];
    currencies: Currency[];
};

export default function HrEmployeeShow({ employee, abilities, contractStatuses, payFrequencies, salaryBases, currencies }: Props) {
    const [editingContractId, setEditingContractId] = useState<string | null>(null);
    const editingContract = useMemo(() => employee.contracts.find((contract) => contract.id === editingContractId) ?? null, [employee.contracts, editingContractId]);

    const contractForm = useForm({
        contract_number: editingContract?.contract_number ?? '',
        status: editingContract?.status ?? contractStatuses[0] ?? 'draft',
        start_date: editingContract?.start_date ?? '',
        end_date: editingContract?.end_date ?? '',
        pay_frequency: editingContract?.pay_frequency ?? payFrequencies[0] ?? 'monthly',
        salary_basis: editingContract?.salary_basis ?? salaryBases[0] ?? 'fixed',
        base_salary_amount: editingContract?.base_salary_amount?.toString() ?? '',
        hourly_rate: editingContract?.hourly_rate?.toString() ?? '',
        currency_id: editingContract?.currency_id ?? '',
        working_days_per_week: editingContract?.working_days_per_week?.toString() ?? '5',
        standard_hours_per_day: editingContract?.standard_hours_per_day?.toString() ?? '8',
        is_payroll_eligible: editingContract?.is_payroll_eligible ?? true,
        notes: editingContract?.notes ?? '',
    });

    const documentForm = useForm({
        document_type: '',
        document_name: '',
        valid_until: '',
        is_private: true,
        file: null as File | null,
    });

    const openContractForm = (contract?: Contract) => {
        setEditingContractId(contract?.id ?? null);
        contractForm.setData({
            contract_number: contract?.contract_number ?? '',
            status: contract?.status ?? contractStatuses[0] ?? 'draft',
            start_date: contract?.start_date ?? '',
            end_date: contract?.end_date ?? '',
            pay_frequency: contract?.pay_frequency ?? payFrequencies[0] ?? 'monthly',
            salary_basis: contract?.salary_basis ?? salaryBases[0] ?? 'fixed',
            base_salary_amount: contract?.base_salary_amount?.toString() ?? '',
            hourly_rate: contract?.hourly_rate?.toString() ?? '',
            currency_id: contract?.currency_id ?? '',
            working_days_per_week: contract?.working_days_per_week?.toString() ?? '5',
            standard_hours_per_day: contract?.standard_hours_per_day?.toString() ?? '8',
            is_payroll_eligible: contract?.is_payroll_eligible ?? true,
            notes: contract?.notes ?? '',
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'HR', href: '/company/hr' },
                { title: 'Employees', href: '/company/hr/employees' },
                { title: employee.display_name, href: `/company/hr/employees/${employee.id}` },
            ]}
        >
            <Head title={employee.display_name} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">{employee.display_name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {employee.employee_number} · {employee.employment_status.replaceAll('_', ' ')} · {employee.employment_type.replaceAll('_', ' ')}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/company/hr/employees">Back to employees</Link>
                    </Button>
                    {abilities.can_edit_employee && (
                        <Button asChild>
                            <Link href={`/company/hr/employees/${employee.id}/edit`}>Edit employee</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Metric label="Department" value={employee.department?.name ?? '-'} />
                <Metric label="Designation" value={employee.designation?.name ?? '-'} />
                <Metric label="Contracts" value={employee.contracts.length.toString()} />
                <Metric label="Documents" value={employee.attachment_count.toString()} />
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-2">
                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Employee profile</h2>
                    <div className="mt-4 grid gap-3 md:grid-cols-2 text-sm">
                        <Detail label="Work email" value={employee.work_email} />
                        <Detail label="Work phone" value={employee.work_phone} />
                        <Detail label="Linked user" value={employee.linked_user ? `${employee.linked_user.name}${employee.linked_user.email ? ` (${employee.linked_user.email})` : ''}` : null} />
                        <Detail label="Hire date" value={employee.hire_date} />
                        <Detail label="Manager" value={employee.manager ? `${employee.manager.display_name}${employee.manager.employee_number ? ` (${employee.manager.employee_number})` : ''}` : null} />
                        <Detail label="Location" value={employee.work_location} />
                        <Detail label="Timezone" value={employee.timezone} />
                        <Detail label="Country" value={employee.country_code} />
                    </div>

                    {abilities.can_view_private && (
                        <div className="mt-6 grid gap-3 md:grid-cols-2 text-sm">
                            <Detail label="Personal email" value={employee.personal_email} />
                            <Detail label="Personal phone" value={employee.personal_phone} />
                            <Detail label="Date of birth" value={employee.date_of_birth} />
                            <Detail label="Bank reference" value={employee.bank_account_reference} />
                            <Detail label="Emergency contact" value={employee.emergency_contact_name} />
                            <Detail label="Emergency phone" value={employee.emergency_contact_phone} />
                            <Detail label="Leave approver" value={employee.leave_approver?.name} />
                            <Detail label="Attendance approver" value={employee.attendance_approver?.name} />
                            <Detail label="Reimbursement approver" value={employee.reimbursement_approver?.name} />
                            <Detail label="Termination date" value={employee.termination_date} />
                        </div>
                    )}

                    {abilities.can_view_private && employee.notes && (
                        <div className="mt-6 rounded-lg border p-3 text-sm text-muted-foreground">
                            {employee.notes}
                        </div>
                    )}
                </div>

                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Contracts</h2>
                        {abilities.can_manage_private && (
                            <Button variant="outline" onClick={() => openContractForm()}>New contract</Button>
                        )}
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Contract</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Frequency</th>
                                    <th className="px-3 py-2 font-medium">Basis</th>
                                    <th className="px-3 py-2 font-medium">Start</th>
                                    <th className="px-3 py-2 font-medium">End</th>
                                    <th className="px-3 py-2 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {employee.contracts.length === 0 && (
                                    <tr>
                                        <td className="px-3 py-6 text-center text-muted-foreground" colSpan={7}>No contracts yet.</td>
                                    </tr>
                                )}
                                {employee.contracts.map((contract) => (
                                    <tr key={contract.id}>
                                        <td className="px-3 py-2">{contract.contract_number}</td>
                                        <td className="px-3 py-2 capitalize">{contract.status}</td>
                                        <td className="px-3 py-2 capitalize">{contract.pay_frequency}</td>
                                        <td className="px-3 py-2 capitalize">{contract.salary_basis}</td>
                                        <td className="px-3 py-2">{contract.start_date ?? '-'}</td>
                                        <td className="px-3 py-2">{contract.end_date ?? '-'}</td>
                                        <td className="px-3 py-2">
                                            {abilities.can_manage_private && (
                                                <div className="flex gap-2">
                                                    <Button variant="ghost" onClick={() => openContractForm(contract)}>Edit</Button>
                                                    <Button variant="ghost" onClick={() => contractForm.delete(`/company/hr/contracts/${contract.id}`)}>Delete</Button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {abilities.can_manage_private && (
                        <form
                            className="mt-4 grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (editingContractId) {
                                    contractForm.put(`/company/hr/contracts/${editingContractId}`);
                                    return;
                                }

                                contractForm.post(`/company/hr/employees/${employee.id}/contracts`);
                            }}
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field label="Contract number" error={contractForm.errors.contract_number}><Input value={contractForm.data.contract_number} onChange={(event) => contractForm.setData('contract_number', event.target.value)} placeholder="Auto-generated if blank on create" /></Field>
                                <Field label="Status" error={contractForm.errors.status}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={contractForm.data.status} onChange={(event) => contractForm.setData('status', event.target.value)}>{contractStatuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></Field>
                                <Field label="Start date" error={contractForm.errors.start_date}><Input type="date" value={contractForm.data.start_date} onChange={(event) => contractForm.setData('start_date', event.target.value)} required /></Field>
                                <Field label="End date" error={contractForm.errors.end_date}><Input type="date" value={contractForm.data.end_date} onChange={(event) => contractForm.setData('end_date', event.target.value)} /></Field>
                                <Field label="Pay frequency" error={contractForm.errors.pay_frequency}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={contractForm.data.pay_frequency} onChange={(event) => contractForm.setData('pay_frequency', event.target.value)}>{payFrequencies.map((frequency) => <option key={frequency} value={frequency}>{frequency}</option>)}</select></Field>
                                <Field label="Salary basis" error={contractForm.errors.salary_basis}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={contractForm.data.salary_basis} onChange={(event) => contractForm.setData('salary_basis', event.target.value)}>{salaryBases.map((basis) => <option key={basis} value={basis}>{basis}</option>)}</select></Field>
                                <Field label="Base salary" error={contractForm.errors.base_salary_amount}><Input type="number" step="0.01" value={contractForm.data.base_salary_amount} onChange={(event) => contractForm.setData('base_salary_amount', event.target.value)} /></Field>
                                <Field label="Hourly rate" error={contractForm.errors.hourly_rate}><Input type="number" step="0.01" value={contractForm.data.hourly_rate} onChange={(event) => contractForm.setData('hourly_rate', event.target.value)} /></Field>
                                <Field label="Currency" error={contractForm.errors.currency_id}><select className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" value={contractForm.data.currency_id} onChange={(event) => contractForm.setData('currency_id', event.target.value)}><option value="">No currency</option>{currencies.map((currency) => <option key={currency.id} value={currency.id}>{currency.code} · {currency.name}</option>)}</select></Field>
                                <Field label="Working days/week" error={contractForm.errors.working_days_per_week}><Input type="number" min="1" max="7" value={contractForm.data.working_days_per_week} onChange={(event) => contractForm.setData('working_days_per_week', event.target.value)} /></Field>
                                <Field label="Hours/day" error={contractForm.errors.standard_hours_per_day}><Input type="number" step="0.25" value={contractForm.data.standard_hours_per_day} onChange={(event) => contractForm.setData('standard_hours_per_day', event.target.value)} /></Field>
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox checked={contractForm.data.is_payroll_eligible} onCheckedChange={(value) => contractForm.setData('is_payroll_eligible', Boolean(value))} />
                                <span className="text-sm">Payroll eligible</span>
                            </div>

                            <Field label="Notes" error={contractForm.errors.notes}>
                                <textarea className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" value={contractForm.data.notes} onChange={(event) => contractForm.setData('notes', event.target.value)} />
                            </Field>

                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={contractForm.processing}>{editingContractId ? 'Save contract' : 'Add contract'}</Button>
                                {editingContractId && (
                                    <Button type="button" variant="outline" onClick={() => openContractForm()}>
                                        Cancel edit
                                    </Button>
                                )}
                            </div>
                        </form>
                    )}
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex items-center justify-between gap-2">
                    <h2 className="text-sm font-semibold">Documents</h2>
                    <span className="text-xs text-muted-foreground">Private documents stay inside HR access rules.</span>
                </div>

                {abilities.can_manage_private && (
                    <form
                        className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            documentForm.post(`/company/hr/employees/${employee.id}/documents`);
                        }}
                    >
                        <Field label="Type" error={documentForm.errors.document_type}><Input value={documentForm.data.document_type} onChange={(event) => documentForm.setData('document_type', event.target.value)} required /></Field>
                        <Field label="Name" error={documentForm.errors.document_name}><Input value={documentForm.data.document_name} onChange={(event) => documentForm.setData('document_name', event.target.value)} required /></Field>
                        <Field label="Valid until" error={documentForm.errors.valid_until}><Input type="date" value={documentForm.data.valid_until} onChange={(event) => documentForm.setData('valid_until', event.target.value)} /></Field>
                        <Field label="File" error={documentForm.errors.file}><Input type="file" onChange={(event) => documentForm.setData('file', event.target.files?.[0] ?? null)} required /></Field>
                        <div className="grid gap-2">
                            <Label>Visibility</Label>
                            <div className="flex items-center gap-3 rounded-md border border-input px-3 py-2 text-sm">
                                <Checkbox checked={documentForm.data.is_private} onCheckedChange={(value) => documentForm.setData('is_private', Boolean(value))} />
                                <span>Private document</span>
                            </div>
                        </div>
                        <div className="xl:col-span-5">
                            <Button type="submit" disabled={documentForm.processing}>Upload document</Button>
                        </div>
                    </form>
                )}

                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">File</th>
                                <th className="px-3 py-2 font-medium">Valid until</th>
                                <th className="px-3 py-2 font-medium">Visibility</th>
                                <th className="px-3 py-2 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {employee.documents.length === 0 && (
                                <tr>
                                    <td className="px-3 py-6 text-center text-muted-foreground" colSpan={6}>No employee documents yet.</td>
                                </tr>
                            )}
                            {employee.documents.map((document) => (
                                <tr key={document.id}>
                                    <td className="px-3 py-2">{document.document_type}</td>
                                    <td className="px-3 py-2">{document.document_name}</td>
                                    <td className="px-3 py-2">{document.original_name ?? '-'}</td>
                                    <td className="px-3 py-2">{document.valid_until ?? '-'}</td>
                                    <td className="px-3 py-2">{document.is_private ? 'Private' : 'Visible to employee viewers'}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-2">
                                            <Button variant="ghost" asChild>
                                                <a href={document.download_url}>Download</a>
                                            </Button>
                                            {abilities.can_manage_private && (
                                                <Button variant="ghost" onClick={() => documentForm.delete(`/company/hr/documents/${document.id}`)}>Delete</Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-lg font-semibold">{value}</p>
        </div>
    );
}

function Detail({ label, value }: { label: string; value?: string | null }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-1">{value && value !== '' ? value : '-'}</p>
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
