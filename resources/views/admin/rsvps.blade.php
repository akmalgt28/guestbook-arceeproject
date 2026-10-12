<x-layouts.admin title="RSVP">
    @if (isset($eventId))
    <livewire:admin.rsvps :event-filter="$eventId" />
@else
    <livewire:admin.rsvps />
@endif

</x-layouts.admin>

