<x-layouts.admin :title="$eventId ? 'Edit Event' : 'Tambah Event'">
    <livewire:admin.event-form :event-id="$eventId" />
</x-layouts.admin>