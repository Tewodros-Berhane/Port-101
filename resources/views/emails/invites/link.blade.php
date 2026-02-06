<x-mail::message>
# You are invited to Port-101

You have been invited as **{{ str_replace('_', ' ', $invite->role) }}**.

@if($invite->company)
Company: **{{ $invite->company->name }}**
@endif

<x-mail::button :url="$inviteUrl">
Accept Invite
</x-mail::button>

This invite expires on **{{ optional($invite->expires_at)->toDayDateTimeString() ?? 'N/A' }}**.

If you did not expect this invite, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
