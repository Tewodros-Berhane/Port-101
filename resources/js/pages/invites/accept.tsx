import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    canAccept: boolean;
    message?: string;
    token?: string;
    invite?: {
        email: string;
        name?: string | null;
        role: string;
        company?: string | null;
    };
};

const formatRole = (role?: string) =>
    (role ?? '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

export default function InviteAccept({
    canAccept,
    message,
    token,
    invite,
}: Props) {
    const form = useForm({
        name: invite?.name ?? '',
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthLayout
            title="Accept your invite"
            description="Complete your account setup to access Port-101"
        >
            <Head title="Accept Invite" />

            {!canAccept && (
                <div className="space-y-4 rounded-xl border p-4 text-sm">
                    <p className="text-muted-foreground">{message}</p>
                    <Button asChild>
                        <Link href="/login">Back to login</Link>
                    </Button>
                </div>
            )}

            {canAccept && token && invite && (
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/invites/${token}/accept`);
                    }}
                >
                    <div className="rounded-xl border p-4 text-sm text-muted-foreground">
                        <p>
                            Invited as{' '}
                            <span className="font-medium text-foreground">
                                {formatRole(invite.role)}
                            </span>
                            {invite.company ? ` for ${invite.company}` : ''}.
                        </p>
                        <p className="mt-1">Email: {invite.email}</p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Full name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirm password
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            required
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="w-full"
                    >
                        Accept invite
                    </Button>
                </form>
            )}
        </AuthLayout>
    );
}
