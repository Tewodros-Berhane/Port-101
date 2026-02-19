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
    };
    flash?: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
    };
    sidebarOpen: boolean;
    [key: string]: unknown;
};
