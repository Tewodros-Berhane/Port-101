export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header
            className={variant === 'small' ? 'space-y-1' : 'mb-8 space-y-1'}
        >
            <h2
                className={
                    variant === 'small'
                        ? 'text-sm font-semibold tracking-[-0.01em] text-foreground'
                        : 'text-2xl font-semibold tracking-[-0.02em] text-foreground md:text-[28px] md:leading-8'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="max-w-3xl text-sm leading-6 text-[color:var(--text-secondary)]">
                    {description}
                </p>
            )}
        </header>
    );
}
