import { WebhookEndpointForm } from '@/components/integrations/webhook-endpoint-form';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';

type EventOption = {
    value: string;
    label: string;
};

type Props = {
    eventOptions: EventOption[];
};

export default function CreateWebhookEndpoint({ eventOptions }: Props) {
    const form = useForm({
        name: '',
        target_url: '',
        is_active: true,
        subscribed_events: [],
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Integrations', href: '/company/integrations' },
                {
                    title: 'Webhook endpoints',
                    href: '/company/integrations/webhooks',
                },
                {
                    title: 'Create endpoint',
                    href: '/company/integrations/webhooks/create',
                },
            ]}
        >
            <Head title="Create Webhook Endpoint" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold">
                        Create webhook endpoint
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        The signing secret is revealed once after creation. Save
                        it in the receiving system before leaving the page.
                    </p>
                </div>

                <WebhookEndpointForm
                    form={form}
                    eventOptions={eventOptions}
                    submitLabel="Create endpoint"
                    cancelHref="/company/integrations/webhooks"
                    onSubmit={() =>
                        form.post('/company/integrations/webhooks')
                    }
                />
            </div>
        </AppLayout>
    );
}
