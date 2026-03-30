import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    avatarClassName,
    textClassName,
    nameClassName,
    emailClassName,
}: {
    user: User;
    showEmail?: boolean;
    avatarClassName?: string;
    textClassName?: string;
    nameClassName?: string;
    emailClassName?: string;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar
                className={cn(
                    'h-8 w-8 overflow-hidden rounded-full border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]',
                    avatarClassName,
                )}
            >
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-full bg-[color:var(--action-primary-soft)] text-[color:var(--action-primary-soft-foreground)]">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div
                className={cn(
                    'grid flex-1 text-left text-sm leading-tight',
                    textClassName,
                )}
            >
                <span
                    className={cn(
                        'truncate font-medium text-foreground',
                        nameClassName,
                    )}
                >
                    {user.name}
                </span>
                {showEmail && (
                    <span
                        className={cn(
                            'truncate text-xs text-muted-foreground',
                            emailClassName,
                        )}
                    >
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
