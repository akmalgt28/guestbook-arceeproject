<x-layouts.admin title="Guestbook">
    @if (isset($eventId))
    <livewire:admin.checkins :event-filter="$eventId" :event-context="true" />
@else
    <livewire:admin.checkins />
@endif

</x-layouts.admin>

