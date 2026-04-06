<x-mail::message>
<span class="eyebrow">Scheduled Delivery</span>

# Operations reports are ready

The latest scheduled export package for **{{ $presetName }}** has been prepared and attached.

<x-mail::panel>
**Reporting window**<br>
{{ $periodLabel }}

**Format**<br>
{{ $format }}

**Attachments**<br>
Included with this email for direct download.
</x-mail::panel>

<table class="metric-grid" width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
        <td class="metric-cell" width="25%">
            <span class="metric-label">Admin actions</span>
            <span class="metric-value">{{ (int) ($summary['admin_actions'] ?? 0) }}</span>
        </td>
        <td class="metric-cell" width="25%">
            <span class="metric-label">Invite deliveries</span>
            <span class="metric-value">{{ (int) ($summary['delivery_total'] ?? 0) }}</span>
        </td>
        <td class="metric-cell" width="25%">
            <span class="metric-label">Failures</span>
            <span class="metric-value">{{ (int) ($summary['failed'] ?? 0) }}</span>
        </td>
        <td class="metric-cell" width="25%">
            <span class="metric-label">Failure rate</span>
            <span class="metric-value">{{ number_format((float) ($summary['failure_rate'] ?? 0), 1) }}%</span>
        </td>
    </tr>
</table>

<x-mail::button :url="url($links['admin_actions'] ?? '/platform/reports')">
Open admin actions export
</x-mail::button>

<x-mail::button :url="url($links['delivery_trends'] ?? '/platform/reports')" color="success">
Open delivery trends export
</x-mail::button>

Use the platform reports workspace if you need to rerun the preset, review the attachments, or inspect delivery trends in more detail.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
