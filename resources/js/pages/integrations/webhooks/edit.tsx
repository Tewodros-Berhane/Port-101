import { Head, useForm } from '@inertiajs/react';
import { WebhookEndpointForm } from '@/components/integrations/webhook-endpoint-form';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type EventOption = {
    value: string;
    label: string;
};

type Endpoint = {
    id: string;
    name: string;
    target_url: string;
    is_active: boolean;
    subscribed_events: string[];
};

type Props = {
    endpoint: Endpoint;
    eventOptions: EventOption[];
};

export default function EditWebhookEndpoint({ endpoint, eventOptions }: Props) {
    const form = useForm({
        name: endpoint.name,
        target_url: endpoint.target_url,
        is_active: endpoint.is_active,
        subscribed_events: endpoint.subscribed_events,
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.integrations, {
                    title: 'Webhook endpoints',
                    href: '/company/integrations/webhooks',
                },
                {
                    title: endpoint.name,
                    href: `/company/integrations/webhooks/${endpoint.id}`,
                },
                {
                    title: 'Edit',
                    href: `/company/integrations/webhooks/${endpoint.id}/edit`,
                },)}
        >
            <Head title={`Edit ${endpoint.name}`} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold">
                        Edit webhook endpoint
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Adjust delivery target, active state, or subscribed
                        events without losing endpoint history.
                    </p>
                </div>

                <WebhookEndpointForm
                    form={form}
                    eventOptions={eventOptions}
                    submitLabel="Save changes"
                    cancelHref={`/company/integrations/webhooks/${endpoint.id}`}
                    onSubmit={() =>
                        form.put(`/company/integrations/webhooks/${endpoint.id}`)
                    }
                />
            </div>
        </AppLayout>
    );
}
