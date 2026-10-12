<x-layouts.admin title="QR Codes">
    @if (isset($eventId))
    <livewire:admin.qr-codes :event-filter="$eventId" />
@else
    <livewire:admin.qr-codes />
@endif

</x-layouts.admin>

