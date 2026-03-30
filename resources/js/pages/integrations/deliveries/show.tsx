import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { DetailHero } from '@/components/shell/detail-hero';
import { TabbedDetailShell } from '@/components/shell/tabbed-detail-shell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Delivery = {
    id: string;
    webhook_endpoint_id: string;
    endpoint_name?: string | null;
    endpoint_url?: string | null;
    integration_event_id: string;
    event_label: string;
    event_type: string;
    event_payload: Record<string, unknown>;
    occurred_at?: string | null;
    status: string;
    status_label: string;
    attempt_count: number;
    first_attempt_at?: string | null;
    last_attempt_at?: string | null;
    next_retry_at?: string | null;
    response_status?: number | null;
    duration_ms?: number | null;
    response_body_excerpt?: string | null;
    failure_message?: string | null;
    delivered_at?: string | null;
    dead_lettered_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    can_retry: boolean;
};

type SecurityPolicy = {
    signature_version: string;
    signature_algorithm: string;
    signed_content: string;
    timestamp_header: string;
    signature_header: string;
    signature_version_header: string;
    event_header: string;
    event_id_header: string;
    replay_window_seconds: number;
};

type Props = {
    delivery: Delivery;
    securityPolicy: SecurityPolicy;
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function ShowWebhookDelivery({
    delivery,
    securityPolicy,
}: Props) {
    const tabs = [
        {
            id: 'overview',
            label: 'Overview',
            content: (
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Delivery timeline</CardTitle>
                            <CardDescription>
                                Operational timestamps and retry lifecycle for this outbound event delivery.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <DetailField label="Endpoint" value={delivery.endpoint_name ?? '-'} />
                                <DetailField label="Event" value={delivery.event_label} />
                                <DetailField label="Event type" value={delivery.event_type} />
                                <DetailField label="Occurred at" value={formatDateTime(delivery.occurred_at)} />
                                <DetailField label="First attempt" value={formatDateTime(delivery.first_attempt_at)} />
                                <DetailField label="Last attempt" value={formatDateTime(delivery.last_attempt_at)} />
                                <DetailField label="Next retry" value={formatDateTime(delivery.next_retry_at)} />
                                <DetailField label="Delivered at" value={formatDateTime(delivery.delivered_at)} />
                                <DetailField label="Dead lettered at" value={formatDateTime(delivery.dead_lettered_at)} />
                                <DetailField label="Created at" value={formatDateTime(delivery.created_at)} />
                                <DetailField label="Updated at" value={formatDateTime(delivery.updated_at)} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Endpoint context</CardTitle>
                            <CardDescription>
                                Routing target and current delivery state for this record.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <DetailBlock label="Status">
                                <StatusBadge
                                    status={delivery.status}
                                    label={delivery.status_label}
                                />
                            </DetailBlock>
                            <DetailBlock label="Endpoint URL">
                                <span className="break-all">{delivery.endpoint_url ?? '-'}</span>
                            </DetailBlock>
                            <DetailBlock label="Integration event ID">
                                <span className="font-mono text-xs">{delivery.integration_event_id}</span>
                            </DetailBlock>
                        </CardContent>
                    </Card>
                </div>
            ),
        },
        {
            id: 'payload',
            label: 'Payload',
            content: (
                <Card>
                    <CardHeader>
                        <CardTitle>Event payload</CardTitle>
                        <CardDescription>
                            Exact outbound JSON body recorded for this delivery.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <pre className="overflow-x-auto rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] p-4 font-mono text-xs leading-6 whitespace-pre-wrap text-[color:var(--text-secondary)]">
                            {JSON.stringify(delivery.event_payload, null, 2)}
                        </pre>
                    </CardContent>
                </Card>
            ),
        },
        {
            id: 'response',
            label: 'Response',
            content: (
                <div className="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Response summary</CardTitle>
                            <CardDescription>
                                HTTP response and transport timing captured from the receiver.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <DetailField
                                label="HTTP status"
                                value={delivery.response_status ? `HTTP ${delivery.response_status}` : '-'}
                            />
                            <DetailField
                                label="Duration"
                                value={delivery.duration_ms ? `${delivery.duration_ms} ms` : '-'}
                            />
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Failure message</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] p-4 text-sm text-foreground">
                                    {delivery.failure_message ?? 'None'}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Response excerpt</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] p-4 text-sm whitespace-pre-wrap text-foreground">
                                    {delivery.response_body_excerpt ?? 'No response excerpt stored.'}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            ),
        },
        {
            id: 'security',
            label: 'Security',
            content: (
                <div className="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Security policy</CardTitle>
                            <CardDescription>
                                Signature and replay guarantees applied to every webhook delivery.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <DetailField label="Signature version" value={securityPolicy.signature_version} />
                            <DetailField label="Algorithm" value={securityPolicy.signature_algorithm} />
                            <DetailField label="Replay window" value={`${securityPolicy.replay_window_seconds} sec`} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Verification contract</CardTitle>
                            <CardDescription>
                                Headers and signed content expected by downstream consumers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4 text-sm text-foreground">
                                <DetailBlock label="Signed content">
                                    <code>{securityPolicy.signed_content}</code>
                                </DetailBlock>
                                <div>
                                    <p className="text-[11px] font-semibold tracking-[0.08em] text-[color:var(--text-secondary)] uppercase">
                                        Headers
                                    </p>
                                    <div className="mt-3 grid gap-2 md:grid-cols-2">
                                        <HeaderCode>{securityPolicy.event_header}</HeaderCode>
                                        <HeaderCode>{securityPolicy.event_id_header}</HeaderCode>
                                        <HeaderCode>{securityPolicy.timestamp_header}</HeaderCode>
                                        <HeaderCode>{securityPolicy.signature_header}</HeaderCode>
                                        <HeaderCode>{securityPolicy.signature_version_header}</HeaderCode>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.integrations, {
                    title: 'Delivery queue',
                    href: '/company/integrations/deliveries',
                },
                {
                    title: delivery.id,
                    href: `/company/integrations/deliveries/${delivery.id}`,
                },)}
        >
            <Head title={`Delivery ${delivery.id}`} />

            <TabbedDetailShell
                hero={
                    <DetailHero
                        title={`Delivery ${delivery.id}`}
                        description="Outbound webhook delivery detail with payload, response, retry, and signature context."
                        status={
                            <StatusBadge
                                status={delivery.status}
                                label={delivery.status_label}
                            />
                        }
                        meta={
                            <>
                                <span>{delivery.event_label}</span>
                                <span>|</span>
                                <span>{delivery.endpoint_name ?? 'Unknown endpoint'}</span>
                            </>
                        }
                        actions={
                            <>
                                {delivery.endpoint_name && (
                                    <Button variant="outline" asChild>
                                        <Link href={`/company/integrations/webhooks/${delivery.webhook_endpoint_id}`}>
                                            Open endpoint
                                        </Link>
                                    </Button>
                                )}
                                <BackLinkAction href="/company/integrations/deliveries" label="Back to queue
                                    " variant="outline" />
                                {delivery.can_retry && (
                                    <Button
                                        onClick={() =>
                                            router.post(
                                                `/company/integrations/deliveries/${delivery.id}/retry`,
                                            )
                                        }
                                    >
                                        Retry delivery
                                    </Button>
                                )}
                            </>
                        }
                        metrics={[
                            {
                                label: 'Attempts',
                                value: delivery.attempt_count,
                                tone: delivery.attempt_count > 1 ? 'warning' : 'default',
                            },
                            {
                                label: 'HTTP status',
                                value: delivery.response_status
                                    ? `HTTP ${delivery.response_status}`
                                    : '-',
                                tone:
                                    delivery.response_status && delivery.response_status >= 400
                                        ? 'danger'
                                        : 'default',
                            },
                            {
                                label: 'Latency',
                                value: delivery.duration_ms
                                    ? `${delivery.duration_ms} ms`
                                    : '-',
                            },
                            {
                                label: 'Next retry',
                                value: delivery.next_retry_at
                                    ? new Date(delivery.next_retry_at).toLocaleDateString()
                                    : '-',
                            },
                        ]}
                    />
                }
                tabs={tabs}
                defaultTab="overview"
            />
        </AppLayout>
    );
}

function DetailField({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-4 py-3">
            <p className="text-[11px] font-semibold tracking-[0.08em] text-[color:var(--text-secondary)] uppercase">
                {label}
            </p>
            <p className="mt-2 text-sm font-medium text-foreground">{value}</p>
        </div>
    );
}

function DetailBlock({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div>
            <p className="text-[11px] font-semibold tracking-[0.08em] text-[color:var(--text-secondary)] uppercase">
                {label}
            </p>
            <div className="mt-2 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-4 py-3 text-sm">
                {children}
            </div>
        </div>
    );
}

function HeaderCode({ children }: { children: ReactNode }) {
    return (
        <code className="rounded-[var(--radius-control)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3 py-2 text-xs text-foreground">
            {children}
        </code>
    );
}
