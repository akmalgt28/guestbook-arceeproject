<x-layouts.admin title="Media">
    @if (isset($eventId))
    <livewire:admin.media :event-filter="$eventId" :event-context="true" />
@else
    <livewire:admin.media />
@endif

</x-layouts.admin>

