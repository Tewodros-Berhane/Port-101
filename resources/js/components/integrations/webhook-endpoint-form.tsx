import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link } from '@inertiajs/react';

type EventOption = {
    value: string;
    label: string;
};

type WebhookEndpointFormData = {
    name: string;
    target_url: string;
    is_active: boolean;
    subscribed_events: string[];
};

type FormLike = {
    data: WebhookEndpointFormData;
    setData: <K extends keyof WebhookEndpointFormData>(
        key: K,
        value: WebhookEndpointFormData[K],
    ) => void;
    errors: Record<string, string | undefined>;
    processing: boolean;
};

type Props = {
    form: FormLike;
    eventOptions: EventOption[];
    submitLabel: string;
    cancelHref: string;
    onSubmit: () => void;
};

export function WebhookEndpointForm({
    form,
    eventOptions,
    submitLabel,
    cancelHref,
    onSubmit,
}: Props) {
    const selectedEvents = form.data.subscribed_events;
    const selectsAllEvents = selectedEvents.includes('*');

    const setSelectAll = (checked: boolean) => {
        form.setData('subscribed_events', checked ? ['*'] : []);
    };

    const toggleEvent = (eventValue: string, checked: boolean) => {
        if (eventValue === '*') {
            setSelectAll(checked);

            return;
        }

        const nextEvents = checked
            ? Array.from(
                  new Set([
                      ...selectedEvents.filter((value) => value !== '*'),
                      eventValue,
                  ]),
              )
            : selectedEvents.filter((value) => value !== eventValue);

        form.setData('subscribed_events', nextEvents);
    };

    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section className="space-y-4 rounded-xl border p-5">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Endpoint configuration
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Configure where Port-101 sends signed event payloads.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Display name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Finance automation"
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="target_url">Target URL</Label>
                        <Input
                            id="target_url"
                            value={form.data.target_url}
                            onChange={(event) =>
                                form.setData('target_url', event.target.value)
                            }
                            placeholder="https://example.com/webhooks/port101"
                        />
                        <InputError message={form.errors.target_url} />
                        <p className="text-xs text-muted-foreground">
                            Production endpoints should use HTTPS. The request is
                            signed with the endpoint-specific secret.
                        </p>
                    </div>

                    <div className="rounded-xl border border-dashed p-4">
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <div className="space-y-1">
                                <Label
                                    htmlFor="is_active"
                                    className="text-sm font-medium"
                                >
                                    Endpoint active
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Inactive endpoints keep their history but do
                                    not receive new deliveries.
                                </p>
                            </div>
                        </div>
                        <InputError message={form.errors.is_active} />
                    </div>
                </section>

                <aside className="space-y-4 rounded-xl border p-5">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Delivery behavior
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Failed deliveries retry automatically before moving
                            to the dead-letter queue.
                        </p>
                    </div>

                    <div className="space-y-2 rounded-xl border bg-muted/20 p-4 text-sm">
                        <p className="font-medium">Headers sent on each request</p>
                        <ul className="space-y-1 text-xs text-muted-foreground">
                            <li>`X-Port101-Event`</li>
                            <li>`X-Port101-Event-Id`</li>
                            <li>`X-Port101-Timestamp`</li>
                            <li>`X-Port101-Signature`</li>
                        </ul>
                    </div>

                    <div className="space-y-2 rounded-xl border bg-muted/20 p-4 text-sm">
                        <p className="font-medium">Retry cadence</p>
                        <p className="text-xs text-muted-foreground">
                            1 minute, 5 minutes, 15 minutes, 1 hour, then dead
                            letter after the maximum retry count is reached.
                        </p>
                    </div>
                </aside>
            </div>

            <section className="space-y-4 rounded-xl border p-5">
                <div>
                    <h2 className="text-sm font-semibold">Subscribed events</h2>
                    <p className="text-xs text-muted-foreground">
                        Select the business events this endpoint should receive.
                    </p>
                </div>

                <label className="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/30">
                    <Checkbox
                        checked={selectsAllEvents}
                        onCheckedChange={(checked) =>
                            setSelectAll(checked === true)
                        }
                    />
                    <div className="space-y-1">
                        <span className="text-sm font-medium">
                            All supported events
                        </span>
                        <p className="text-xs text-muted-foreground">
                            Subscribe this endpoint to every currently supported
                            outbound event.
                        </p>
                    </div>
                </label>

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {eventOptions
                        .filter((eventOption) => eventOption.value !== '*')
                        .map((eventOption) => {
                            const checked =
                                selectsAllEvents ||
                                selectedEvents.includes(eventOption.value);

                            return (
                                <label
                                    key={eventOption.value}
                                    className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors ${
                                        checked
                                            ? 'border-primary/50 bg-primary/5'
                                            : 'hover:bg-muted/30'
                                    }`}
                                >
                                    <Checkbox
                                        checked={checked}
                                        disabled={selectsAllEvents}
                                        onCheckedChange={(value) =>
                                            toggleEvent(
                                                eventOption.value,
                                                value === true,
                                            )
                                        }
                                    />
                                    <div className="space-y-1">
                                        <span className="text-sm font-medium">
                                            {eventOption.label}
                                        </span>
                                        <p className="text-xs text-muted-foreground">
                                            `{eventOption.value}`
                                        </p>
                                    </div>
                                </label>
                            );
                        })}
                </div>
                <InputError
                    message={
                        form.errors.subscribed_events ??
                        form.errors['subscribed_events.0']
                    }
                />
            </section>

            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" disabled={form.processing}>
                    {submitLabel}
                </Button>
                <Button variant="outline" asChild>
                    <Link href={cancelHref}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}
