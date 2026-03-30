import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthSplitLayout from '@/layouts/auth/auth-split-layout';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const loginHighlights = [
    {
        title: 'One structured workspace',
        description:
            'Run finance, operations, projects, and people from one shared system.',
    },
    {
        title: 'Approvals and records stay aligned',
        description:
            'Keep workflows, ownership, and traceable records in one place.',
    },
];

export default function Login({ status, canResetPassword }: Props) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <AuthSplitLayout
            title="Sign in to your workspace"
            description="Use your assigned work email and password to continue."
            eyebrow="Enterprise workspace"
            panelTitle="Run finance, operations, projects, and people from one workspace."
            panelDescription="Structured workflows, approvals, and records in a single system for daily operational work."
            highlights={loginHighlights}
            securityNote="Access is controlled by company membership and assigned role. If access is incorrect, contact your company administrator."
            variant="editorial"
            showCardBrand
            cardClassName="w-full"
            contentClassName="pt-2"
        >
            <Head title="Log in" />

            <div className="space-y-5">
                {status ? (
                    <div
                        role="status"
                        aria-live="polite"
                        className="rounded-[14px] border border-[color:var(--status-success-soft)] bg-[color:var(--status-success-soft)] px-4 py-3 text-sm font-medium text-[color:var(--status-success-foreground)]"
                    >
                        {status}
                    </div>
                ) : null}

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4">
                                <div className="grid gap-2.5">
                                    <Label
                                        htmlFor="email"
                                        className="text-[13px] font-medium tracking-[-0.01em] text-foreground"
                                    >
                                        Email address
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        inputMode="email"
                                        spellCheck={false}
                                        placeholder="name@company.com"
                                        className="h-11 pr-3.5"
                                        aria-invalid={Boolean(errors.email)}
                                        aria-describedby={
                                            errors.email
                                                ? 'login-email-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="login-email-error"
                                        role="alert"
                                        aria-live="polite"
                                        message={errors.email}
                                    />
                                </div>

                                <div className="grid gap-2.5">
                                    <div className="flex items-center gap-3">
                                        <Label
                                            htmlFor="password"
                                            className="text-[13px] font-medium tracking-[-0.01em] text-foreground"
                                        >
                                            Password
                                        </Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="ml-auto text-sm text-[color:var(--text-secondary)] transition-colors hover:text-foreground"
                                                tabIndex={6}
                                            >
                                                Forgot password?
                                            </TextLink>
                                        )}
                                    </div>
                                    <div className="relative">
                                        <Input
                                            id="password"
                                            type={
                                                showPassword ? 'text' : 'password'
                                            }
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="Enter your password"
                                            className="h-11 pr-11"
                                            aria-invalid={Boolean(errors.password)}
                                            aria-describedby={
                                                errors.password
                                                    ? 'login-password-error'
                                                    : undefined
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            tabIndex={3}
                                            aria-label={
                                                showPassword
                                                    ? 'Hide password'
                                                    : 'Show password'
                                            }
                                            aria-pressed={showPassword}
                                            onClick={() =>
                                                setShowPassword((value) => !value)
                                            }
                                            className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-full text-[color:var(--text-secondary)] hover:bg-[color:var(--bg-surface-muted)] hover:text-foreground"
                                        >
                                            {showPassword ? (
                                                <EyeOff className="size-4" />
                                            ) : (
                                                <Eye className="size-4" />
                                            )}
                                        </Button>
                                    </div>
                                    <InputError
                                        id="login-password-error"
                                        role="alert"
                                        aria-live="polite"
                                        message={errors.password}
                                    />
                                </div>

                                <div className="flex items-center justify-between gap-4 rounded-[14px] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-4 py-3">
                                    <div className="flex items-center space-x-3">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={4}
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="text-sm font-medium text-foreground"
                                        >
                                            Remember me
                                        </Label>
                                    </div>
                                    <div className="hidden text-xs text-[color:var(--text-secondary)] sm:block">
                                        Trusted device only
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-1 h-11 w-full rounded-[14px] text-sm font-semibold"
                                    tabIndex={5}
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing ? (
                                        <>
                                            <Spinner />
                                            Signing in...
                                        </>
                                    ) : (
                                        'Sign in'
                                    )}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="flex items-start gap-3 rounded-[14px] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-4 py-3 text-sm text-[color:var(--text-secondary)]">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0 text-primary" />
                    <p>
                        Use the credentials assigned to your company account.
                        Your access, modules, and actions are controlled by role
                        and company membership.
                    </p>
                </div>
            </div>
        </AuthSplitLayout>
    );
}
