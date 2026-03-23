<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Integrations\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('integrations.webhooks.view')
            || $user->hasPermission('integrations.webhooks.manage');
    }

    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return (
            $user->hasPermission('integrations.webhooks.view')
            || $user->hasPermission('integrations.webhooks.manage')
        ) && $user->canAccessDataScopedRecord($endpoint);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('integrations.webhooks.manage');
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->hasPermission('integrations.webhooks.manage')
            && $user->canAccessDataScopedRecord($endpoint);
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->hasPermission('integrations.webhooks.manage')
            && $user->canAccessDataScopedRecord($endpoint);
    }

    public function rotateSecret(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->hasPermission('integrations.webhooks.manage')
            && $user->canAccessDataScopedRecord($endpoint);
    }

    public function test(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->hasPermission('integrations.webhooks.manage')
            && $user->canAccessDataScopedRecord($endpoint);
    }
}
