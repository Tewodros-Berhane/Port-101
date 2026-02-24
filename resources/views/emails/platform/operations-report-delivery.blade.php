<x-mail::message>
# Scheduled Operations Reports Ready

Preset: **{{ $presetName }}**

Window: **{{ $periodLabel }}**

Format: **{{ $format }}**

Admin actions: **{{ (int) ($summary['admin_actions'] ?? 0) }}**  
Invite deliveries: **{{ (int) ($summary['delivery_total'] ?? 0) }}**  
Failures: **{{ (int) ($summary['failed'] ?? 0) }}**  
Failure rate: **{{ (float) ($summary['failure_rate'] ?? 0) }}%**

<x-mail::button :url="url($links['admin_actions'] ?? '/platform/reports')">
Open Admin Actions Export
</x-mail::button>

<x-mail::button :url="url($links['delivery_trends'] ?? '/platform/reports')">
Open Delivery Trends Export
</x-mail::button>

Attached files are included for direct download.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
