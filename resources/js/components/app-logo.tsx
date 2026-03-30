import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-primary text-primary-foreground shadow-[var(--shadow-xs)]">
                <AppLogoIcon className="size-5 text-primary-foreground" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold tracking-[-0.01em] text-foreground">
                    Port-101
                </span>
            </div>
        </>
    );
}
