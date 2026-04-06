<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
<div class="brand-lockup">
    <span class="brand-name">{{ config('app.name') }}</span>
    <span class="brand-tagline">Operational ERP for finance, inventory, projects, and HR</span>
</div>
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
{{ config('app.name') }}<br>
Operational workflows, approvals, and reporting in one workspace.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
