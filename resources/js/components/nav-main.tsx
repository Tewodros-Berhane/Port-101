import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label = 'Platform',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { hasPermission } = usePermissions();
    const visibleItems = items.filter(
        (item) => !item.permission || hasPermission(item.permission),
    );

    if (visibleItems.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="mt-1 first:mt-0">
            <SidebarGroupLabel>
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-1">
                {visibleItems.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            size="default"
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span className="group-data-[collapsible=icon]:hidden">
                                    {item.title}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                        {item.badge !== undefined &&
                            item.badge !== null &&
                            item.badge !== '' && (
                                <SidebarMenuBadge>
                                    {item.badge}
                                </SidebarMenuBadge>
                            )}
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
