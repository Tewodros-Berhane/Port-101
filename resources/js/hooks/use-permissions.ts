import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export type UsePermissionsReturn = {
    permissions: string[];
    hasPermission: (permission: string) => boolean;
};

export function usePermissions(): UsePermissionsReturn {
    const { permissions = [] } = usePage<SharedData>().props;

    const hasPermission = (permission: string) =>
        permissions.includes(permission);

    return {
        permissions,
        hasPermission,
    };
}
