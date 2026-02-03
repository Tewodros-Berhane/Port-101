import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Address = {
    id: string;
    partner_id: string;
    type: string;
    line1: string;
    line2?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country_code?: string | null;
    is_primary: boolean;
};

type PartnerOption = {
    id: string;
    name: string;
    code?: string | null;
};

type Props = {
    address: Address;
    partners: PartnerOption[];
};

export default function AddressEdit({ address, partners }: Props) {
    const form = useForm({
        partner_id: address.partner_id ?? '',
        type: address.type ?? 'billing',
        line1: address.line1 ?? '',
        line2: address.line2 ?? '',
        city: address.city ?? '',
        state: address.state ?? '',
        postal_code: address.postal_code ?? '',
        country_code: address.country_code ?? '',
        is_primary: address.is_primary ?? false,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Addresses', href: '/core/addresses' },
                {
                    title: address.line1,
                    href: `/core/addresses/${address.id}/edit`,
                },
            ]}
        >
            <Head title={address.line1} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Edit address</h1>
                    <p className="text-sm text-muted-foreground">
                        Update address details.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/core/addresses">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/core/addresses/${address.id}`);
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="partner_id">Partner</Label>
                    <select
                        id="partner_id"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.partner_id}
                        onChange={(event) =>
                            form.setData('partner_id', event.target.value)
                        }
                        required
                    >
                        <option value="">Select partner</option>
                        {partners.map((partner) => (
                            <option key={partner.id} value={partner.id}>
                                {partner.name}
                                {partner.code ? ` (${partner.code})` : ''}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.partner_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="type">Type</Label>
                    <select
                        id="type"
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        value={form.data.type}
                        onChange={(event) =>
                            form.setData('type', event.target.value)
                        }
                    >
                        <option value="billing">Billing</option>
                        <option value="shipping">Shipping</option>
                        <option value="other">Other</option>
                    </select>
                    <InputError message={form.errors.type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="line1">Address line 1</Label>
                    <Input
                        id="line1"
                        value={form.data.line1}
                        onChange={(event) =>
                            form.setData('line1', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.line1} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="line2">Address line 2</Label>
                    <Input
                        id="line2"
                        value={form.data.line2}
                        onChange={(event) =>
                            form.setData('line2', event.target.value)
                        }
                    />
                    <InputError message={form.errors.line2} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="city">City</Label>
                    <Input
                        id="city"
                        value={form.data.city}
                        onChange={(event) =>
                            form.setData('city', event.target.value)
                        }
                    />
                    <InputError message={form.errors.city} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="state">State</Label>
                    <Input
                        id="state"
                        value={form.data.state}
                        onChange={(event) =>
                            form.setData('state', event.target.value)
                        }
                    />
                    <InputError message={form.errors.state} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="postal_code">Postal code</Label>
                    <Input
                        id="postal_code"
                        value={form.data.postal_code}
                        onChange={(event) =>
                            form.setData('postal_code', event.target.value)
                        }
                    />
                    <InputError message={form.errors.postal_code} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="country_code">Country code</Label>
                    <Input
                        id="country_code"
                        value={form.data.country_code}
                        onChange={(event) =>
                            form.setData('country_code', event.target.value)
                        }
                        maxLength={2}
                    />
                    <InputError message={form.errors.country_code} />
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="is_primary"
                        checked={form.data.is_primary}
                        onCheckedChange={(value) =>
                            form.setData('is_primary', Boolean(value))
                        }
                    />
                    <Label htmlFor="is_primary">Primary address</Label>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Save changes
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() =>
                            form.delete(`/core/addresses/${address.id}`)
                        }
                        disabled={form.processing}
                    >
                        Delete
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
