export type * from './auth';
export type * from './company';
export type * from './navigation';
export type * from './ui';

import type { Auth } from './auth';
import type { Company } from './company';

export type SharedData = {
    name: string;
    auth: Auth;
    company: Company | null;
    companies: Company[];
    permissions: string[];
    notifications?: {
        unread_count: number;
        recent: Array<{
            id: string;
            title: string;
            message: string;
            url?: string | null;
            severity: string;
            read_at?: string | null;
            created_at?: string | null;
        }>;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
    };
    sidebarOpen: boolean;
    [key: string]: unknown;
};
