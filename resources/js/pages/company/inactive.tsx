import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { logout } from '@/routes';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Building2 } from 'lucide-react';

export default function CompanyInactive() {
    const { auth, companies } = usePage<SharedData>().props;
    const inactiveCompanies = companies.filter((company) => !company.is_active);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company Access', href: '/company/inactive' },
            ]}
        >
            <Head title="Company Access Inactive" />

            <div className="mx-auto max-w-3xl">
                <div className="rounded-xl border border-amber-300/70 bg-amber-50/60 p-6 dark:border-amber-700/60 dark:bg-amber-950/20">
                    <div className="flex items-start gap-3">
                        <AlertTriangle className="mt-0.5 size-5 text-amber-700 dark:text-amber-400" />
                        <div>
                            <h1 className="text-xl font-semibold">
                                Company access is inactive
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Hi {auth.user.name}, your account currently has
                                no active company workspace. A platform admin
                                needs to reactivate a company before you can
                                continue.
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 rounded-lg border bg-background p-4">
                        <p className="text-sm font-medium">
                            Assigned inactive companies
                        </p>
                        <div className="mt-3 space-y-2">
                            {inactiveCompanies.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No company memberships were found.
                                </p>
                            )}
                            {inactiveCompanies.map((company) => (
                                <div
                                    key={company.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Building2 className="size-4 text-muted-foreground" />
                                    <span>{company.name}</span>
                                    <span className="text-muted-foreground">
                                        (Inactive)
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="mt-5 flex flex-wrap items-center gap-3">
                        <Button asChild variant="outline">
                            <Link href={logout()} as="button">
                                Sign out
                            </Link>
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            If you think this is unexpected, contact your
                            platform administrator.
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
