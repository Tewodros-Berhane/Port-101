import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

export function NavUser({ variant = 'sidebar' }: { variant?: 'sidebar' | 'header' }) {
    const { auth } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const isCollapsed = state === 'collapsed';

    if (variant === 'header') {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-10 min-w-0 gap-2 rounded-[var(--radius-control)] border-[color:var(--border-default)] bg-card/80 px-2.5 shadow-[var(--shadow-xs)]"
                    >
                        <UserInfo
                            user={auth.user}
                            textClassName="hidden min-w-0 sm:grid"
                        />
                        <ChevronsUpDown className="hidden size-4 text-[color:var(--text-muted)] sm:block" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    className="min-w-64 rounded-[var(--radius-panel)]"
                    align="end"
                    side="bottom"
                >
                    <UserMenuContent user={auth.user} />
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className={cn(
                                'group h-12 rounded-[var(--radius-panel)] border border-transparent px-3 data-[state=open]:bg-sidebar-accent',
                                'data-[state=open]:text-sidebar-accent-foreground data-[state=open]:shadow-none',
                                isCollapsed &&
                                    'size-12 justify-center rounded-[var(--radius-hero)] px-0',
                            )}
                            data-test="sidebar-menu-button"
                        >
                            <UserInfo
                                user={auth.user}
                                textClassName={cn(
                                    'text-sidebar-accent-foreground',
                                    isCollapsed && 'hidden',
                                )}
                            />
                            {!isCollapsed && (
                                <ChevronsUpDown className="ml-auto size-4 text-sidebar-foreground/55" />
                            )}
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-60 rounded-[var(--radius-panel)]"
                        align="end"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'bottom'
                        }
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
