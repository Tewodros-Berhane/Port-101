import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';

const defaultHighlights = [
    {
        title: 'One structured workspace',
        description:
            'Run finance, operations, projects, and people from one shared system.',
    },
    {
        title: 'Approvals and records stay aligned',
        description:
            'Keep workflows, ownership, and traceable records in one place.',
    },
];

export default function AuthLayout({
    children,
    title,
    description,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            eyebrow="Enterprise workspace"
            panelTitle="Run finance, operations, projects, and people from one workspace."
            panelDescription="Structured workflows, approvals, and records in a single system for daily operational work."
            highlights={defaultHighlights}
            securityNote="Access is controlled by company membership and assigned role. If access is incorrect, contact your company administrator."
            variant="editorial"
            showCardBrand
            cardClassName="w-full"
            contentClassName="pt-2"
            {...props}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
