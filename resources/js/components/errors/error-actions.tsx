import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';

type ButtonVariant = ComponentProps<typeof Button>['variant'];

export type ErrorAction = {
    kind: 'link' | 'back' | 'reload';
    label: string;
    href?: string | null;
    fallback_href?: string | null;
    variant?: ButtonVariant | null;
};

export default function ErrorActions({ actions }: { actions: ErrorAction[] }) {
    const handleAction = (action: ErrorAction) => {
        if (typeof window === 'undefined') {
            return;
        }

        if (action.kind === 'reload') {
            window.location.reload();

            return;
        }

        if (action.kind === 'back') {
            if (window.history.length > 1) {
                window.history.back();

                return;
            }

            if (action.fallback_href) {
                window.location.assign(action.fallback_href);
            }
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-3">
            {actions.map((action) => {
                const variant = action.variant ?? 'default';

                if (action.kind === 'link' && action.href) {
                    return (
                        <Button key={`${action.kind}-${action.label}-${action.href}`} asChild variant={variant}>
                            <Link href={action.href}>{action.label}</Link>
                        </Button>
                    );
                }

                return (
                    <Button
                        key={`${action.kind}-${action.label}`}
                        type="button"
                        variant={variant}
                        onClick={() => handleAction(action)}
                    >
                        {action.label}
                    </Button>
                );
            })}
        </div>
    );
}
