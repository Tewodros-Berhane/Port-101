<?php

namespace App\Policies;

use App\Core\MasterData\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermission('core.contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.contacts.manage');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermission('core.contacts.manage');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasPermission('core.contacts.manage');
    }
}
