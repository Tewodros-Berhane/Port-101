import ThemeToggle from '@/components/theme-toggle';

export default function PublicThemeToggle({
    className,
    compact = false,
}: {
    className?: string;
    compact?: boolean;
}) {
    return <ThemeToggle className={className} compact={compact} />;
}
