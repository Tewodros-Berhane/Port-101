<?php

namespace App\Policies;

use App\Core\Attachments\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.attachments.view')
            && (bool) $user->current_company_id;
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $user->hasPermission('core.attachments.view')
            && $attachment->company_id === $user->current_company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.attachments.manage')
            && (bool) $user->current_company_id;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->hasPermission('core.attachments.manage')
            && $attachment->company_id === $user->current_company_id;
    }
}
