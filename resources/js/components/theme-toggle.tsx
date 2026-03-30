import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function ThemeToggle({
    className,
    compact = false,
}: {
    className?: string;
    compact?: boolean;
}) {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const nextAppearance = resolvedAppearance === 'dark' ? 'light' : 'dark';
    const isDark = resolvedAppearance === 'dark';

    return (
        <Button
            type="button"
            variant="outline"
            size={compact ? 'icon' : 'sm'}
            className={cn(
                'rounded-full border-[color:var(--border-subtle)] bg-background/80 text-[color:var(--text-secondary)] shadow-none hover:bg-[color:var(--bg-surface-muted)] hover:text-foreground',
                !compact && 'px-3.5',
                className,
            )}
            aria-label={`Switch to ${nextAppearance} mode`}
            title={`Switch to ${nextAppearance} mode`}
            onClick={() => updateAppearance(nextAppearance)}
        >
            {isDark ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </Button>
    );
}
