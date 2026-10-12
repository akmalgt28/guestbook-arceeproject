<x-layouts.admin title="Recipients">
    @if (isset($eventId))
    <livewire:admin.recipients :event-filter="$eventId" :event-context="true" />
@else
    <livewire:admin.recipients />
@endif

</x-layouts.admin>

