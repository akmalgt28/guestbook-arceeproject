<x-layouts.admin title="Check-in">
    @if (isset($eventId))
    <livewire:admin.checkins :event-filter="$eventId" />
@else
    <livewire:admin.checkins />
@endif

</x-layouts.admin>

