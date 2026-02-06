import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Currency = {
    id: string;
    code: string;
    name: string;
    symbol?: string | null;
    decimal_places: number;
    is_active: boolean;
};

type Props = {
    currencies: {
        data: Currency[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function CurrenciesIndex({ currencies }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.currencies.view');
    const canManage = hasPermission('core.currencies.manage');

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Currencies', href: '/core/currencies' },
            ]}
        >
            <Head title="Currencies" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Currencies</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage currencies and formatting rules.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href="/core/currencies/create">New currency</Link>
                    </Button>
                )}
            </div>
            {canView ? (
                <>
                    <div className="mt-6 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Code
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Symbol
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Decimals
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    {canManage && (
                                        <th className="px-4 py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {currencies.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 6 : 5}
                                        >
                                            No currencies yet.
                                        </td>
                                    </tr>
                                )}
                                {currencies.data.map((currency) => (
                                    <tr key={currency.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {currency.code}
                                        </td>
                                        <td className="px-4 py-3">
                                            {currency.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {currency.symbol ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {currency.decimal_places}
                                        </td>
                                        <td className="px-4 py-3">
                                            {currency.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/currencies/${currency.id}/edit`}
                                                    className="text-sm font-medium text-primary"
                                                >
                                                    Edit
                                                </Link>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {currencies.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {currencies.links.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.url ?? '#'}
                                    className={`rounded-md border px-3 py-1 text-sm ${
                                        link.active
                                            ? 'border-primary text-primary'
                                            : 'text-muted-foreground'
                                    } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div className="mt-6 rounded-xl border p-6 text-sm text-muted-foreground">
                    You do not have access to view currencies.
                </div>
            )}
        </AppLayout>
    );
}
