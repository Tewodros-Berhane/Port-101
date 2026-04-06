<x-mail::message>
<span class="eyebrow">Workspace Access</span>

# You are invited to Port-101

Your access is ready. Use the secure link below to join the workspace and finish account setup.

<x-mail::panel>
**Role**<br>
{{ str_replace('_', ' ', $invite->role) }}

@if($invite->company)
**Company**<br>
{{ $invite->company->name }}
@endif

**Invite expires**<br>
{{ optional($invite->expires_at)->toDayDateTimeString() ?? 'N/A' }}
</x-mail::panel>

<x-mail::button :url="$inviteUrl">
Accept invite
</x-mail::button>

If you were not expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
