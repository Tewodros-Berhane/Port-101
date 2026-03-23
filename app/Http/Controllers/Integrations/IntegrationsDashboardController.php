<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Integrations\IntegrationWorkspaceService;
use App\Modules\Integrations\Models\IntegrationEvent;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsDashboardController extends Controller
{
    public function index(IntegrationWorkspaceService $workspaceService): Response
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        $user = request()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $endpointQuery = WebhookEndpoint::query()
            ->with('latestDelivery')
            ->withCount('deliveries')
            ->withCount([
                'deliveries as delivered_deliveries_count' => fn ($query) => $query
                    ->where('status', WebhookDelivery::STATUS_DELIVERED),
                'deliveries as failed_deliveries_count' => fn ($query) => $query
                    ->whereIn('status', [
                        WebhookDelivery::STATUS_FAILED,
                        WebhookDelivery::STATUS_DEAD,
                    ]),
                'deliveries as dead_deliveries_count' => fn ($query) => $query
                    ->where('status', WebhookDelivery::STATUS_DEAD),
            ]);

        $deliveryQuery = WebhookDelivery::query()
            ->with([
                'endpoint:id,name,target_url,is_active',
                'integrationEvent:id,event_type,payload,occurred_at',
            ]);

        $eventActivityQuery = IntegrationEvent::query();

        $user->applyDataScopeToQuery($endpointQuery);
        $user->applyDataScopeToQuery($deliveryQuery);
        $user->applyDataScopeToQuery($eventActivityQuery);

        $recentEndpoints = $endpointQuery
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (WebhookEndpoint $endpoint) => $workspaceService->mapEndpoint($endpoint, $user))
            ->values()
            ->all();

        $deadLetters = $deliveryQuery
            ->whereIn('status', [
                WebhookDelivery::STATUS_FAILED,
                WebhookDelivery::STATUS_DEAD,
            ])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (WebhookDelivery $delivery) => $workspaceService->mapDelivery($delivery, $user))
            ->values()
            ->all();

        $recentEventActivity = $eventActivityQuery
            ->selectRaw('event_type, COUNT(*) as aggregate_count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('event_type')
            ->orderByDesc('aggregate_count')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'event_type' => $row->event_type,
                'event_label' => $workspaceService->eventLabel((string) $row->event_type),
                'count' => (int) $row->aggregate_count,
            ])
            ->values()
            ->all();

        return Inertia::render('integrations/index', [
            'summary' => $workspaceService->moduleSummary($user),
            'recentEndpoints' => $recentEndpoints,
            'deadLetters' => $deadLetters,
            'recentEventActivity' => $recentEventActivity,
            'abilities' => [
                'can_manage_webhooks' => $user->can('create', WebhookEndpoint::class),
                'can_view_deliveries' => $user->can('viewAny', WebhookDelivery::class),
            ],
        ]);
    }
}
