import { Head, usePage } from '@inertiajs/react';
import {
    BarChart3,
    ClipboardCheck,
    FileSpreadsheet,
    FolderKanban,
    Handshake,
    PlugZap,
    ShieldCheck,
    ShoppingCart,
    UserRoundCheck,
    Warehouse,
} from 'lucide-react';
import FaqSection, { type FaqItem } from '@/components/public/faq-section';
import FinalCtaSection from '@/components/public/final-cta-section';
import HeroSection from '@/components/public/hero-section';
import ModuleGridSection, { type ModuleCard } from '@/components/public/module-grid-section';
import ProductPreviewSection, {
    type ProductPreviewTab,
} from '@/components/public/product-preview-section';
import ProofSection, { type ProofItem } from '@/components/public/proof-section';
import RoleUseCaseSection, {
    type RoleUseCase,
} from '@/components/public/role-use-case-section';
import SalesPathSection, {
    type SalesPathProfile,
} from '@/components/public/sales-path-section';
import SecuritySection, {
    type SecurityItem,
} from '@/components/public/security-section';
import TrustStrip from '@/components/public/trust-strip';
import WorkflowSection, {
    type WorkflowItem,
} from '@/components/public/workflow-section';
import PublicLayout from '@/layouts/public/public-layout';
import { publicFooterGroups, publicNavLinks } from '@/lib/public-site';
import { dashboard as companyDashboard } from '@/routes/company';
import { dashboard as platformDashboard } from '@/routes/platform';
import type { SharedData } from '@/types';

const trustChips = [
    'Role-based access',
    'Audit-ready workflows',
    'Approvals and queues',
    'Real-time reporting',
    'Secure integrations',
    'Modular rollout',
];

const heroPreviewCards = [
    {
        title: 'Commercial flow',
        helper: 'Leads, quotes, orders, purchasing, stock, and billing stay connected.',
    },
    {
        title: 'Controlled finance',
        helper: 'Invoices, payments, statements, and journals stay visible in the same system.',
    },
    {
        title: 'Project and people work',
        helper: 'Projects, timesheets, HR, reimbursements, and payroll stay tied to execution.',
    },
];

const heroPreviewQueue = [
    {
        title: 'Quote approval ready for review',
        meta: 'Sales to approvals to order handoff',
        status: 'pending',
    },
    {
        title: 'Vendor receipt matched to stock move',
        meta: 'Purchasing and inventory records aligned',
        status: 'approved',
    },
    {
        title: 'Reimbursement claim handed to finance',
        meta: 'HR request visible in shared workflow queues',
        status: 'in_progress',
    },
    {
        title: 'Webhook delivery retained in audit trail',
        meta: 'Integration event traceable from endpoint to response',
        status: 'delivered',
    },
];

const productPreviewTabs: ProductPreviewTab[] = [
    {
        id: 'operations',
        label: 'Operations',
        title: 'Keep sales, purchasing, stock, and delivery in one operational chain.',
        description:
            'The operations workspace keeps commercial records, stock movement, and procurement activity connected across teams.',
        bullets: [
            'Move from lead to quote to order without losing operational context.',
            'Track warehouse activity, stock moves, lots, reordering, and counts in the same system.',
            'Keep procurement and receiving activity tied to downstream execution.',
        ],
        frameTitle: 'Operations workspace',
        frameContext: 'Commercial and inventory work viewed in one surface.',
        frameCards: [
            { title: 'Sales', helper: 'Leads, quotes, orders, customer commitments' },
            { title: 'Inventory', helper: 'Stock positions, moves, lots, cycle counts' },
            { title: 'Purchasing', helper: 'RFQs, purchase orders, receiving flow' },
            { title: 'Reordering', helper: 'Suggestions, rules, coverage checks' },
        ],
        frameMetrics: [
            {
                label: 'Approval flow',
                value: 'Ready to review',
                description: 'Commercial records stay visible before downstream execution.',
                tone: 'warning',
            },
            {
                label: 'Inventory traceability',
                value: 'Linked movement',
                description: 'Receipts, counts, and stock moves share the same context.',
                tone: 'success',
            },
            {
                label: 'Replenishment',
                value: 'Coverage visible',
                description: 'Rules and suggestions stay close to warehouse work.',
                tone: 'info',
            },
        ],
        frameRows: [
            {
                record: 'Quote approval',
                detail: 'Commercial review before order confirmation',
                owner: 'Sales',
                status: 'pending',
            },
            {
                record: 'Inbound stock receipt',
                detail: 'Warehouse receipt linked to purchasing record',
                owner: 'Warehouse',
                status: 'approved',
            },
            {
                record: 'Cycle count adjustment',
                detail: 'Count result visible before inventory correction',
                owner: 'Inventory',
                status: 'queued',
            },
        ],
    },
    {
        id: 'finance',
        label: 'Finance',
        title: 'Execute billing, payments, statements, and reporting without spreadsheet handoffs.',
        description:
            'Finance users can work from invoices to payments, manual journals, reconciliation, statements, and reporting inside the same application.',
        bullets: [
            'Manage invoices, payments, journals, and ledger views in one place.',
            'Keep approvals and posting states visible before records move forward.',
            'Use reporting and statement workspaces without rebuilding context elsewhere.',
        ],
        frameTitle: 'Finance workspace',
        frameContext: 'Accounting execution tied to operational source records.',
        frameCards: [
            { title: 'Invoices', helper: 'Sales and vendor billing execution' },
            { title: 'Payments', helper: 'Incoming and outgoing payment control' },
            { title: 'Statements', helper: 'Statement review and reporting workspaces' },
            { title: 'Ledger', helper: 'Reference and review without manual rollups' },
        ],
        frameMetrics: [
            {
                label: 'Document control',
                value: 'Posting visible',
                description: 'Finance states stay explicit before records move forward.',
                tone: 'warning',
            },
            {
                label: 'Cash activity',
                value: 'Payment aware',
                description: 'Invoices and payments remain tied to finance execution.',
                tone: 'success',
            },
            {
                label: 'Reporting',
                value: 'Statement ready',
                description: 'Review views stay inside the same accounting surface.',
                tone: 'info',
            },
        ],
        frameRows: [
            {
                record: 'Customer invoice draft',
                detail: 'Prepared from commercial activity',
                owner: 'Finance',
                status: 'draft',
            },
            {
                record: 'Manual journal review',
                detail: 'Posting state visible before finalization',
                owner: 'Finance',
                status: 'pending',
            },
            {
                record: 'Payment posted',
                detail: 'Finance record completed and reportable',
                owner: 'Treasury',
                status: 'posted',
            },
        ],
    },
    {
        id: 'projects',
        label: 'Projects',
        title: 'Run project delivery, billables, and recurring work from one workspace.',
        description:
            'Projects, task work, timesheets, milestones, billables, and recurring billing stay connected to execution and finance.',
        bullets: [
            'Track delivery progress and billable work without separate spreadsheets.',
            'Review recurring billing schedules and invoice handoff in one workflow.',
            'Keep project profitability and pending approvals visible to managers.',
        ],
        frameTitle: 'Project delivery workspace',
        frameContext: 'Execution, billing readiness, and recurring work aligned.',
        frameCards: [
            { title: 'Projects', helper: 'Project records and delivery context' },
            { title: 'Tasks and time', helper: 'Operational work capture and review' },
            { title: 'Billables', helper: 'Approval-aware billing pipeline' },
            { title: 'Recurring billing', helper: 'Scheduled invoice generation paths' },
        ],
        frameMetrics: [
            {
                label: 'Delivery visibility',
                value: 'Billable ready',
                description: 'Project work can move cleanly into billing review.',
                tone: 'success',
            },
            {
                label: 'Manager review',
                value: 'Pending checks',
                description: 'Task, time, and milestone review stay close to execution.',
                tone: 'warning',
            },
            {
                label: 'Recurring cycles',
                value: 'Scheduled',
                description: 'Invoice generation paths stay visible before release.',
                tone: 'info',
            },
        ],
        frameRows: [
            {
                record: 'Billable work ready',
                detail: 'Project work staged for finance handoff',
                owner: 'Projects',
                status: 'approved',
            },
            {
                record: 'Timesheet review',
                detail: 'Project manager approval before billing',
                owner: 'Project lead',
                status: 'pending',
            },
            {
                record: 'Recurring cycle prepared',
                detail: 'Schedule ready to create invoice draft',
                owner: 'Billing ops',
                status: 'queued',
            },
        ],
    },
    {
        id: 'hr',
        label: 'HR',
        title: 'Manage employees, leave, attendance, reimbursements, and payroll in the same platform.',
        description:
            'People operations are part of the ERP surface, with employee access, attendance, reimbursement, and payroll workflows tied to approvals and reporting.',
        bullets: [
            'Create employees with or without system access and govern role-based entry.',
            'Run leave, attendance correction, and reimbursement workflows with approval states.',
            'Keep payroll structures, runs, and payslips within the broader company workspace.',
        ],
        frameTitle: 'People operations workspace',
        frameContext: 'HR data and workflows aligned with company access and approvals.',
        frameCards: [
            { title: 'Employees', helper: 'Records, contracts, documents, system access' },
            { title: 'Leave and attendance', helper: 'Requests, shifts, corrections, balances' },
            { title: 'Reimbursements', helper: 'Claims, review, finance handoff' },
            { title: 'Payroll', helper: 'Assignments, runs, payslips, posting flow' },
        ],
        frameMetrics: [
            {
                label: 'Access governance',
                value: 'HR owned',
                description: 'Employee access stays tied to the employee record.',
                tone: 'info',
            },
            {
                label: 'Request flow',
                value: 'Approval aware',
                description: 'Leave, attendance, and reimbursement states stay explicit.',
                tone: 'warning',
            },
            {
                label: 'Payroll execution',
                value: 'Run visible',
                description: 'Assignments, runs, and payslips stay inside the same system.',
                tone: 'success',
            },
        ],
        frameRows: [
            {
                record: 'Employee access invite',
                detail: 'HR-driven onboarding with linked user access',
                owner: 'HR',
                status: 'active',
            },
            {
                record: 'Leave request review',
                detail: 'Manager approval before balance consumption',
                owner: 'Manager',
                status: 'pending',
            },
            {
                record: 'Payroll run',
                detail: 'Work entries prepared for processing',
                owner: 'Payroll',
                status: 'in_progress',
            },
        ],
    },
    {
        id: 'integrations',
        label: 'Integrations',
        title: 'Govern outbound events, deliveries, and operational signals from one integration surface.',
        description:
            'Webhook endpoints, delivery history, queue health, notifications, and governance views are part of the product, not an afterthought.',
        bullets: [
            'Register webhook endpoints and inspect outbound delivery history.',
            'Use queue health and governance workspaces to track delivery behavior.',
            'Keep audit logs and notifications available alongside operational modules.',
        ],
        frameTitle: 'Integration and governance workspace',
        frameContext: 'Event delivery and operational governance with traceable records.',
        frameCards: [
            { title: 'Webhooks', helper: 'Endpoint management and subscriptions' },
            { title: 'Deliveries', helper: 'Status history and retry visibility' },
            { title: 'Governance', helper: 'Operational controls and reporting cadence' },
            { title: 'Queue health', helper: 'Failure review and delivery exceptions' },
        ],
        frameMetrics: [
            {
                label: 'Endpoint governance',
                value: 'Subscription aware',
                description: 'Outbound events stay attached to explicit endpoint records.',
                tone: 'info',
            },
            {
                label: 'Delivery visibility',
                value: 'Retry retained',
                description: 'Failures remain visible for review, diagnosis, and retry.',
                tone: 'warning',
            },
            {
                label: 'Operational review',
                value: 'Exceptions surfaced',
                description: 'Queue health and notifications stay close to platform controls.',
                tone: 'danger',
            },
        ],
        frameRows: [
            {
                record: 'Webhook endpoint',
                detail: 'Active endpoint with governed event flow',
                owner: 'Platform',
                status: 'active',
            },
            {
                record: 'Delivery retry',
                detail: 'Retained for inspection and rerun',
                owner: 'Platform',
                status: 'failed',
            },
            {
                record: 'Queue exception review',
                detail: 'Operational issue surfaced to platform controls',
                owner: 'Ops',
                status: 'queued',
            },
        ],
    },
];

const modules: ModuleCard[] = [
    {
        title: 'Sales',
        description: 'Track commercial work from lead to quote to customer order.',
        capabilities: ['Leads and pipeline activity', 'Quotes and sales orders', 'Operational handoff into billing'],
        icon: Handshake,
    },
    {
        title: 'Purchasing',
        description: 'Control vendor-facing procurement and receiving workflows.',
        capabilities: ['RFQs and purchase orders', 'Receiving context into inventory', 'Vendor-side operational visibility'],
        icon: ShoppingCart,
    },
    {
        title: 'Inventory',
        description: 'Operate stock, movements, counts, reordering, and traceability.',
        capabilities: ['Warehouses, locations, lots', 'Stock moves and cycle counts', 'Reordering and coverage controls'],
        icon: Warehouse,
    },
    {
        title: 'Accounting',
        description: 'Execute invoices, payments, journals, statements, and review.',
        capabilities: ['Invoices and payments', 'Ledger and statement workspaces', 'Posting-aware finance controls'],
        icon: FileSpreadsheet,
    },
    {
        title: 'Projects',
        description: 'Manage delivery, timesheets, billables, and recurring work.',
        capabilities: ['Projects, tasks, milestones', 'Billables and billing handoff', 'Recurring billing schedules'],
        icon: FolderKanban,
    },
    {
        title: 'HR',
        description: 'Handle employee records and people workflows inside the ERP.',
        capabilities: ['Employees and access onboarding', 'Leave, attendance, reimbursements', 'Payroll structures and runs'],
        icon: UserRoundCheck,
    },
    {
        title: 'Approvals and reports',
        description: 'Keep controlled decisions and reporting close to operational work.',
        capabilities: ['Shared approval queue', 'Reporting workspaces', 'Operational visibility across modules'],
        icon: ClipboardCheck,
    },
    {
        title: 'Integrations and governance',
        description: 'Manage outbound events, notifications, audit trails, and platform controls.',
        capabilities: ['Webhook endpoints and deliveries', 'Audit logs and notifications', 'Governance and queue visibility'],
        icon: PlugZap,
    },
];

const roleUseCases: RoleUseCase[] = [
    {
        title: 'Finance leaders',
        manages: 'Invoices, payments, journals, statements, ledger review, and reporting workspaces.',
        removes: 'Spreadsheet rollups and disconnected handoffs from commercial execution.',
        improves: 'Cash visibility, posting confidence, and close-readiness.',
    },
    {
        title: 'Operations teams',
        manages: 'Orders, procurement, receipts, stock movement, counts, and replenishment.',
        removes: 'Context switching between sales, warehouse, and purchasing tools.',
        improves: 'Execution visibility, stock confidence, and operational response time.',
    },
    {
        title: 'Project managers',
        manages: 'Project delivery, tasks, time, milestones, billables, and recurring billing.',
        removes: 'Separate delivery and billing tracking surfaces.',
        improves: 'Billing readiness, project visibility, and delivery accountability.',
    },
    {
        title: 'HR and people ops',
        manages: 'Employees, access onboarding, leave, attendance, reimbursements, and payroll flows.',
        removes: 'Split HR records and approval trails across separate systems.',
        improves: 'Access governance, request tracking, and payroll coordination.',
    },
    {
        title: 'Executives and admins',
        manages: 'Company settings, approvals, reports, integrations, notifications, and audit visibility.',
        removes: 'Blind spots between departments and missing operational oversight.',
        improves: 'Cross-functional visibility and control over critical workflow states.',
    },
];

const workflowItems: WorkflowItem[] = [
    {
        title: 'Capture work in the source module',
        description:
            'A quote, receipt, reimbursement, timesheet, or invoice starts in the module where the work actually happens.',
    },
    {
        title: 'Route through approvals and review',
        description:
            'Requests and records move through explicit approval or review states rather than disappearing into inboxes or side spreadsheets.',
    },
    {
        title: 'Execute against the governed record',
        description:
            'Teams continue from the same operational record into stock movement, finance posting, payroll, or integration delivery.',
    },
    {
        title: 'Report from the same system of record',
        description:
            'Dashboards, queues, audit views, and reports reflect the same workflow states used during execution.',
    },
];

const securityItems: SecurityItem[] = [
    {
        title: 'Role-based access',
        description:
            'Navigation, records, and actions are permission-aware across company and platform contexts.',
        icon: ShieldCheck,
    },
    {
        title: 'Approvals and audit visibility',
        description:
            'Approval queues, status transitions, and audit logs make decisions and record movement traceable.',
        icon: ClipboardCheck,
    },
    {
        title: 'Reporting and governed outputs',
        description:
            'Reports, statements, and review surfaces stay tied to the same operational records used during execution.',
        icon: BarChart3,
    },
    {
        title: 'Governed integrations',
        description:
            'Webhook endpoints, delivery history, queue health, and notifications provide visibility into outbound event handling.',
        icon: PlugZap,
    },
];

const securityEvidence = [
    'Company memberships and roles',
    'Shared approvals queue',
    'Audit logs',
    'Reports and statements',
    'Notifications',
    'Webhook delivery history',
    'Queue health review',
];

const proofItems: ProofItem[] = [
    {
        title: 'One governed record chain',
        description:
            'Commercial, inventory, finance, project, HR, and integration records stay connected inside one governed operating model.',
    },
    {
        title: 'Ownership stays visible',
        description:
            'Approval queues, status states, notifications, and reporting surfaces keep responsibility explicit across teams.',
    },
    {
        title: 'Rollout can happen in sequence',
        description:
            'Port-101 supports phased adoption so teams can move in sequence without disrupting the whole business at once.',
    },
];

const salesPathProfiles: SalesPathProfile[] = [
    {
        title: 'Growth teams',
        fit: 'Best when a company needs one workspace for day-to-day execution without starting from a full custom ERP rollout.',
        scope: [
            'Commercial flow, stock, and finance visibility',
            'Clear approval and reporting foundations',
            'Operational structure without point-tool sprawl',
        ],
        support:
            'Start with the highest-friction workflows first, then add adjacent modules as teams adopt the shared workspace.',
    },
    {
        title: 'Multi-team operations',
        fit: 'Best when finance, operations, projects, and people need aligned records across the same platform.',
        scope: [
            'Cross-functional records and workflow ownership',
            'Project, HR, and finance handoff visibility',
            'Reporting surfaces for managers and admins',
        ],
        support:
            'Roll out by function, preserving shared visibility and controlled approvals as more teams come into the platform.',
        highlight: true,
    },
    {
        title: 'Controlled enterprise work',
        fit: 'Best when governed access, audit visibility, notifications, and integration controls matter as much as core execution.',
        scope: [
            'Governance, notifications, and audit surfaces',
            'Integration delivery visibility and queue review',
            'Structured access and company-level controls',
        ],
        support:
            'Use the modular architecture to sequence adoption while keeping control layers close to operational execution.',
    },
];

const faqs: FaqItem[] = [
    {
        question: 'What does Port-101 cover?',
        answer:
            'Port-101 covers sales, purchasing, inventory, accounting, projects, HR, approvals, reports, notifications, audit logs, and integration governance.',
    },
    {
        question: 'Who is Port-101 for?',
        answer:
            'It is built for company teams that need commercial work, operational execution, finance review, project delivery, people workflows, and reporting to stay inside one ERP workspace.',
    },
    {
        question: 'Can teams adopt modules gradually?',
        answer:
            'Yes. Port-101 supports phased rollout by operational area, so teams can adopt the platform in a practical sequence.',
    },
    {
        question: 'Does the product support approvals and reporting?',
        answer:
            'Yes. The codebase includes a shared approvals queue, reporting workspaces, dashboards, and record states that surface review and execution status.',
    },
    {
        question: 'How does access control work?',
        answer:
            'The product is permission-aware, uses company membership and role-based access, and exposes governance surfaces such as audit logs and notifications.',
    },
    {
        question: 'Does Port-101 include HR and project workflows?',
        answer:
            'Yes. HR includes employees, leave, attendance, reimbursements, and payroll. Projects include delivery records, tasks, timesheets, milestones, billables, and recurring billing.',
    },
    {
        question: 'Can I book a demo or contact sales from the public site?',
        answer:
            'Yes. You can book a demo or contact sales directly from the public site.',
    },
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const isAuthenticated = Boolean(auth.user);
    const isSuperAdmin = Boolean(auth.user?.is_super_admin);
    const dashboardHref = isSuperAdmin
        ? platformDashboard()
        : companyDashboard();

    return (
        <>
            <Head title="Port-101" />
            <PublicLayout
                navLinks={publicNavLinks}
                footerGroups={publicFooterGroups}
                isAuthenticated={isAuthenticated}
                dashboardHref={dashboardHref}
            >
                <HeroSection
                    isAuthenticated={isAuthenticated}
                    dashboardHref={dashboardHref}
                    trustChips={trustChips}
                    previewCards={heroPreviewCards}
                    previewQueue={heroPreviewQueue}
                />
                <TrustStrip items={trustChips} />
                <ProductPreviewSection
                    tabs={productPreviewTabs}
                    isAuthenticated={isAuthenticated}
                    dashboardHref={dashboardHref}
                />
                <ModuleGridSection items={modules} />
                <RoleUseCaseSection items={roleUseCases} />
                <WorkflowSection items={workflowItems} />
                <SecuritySection items={securityItems} evidence={securityEvidence} />
                <ProofSection items={proofItems} />
                <SalesPathSection
                    profiles={salesPathProfiles}
                    isAuthenticated={isAuthenticated}
                    dashboardHref={dashboardHref}
                />
                <FaqSection items={faqs} />
                <FinalCtaSection
                    isAuthenticated={isAuthenticated}
                    dashboardHref={dashboardHref}
                />
            </PublicLayout>
        </>
    );
}
