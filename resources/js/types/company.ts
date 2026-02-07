export type Company = {
    id: string;
    name: string;
    slug: string;
    is_active?: boolean;
    role_id?: string | null;
    is_owner?: boolean;
};
