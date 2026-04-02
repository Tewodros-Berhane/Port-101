import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PublicReveal from '@/components/public/public-reveal';
import { PublicEyebrow } from '@/components/public/public-shell';
import { FormErrorSummary } from '@/components/shell/form-error-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import PublicLayout from '@/layouts/public/public-layout';
import { publicFooterGroups, publicNavLinks } from '@/lib/public-site';
import { login } from '@/routes';
import { dashboard as companyDashboard } from '@/routes/company';
import { dashboard as platformDashboard } from '@/routes/platform';
import type { SharedData } from '@/types';

type Option = {
    value: string;
    label: string;
};

type Props = {
    requestType: 'demo' | 'sales';
    sourcePage: string;
    hero: {
        eyebrow: string;
        title: string;
        description: string;
        highlights: string[];
    };
    teamSizeOptions: Option[];
    moduleOptions: Option[];
};

const NATIVE_SELECT_CLASS =
    'h-10 w-full rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30';

function FieldBlock({
    htmlFor,
    label,
    optional = false,
    error,
    children,
}: {
    htmlFor?: string;
    label: string;
    optional?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>
                {label}
                {optional ? (
                    <span className="ml-1 text-[color:var(--text-muted)]">
                        Optional
                    </span>
                ) : null}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

export default function PublicContactRequestPage({
    requestType,
    sourcePage,
    hero,
    teamSizeOptions,
    moduleOptions,
}: Props) {
    const { auth, flash: sharedFlash } = usePage<SharedData>().props;
    const isAuthenticated = Boolean(auth.user);
    const isSuperAdmin = Boolean(auth.user?.is_super_admin);
    const flash = sharedFlash ?? {};
    const dashboardHref = isSuperAdmin
        ? platformDashboard()
        : companyDashboard();

    const form = useForm({
        request_type: requestType,
        full_name: auth.user?.name ?? '',
        work_email: auth.user?.email ?? '',
        company_name: '',
        role_title: '',
        team_size: '',
        preferred_demo_date: '',
        modules_interest: [] as string[],
        message: '',
        phone: '',
        country: '',
        source_page: sourcePage,
        website: '',
    });

    const toggleModule = (value: string, checked: boolean | 'indeterminate') => {
        const shouldAdd = checked === true;
        const current = form.data.modules_interest;

        form.setData(
            'modules_interest',
            shouldAdd
                ? [...current, value]
                : current.filter((item) => item !== value),
        );
    };

    return (
        <>
            <Head
                title={
                    requestType === 'demo' ? 'Book Demo' : 'Contact Sales'
                }
            />
            <PublicLayout
                navLinks={publicNavLinks}
                footerGroups={publicFooterGroups}
                isAuthenticated={isAuthenticated}
                dashboardHref={dashboardHref}
            >
                <section className="border-b border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]">
                    <div className="mx-auto w-full max-w-7xl px-6 py-16 lg:px-8 lg:py-20">
                        <div className="grid gap-10 lg:grid-cols-[0.76fr_minmax(0,1fr)] lg:gap-14">
                            <PublicReveal className="space-y-6" y={18}>
                                <PublicEyebrow>{hero.eyebrow}</PublicEyebrow>
                                <div className="space-y-4">
                                    <h1 className="max-w-3xl text-balance text-4xl font-semibold tracking-[-0.04em] text-foreground sm:text-5xl lg:text-[3.85rem] lg:leading-[0.98]">
                                        {hero.title}
                                    </h1>
                                    <p className="max-w-2xl text-[15px] leading-7 text-[color:var(--text-secondary)] sm:text-lg">
                                        {hero.description}
                                    </p>
                                </div>
                                <div className="space-y-4 border-l border-[color:var(--border-subtle)] pl-5">
                                    {hero.highlights.map((item, index) => (
                                        <div key={item} className="space-y-1">
                                            <p className="text-sm font-semibold text-foreground">
                                                0{index + 1}
                                            </p>
                                            <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                                {item}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                                <div className="flex flex-wrap items-center gap-3 text-sm text-[color:var(--text-secondary)]">
                                    <Badge variant="secondary">Public request</Badge>
                                    <span>Stored for internal review</span>
                                    <span aria-hidden="true">|</span>
                                    {isAuthenticated ? (
                                        <Link
                                            href={dashboardHref}
                                            className="font-medium text-foreground underline decoration-[color:var(--border-strong)] underline-offset-4 transition-colors hover:text-primary"
                                        >
                                            Open dashboard
                                        </Link>
                                    ) : (
                                        <Link
                                            href={login()}
                                            className="font-medium text-foreground underline decoration-[color:var(--border-strong)] underline-offset-4 transition-colors hover:text-primary"
                                        >
                                            Sign in with an existing account
                                        </Link>
                                    )}
                                </div>
                            </PublicReveal>

                            <PublicReveal y={22} delay={0.06}>
                                <Card className="rounded-[var(--radius-hero)] py-0 shadow-[var(--shadow-md)]">
                                    <CardHeader className="border-b border-[color:var(--border-subtle)] px-6 py-5">
                                        <CardTitle className="text-xl tracking-[-0.02em]">
                                            {requestType === 'demo'
                                                ? 'Request a guided demo'
                                                : 'Send a sales request'}
                                        </CardTitle>
                                        <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                            The team will review this request and follow up through the contact details you provide here.
                                        </p>
                                    </CardHeader>
                                    <CardContent className="px-6 py-6">
                                        {flash.success ? (
                                            <div className="space-y-5">
                                                <div className="rounded-[var(--radius-panel)] border border-[color:var(--status-success)]/20 bg-[color:var(--status-success-soft)] px-5 py-4">
                                                    <p className="text-sm font-medium text-[color:var(--status-success-foreground)]">
                                                        {flash.success}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap gap-3">
                                                    <Button asChild>
                                                        <Link href="/">Back to homepage</Link>
                                                    </Button>
                                                    {isAuthenticated ? (
                                                        <Button asChild variant="outline">
                                                            <Link href={dashboardHref}>
                                                                Open dashboard
                                                            </Link>
                                                        </Button>
                                                    ) : (
                                                        <Button asChild variant="outline">
                                                            <Link href={login()}>
                                                                Sign in
                                                            </Link>
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ) : (
                                            <form
                                                className="space-y-6"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    form.post('/contact-requests');
                                                }}
                                            >
                                                <FormErrorSummary errors={form.errors} />

                                                {flash.warning ? (
                                                    <div className="rounded-[var(--radius-panel)] border border-[color:var(--status-warning)]/22 bg-[color:var(--status-warning-soft)] px-4 py-3 text-sm text-[color:var(--status-warning-foreground)]">
                                                        {flash.warning}
                                                    </div>
                                                ) : null}

                                                {flash.error ? (
                                                    <div className="rounded-[var(--radius-panel)] border border-[color:var(--status-danger)]/18 bg-[color:var(--status-danger-soft)] px-4 py-3 text-sm text-[color:var(--status-danger-foreground)]">
                                                        {flash.error}
                                                    </div>
                                                ) : null}

                                                <input
                                                    type="hidden"
                                                    name="request_type"
                                                    value={form.data.request_type}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="source_page"
                                                    value={form.data.source_page}
                                                />

                                                <div
                                                    aria-hidden="true"
                                                    className="absolute -left-[9999px] top-auto h-px w-px overflow-hidden"
                                                >
                                                    <Label htmlFor="website">
                                                        Website
                                                    </Label>
                                                    <Input
                                                        id="website"
                                                        tabIndex={-1}
                                                        autoComplete="off"
                                                        value={form.data.website}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'website',
                                                                event.target.value,
                                                            )
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <FieldBlock
                                                        htmlFor="full_name"
                                                        label="Full name"
                                                        error={form.errors.full_name}
                                                    >
                                                        <Input
                                                            id="full_name"
                                                            value={form.data.full_name}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'full_name',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            required
                                                            autoComplete="name"
                                                        />
                                                    </FieldBlock>

                                                    <FieldBlock
                                                        htmlFor="work_email"
                                                        label="Work email"
                                                        error={form.errors.work_email}
                                                    >
                                                        <Input
                                                            id="work_email"
                                                            type="email"
                                                            value={form.data.work_email}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'work_email',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            required
                                                            autoComplete="email"
                                                        />
                                                    </FieldBlock>

                                                    <FieldBlock
                                                        htmlFor="company_name"
                                                        label="Company name"
                                                        error={form.errors.company_name}
                                                    >
                                                        <Input
                                                            id="company_name"
                                                            value={form.data.company_name}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'company_name',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            required
                                                            autoComplete="organization"
                                                        />
                                                    </FieldBlock>

                                                    <FieldBlock
                                                        htmlFor="role_title"
                                                        label="Role title"
                                                        error={form.errors.role_title}
                                                    >
                                                        <Input
                                                            id="role_title"
                                                            value={form.data.role_title}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'role_title',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            required
                                                            autoComplete="organization-title"
                                                        />
                                                    </FieldBlock>

                                                    <FieldBlock
                                                        htmlFor="team_size"
                                                        label="Team size"
                                                        error={form.errors.team_size}
                                                    >
                                                        <select
                                                            id="team_size"
                                                            className={NATIVE_SELECT_CLASS}
                                                            value={form.data.team_size}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'team_size',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            required
                                                        >
                                                            <option value="">
                                                                Select a team size
                                                            </option>
                                                            {teamSizeOptions.map(
                                                                (option) => (
                                                                    <option
                                                                        key={option.value}
                                                                        value={option.value}
                                                                    >
                                                                        {option.label}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </FieldBlock>

                                                    {requestType === 'demo' ? (
                                                        <FieldBlock
                                                            htmlFor="preferred_demo_date"
                                                            label="Preferred demo date"
                                                            error={
                                                                form.errors
                                                                    .preferred_demo_date
                                                            }
                                                        >
                                                            <Input
                                                                id="preferred_demo_date"
                                                                type="date"
                                                                value={
                                                                    form.data
                                                                        .preferred_demo_date
                                                                }
                                                                onChange={(event) =>
                                                                    form.setData(
                                                                        'preferred_demo_date',
                                                                        event.target.value,
                                                                    )
                                                                }
                                                                required
                                                            />
                                                        </FieldBlock>
                                                    ) : null}

                                                    <FieldBlock
                                                        htmlFor="phone"
                                                        label="Phone"
                                                        optional
                                                        error={form.errors.phone}
                                                    >
                                                        <Input
                                                            id="phone"
                                                            value={form.data.phone}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'phone',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            autoComplete="tel"
                                                        />
                                                    </FieldBlock>

                                                    <div className="grid gap-2 md:col-span-2">
                                                        <Label>
                                                            Modules of interest
                                                        </Label>
                                                        <div className="grid gap-3 sm:grid-cols-2">
                                                            {moduleOptions.map(
                                                                (option) => (
                                                                    <label
                                                                        key={option.value}
                                                                        className="flex items-start gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-4 py-3 text-sm transition-colors hover:border-[color:var(--border-strong)]"
                                                                    >
                                                                        <Checkbox
                                                                            checked={form.data.modules_interest.includes(
                                                                                option.value,
                                                                            )}
                                                                            onCheckedChange={(
                                                                                checked,
                                                                            ) =>
                                                                                toggleModule(
                                                                                    option.value,
                                                                                    checked,
                                                                                )
                                                                            }
                                                                        />
                                                                        <span className="leading-6 text-foreground">
                                                                            {option.label}
                                                                        </span>
                                                                    </label>
                                                                ),
                                                            )}
                                                        </div>
                                                        <InputError
                                                            message={
                                                                form.errors.modules_interest
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="grid gap-2 md:col-span-2">
                                                        <Label htmlFor="message">
                                                            What should the team understand before they follow up?
                                                        </Label>
                                                        <Textarea
                                                            id="message"
                                                            value={form.data.message}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'message',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            placeholder={
                                                                requestType ===
                                                                'demo'
                                                                    ? 'Describe the workflows, modules, or control questions you want to see in the demo.'
                                                                    : 'Describe the business context, deployment scope, or commercial questions you want covered.'
                                                            }
                                                        />
                                                        <InputError
                                                            message={form.errors.message}
                                                        />
                                                    </div>

                                                    <FieldBlock
                                                        htmlFor="country"
                                                        label="Country"
                                                        optional
                                                        error={form.errors.country}
                                                    >
                                                        <Input
                                                            id="country"
                                                            value={form.data.country}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'country',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            autoComplete="country-name"
                                                        />
                                                    </FieldBlock>
                                                </div>

                                                <div className="flex flex-col gap-3 border-t border-[color:var(--border-subtle)] pt-5 sm:flex-row sm:items-center sm:justify-between">
                                                    <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                                        This request creates an internal review item for the platform team.
                                                        {requestType === 'demo'
                                                            ? ' A requested date helps the team confirm the walkthrough faster.'
                                                            : ''}
                                                    </p>
                                                    <Button
                                                        type="submit"
                                                        disabled={form.processing}
                                                        size="lg"
                                                    >
                                                        {form.processing
                                                            ? 'Submitting...'
                                                            : requestType ===
                                                                'demo'
                                                              ? 'Book demo'
                                                              : 'Contact sales'}
                                                    </Button>
                                                </div>
                                            </form>
                                        )}
                                    </CardContent>
                                </Card>
                            </PublicReveal>
                        </div>
                    </div>
                </section>
            </PublicLayout>
        </>
    );
}
