import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleLinks, moduleBreadcrumbs } from '@/lib/page-navigation';

type StructureLine = { id?: string; line_type: string; calculation_type: string; code: string; name: string; amount: string; percentage_rate: string; is_active: boolean };
type Props = { mode: 'create' | 'edit'; title: string; description: string; submitUrl: string; method: 'post' | 'put'; form: { name: string; code: string; is_active: boolean; notes: string; lines: StructureLine[] } };
const emptyLine = (): StructureLine => ({ line_type: 'earning', calculation_type: 'fixed', code: '', name: '', amount: '', percentage_rate: '', is_active: true });

export default function StructureForm({ mode, title, description, submitUrl, method, form: defaults }: Props) {
    const form = useForm(defaults);
    const updateLine = (index: number, field: keyof StructureLine, value: string | boolean) => { const next = [...form.data.lines]; next[index] = { ...next[index], [field]: value }; form.setData('lines', next); };
    const submit = () => method === 'put' ? form.put(submitUrl) : form.post(submitUrl);

    return (
        <AppLayout breadcrumbs={moduleBreadcrumbs(companyModuleLinks.hr, { title: 'Payroll', href: '/company/hr/payroll' }, { title, href: submitUrl })}>
            <div className="max-w-6xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">{title}</h1><p className="text-sm text-muted-foreground">{description}</p></div><BackLinkAction href="/company/hr/payroll" label="Back to payroll" variant="outline" /></div>
                <form className="space-y-6" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                    <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
                        <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                        <Field label="Code" error={form.errors.code}><Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></Field>
                        <div className="md:col-span-2"><Field label="Notes" error={form.errors.notes}><textarea className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></Field></div>
                        <div className="flex items-center gap-3 rounded-lg border px-3 py-3 text-sm md:col-span-2"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', Boolean(checked))} /><span>Structure is active</span></div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-sm font-semibold">Salary lines</h2><p className="text-xs text-muted-foreground">Fixed or percentage-based earnings and deductions.</p></div><Button type="button" variant="outline" onClick={() => form.setData('lines', [...form.data.lines, emptyLine()])}>Add line</Button></div>
                        <div className="mt-4 overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[1120px] text-sm"><thead className="bg-muted/60 text-left"><tr><th className="px-3 py-2 font-medium">Type</th><th className="px-3 py-2 font-medium">Calc</th><th className="px-3 py-2 font-medium">Code</th><th className="px-3 py-2 font-medium">Name</th><th className="px-3 py-2 font-medium">Amount</th><th className="px-3 py-2 font-medium">Percent</th><th className="px-3 py-2 font-medium">Active</th><th className="px-3 py-2 text-right font-medium">Actions</th></tr></thead><tbody className="divide-y">{form.data.lines.map((line, index) => <tr key={line.id ?? `line-${index}`}><td className="px-3 py-2 align-top"><select className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm" value={line.line_type} onChange={(event) => updateLine(index, 'line_type', event.target.value)}><option value="earning">Earning</option><option value="deduction">Deduction</option></select><InputError message={form.errors[`lines.${index}.line_type` as keyof typeof form.errors]} /></td><td className="px-3 py-2 align-top"><select className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm" value={line.calculation_type} onChange={(event) => updateLine(index, 'calculation_type', event.target.value)}><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select><InputError message={form.errors[`lines.${index}.calculation_type` as keyof typeof form.errors]} /></td><td className="px-3 py-2 align-top"><Input value={line.code} onChange={(event) => updateLine(index, 'code', event.target.value)} /><InputError message={form.errors[`lines.${index}.code` as keyof typeof form.errors]} /></td><td className="px-3 py-2 align-top"><Input value={line.name} onChange={(event) => updateLine(index, 'name', event.target.value)} /><InputError message={form.errors[`lines.${index}.name` as keyof typeof form.errors]} /></td><td className="px-3 py-2 align-top"><Input value={line.amount} onChange={(event) => updateLine(index, 'amount', event.target.value)} placeholder="0.00" /><InputError message={form.errors[`lines.${index}.amount` as keyof typeof form.errors]} /></td><td className="px-3 py-2 align-top"><Input value={line.percentage_rate} onChange={(event) => updateLine(index, 'percentage_rate', event.target.value)} placeholder="0.00" /><InputError message={form.errors[`lines.${index}.percentage_rate` as keyof typeof form.errors]} /></td><td className="px-3 py-2 align-top"><Checkbox checked={line.is_active} onCheckedChange={(checked) => updateLine(index, 'is_active', Boolean(checked))} /></td><td className="px-3 py-2 text-right align-top"><Button type="button" variant="ghost" size="sm" disabled={form.data.lines.length === 1} onClick={() => form.setData('lines', form.data.lines.filter((_, lineIndex) => lineIndex !== index))}>Remove</Button></td></tr>)}</tbody></table>
                        </div>
                    </div>
                    <Button type="submit" disabled={form.processing}>{mode === 'create' ? 'Create structure' : 'Save structure'}</Button>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) { return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>; }
