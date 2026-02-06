import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

type Props = {
    module: string;
};

export default function CompanyModulePlaceholder({ module }: Props) {
    const slug = module.toLowerCase();

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: module, href: `/company/${slug}` },
            ]}
        >
            <Head title={module} />

            <div className="rounded-xl border p-6">
                <h1 className="text-xl font-semibold">{module}</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    This module page is ready as a placeholder and will be
                    implemented in the module delivery phase.
                </p>
            </div>
        </AppLayout>
    );
}
