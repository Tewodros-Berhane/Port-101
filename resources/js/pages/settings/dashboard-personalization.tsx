import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type OperationsTab = 'companies' | 'invites' | 'admin_actions';
type WidgetId =
    | 'delivery_performance'
    | 'governance_snapshot'
    | 'operations_presets'
    | 'operations_detail';

type Props = {
    operationsReportPresets: {
        id: string;
        name: string;
    }[];
    dashboardPreferences: {
        default_preset_id?: string | null;
        default_operations_tab?: OperationsTab;
        layout?: 'balanced' | 'analytics_first' | 'operations_first';
        hidden_widgets?: WidgetId[];
    };
};

const widgetLabels: Record<WidgetId, string> = {
    delivery_performance: 'Delivery performance',
    governance_snapshot: 'Governance snapshot',
    operations_presets: 'Saved presets',
    operations_detail: 'Operations detail tabs',
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard personalization',
        href: '/settings/dashboard-personalization',
    },
];

export default function DashboardPersonalization({
    operationsReportPresets,
    dashboardPreferences,
}: Props) {
    const form = useForm({
        default_preset_id: dashboardPreferences.default_preset_id ?? '',
        default_operations_tab:
            dashboardPreferences.default_operations_tab ?? 'companies',
        layout: dashboardPreferences.layout ?? 'balanced',
        hidden_widgets: dashboardPreferences.hidden_widgets ?? [],
    });
    const defaultPresetError = form.errors.default_preset_id;

    const toggleWidgetVisibility = (widget: WidgetId, visible: boolean) => {
        const current = [...form.data.hidden_widgets];

        if (visible) {
            form.setData(
                'hidden_widgets',
                current.filter((item) => item !== widget),
            );

            return;
        }

        if (!current.includes(widget)) {
            form.setData('hidden_widgets', [...current, widget]);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard personalization" />

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Dashboard personalization"
                        description="Save your default platform dashboard layout and widgets."
                    />

                    <form
                        className="space-y-4 rounded-xl border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put('/settings/dashboard-personalization', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="default_preset_id">
                                    Default preset
                                </Label>
                                <select
                                    id="default_preset_id"
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                    value={form.data.default_preset_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_preset_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">No default preset</option>
                                    {operationsReportPresets.map((preset) => (
                                        <option key={preset.id} value={preset.id}>
                                            {preset.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={defaultPresetError} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="default_operations_tab">
                                    Default operations tab
                                </Label>
                                <select
                                    id="default_operations_tab"
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                    value={form.data.default_operations_tab}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_operations_tab',
                                            event.target.value as OperationsTab,
                                        )
                                    }
                                >
                                    <option value="companies">Companies</option>
                                    <option value="invites">Invites</option>
                                    <option value="admin_actions">
                                        Admin actions
                                    </option>
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="layout">Widget layout</Label>
                                <select
                                    id="layout"
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                    value={form.data.layout}
                                    onChange={(event) =>
                                        form.setData(
                                            'layout',
                                            event.target.value as
                                                | 'balanced'
                                                | 'analytics_first'
                                                | 'operations_first',
                                        )
                                    }
                                >
                                    <option value="balanced">Balanced</option>
                                    <option value="analytics_first">
                                        Analytics first
                                    </option>
                                    <option value="operations_first">
                                        Operations first
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div className="rounded-lg border p-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                Visible widgets
                            </p>
                            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                {(Object.keys(widgetLabels) as WidgetId[]).map(
                                    (widget) => {
                                        const visible =
                                            !form.data.hidden_widgets.includes(
                                                widget,
                                            );

                                        return (
                                            <label
                                                key={widget}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={visible}
                                                    onChange={(event) =>
                                                        toggleWidgetVisibility(
                                                            widget,
                                                            event.target.checked,
                                                        )
                                                    }
                                                />
                                                <span>
                                                    {widgetLabels[widget]}
                                                </span>
                                            </label>
                                        );
                                    },
                                )}
                            </div>
                        </div>

                        <Button type="submit" disabled={form.processing}>
                            Save personalization
                        </Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
