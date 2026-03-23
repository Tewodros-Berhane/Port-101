<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Integrations\Models\WebhookDelivery;

class WebhookDeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('integrations.webhooks.view')
            || $user->hasPermission('integrations.webhooks.manage');
    }

    public function view(User $user, WebhookDelivery $delivery): bool
    {
        return (
            $user->hasPermission('integrations.webhooks.view')
            || $user->hasPermission('integrations.webhooks.manage')
        ) && $user->canAccessDataScopedRecord($delivery);
    }

    public function retry(User $user, WebhookDelivery $delivery): bool
    {
        return $user->hasPermission('integrations.webhooks.manage')
            && $user->canAccessDataScopedRecord($delivery);
    }
}
