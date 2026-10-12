<x-layouts.admin title="Pesan">
    @if (isset($eventId))
    <livewire:admin.guestbook :event-filter="$eventId" :event-context="true" />
@else
    <livewire:admin.guestbook />
@endif

</x-layouts.admin>

