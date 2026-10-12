<x-layouts.admin title="Guestbook">
    @if (isset($eventId))
    <livewire:admin.guestbook :event-filter="$eventId" />
@else
    <livewire:admin.guestbook />
@endif

</x-layouts.admin>

