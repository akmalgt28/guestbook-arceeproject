<?php

use App\Models\Event;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $eventFilter = '';
    public string $statusFilter = '';
    public bool $formOpen = false;
    public ?int $editingId = null;
    public string $status = Rsvp::STATUS_PENDING;
    public string $attendingCount = '0';
    public string $notes = '';

    public function with(): array
    {
        abort_unless(auth()->user()?->can('rsvps.view'), 403);
        return [
            'rsvps' => $this->accessibleRsvps()->when($this->search !== '', fn (Builder $query): Builder => $query->whereHas('invitation.recipient', fn (Builder $query): Builder => $query->where('name', 'ilike', "%{$this->search}%")))->when($this->eventFilter !== '', fn (Builder $query): Builder => $query->whereHas('invitation', fn (Builder $query): Builder => $query->where('event_id', $this->eventFilter)))->when($this->statusFilter !== '', fn (Builder $query): Builder => $query->where('status', $this->statusFilter))->with(['invitation.event', 'invitation.recipient', 'attendees'])->latest('responded_at')->get(),
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
        ];
    }

    public function openEdit(int $rsvpId): void
    {
        abort_unless(auth()->user()?->can('rsvps.update'), 403);
        $rsvp = $this->accessibleRsvps()->findOrFail($rsvpId);
        $this->editingId = $rsvp->id; $this->status = $rsvp->status; $this->attendingCount = (string) $rsvp->attending_count; $this->notes = $rsvp->notes ?? ''; $this->formOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('rsvps.update'), 403);
        $data = $this->validate(['status' => ['required', 'in:pending,attending,not_attending,cancelled'], 'attendingCount' => ['required', 'integer', 'min:0', 'max:999'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $rsvp = $this->accessibleRsvps()->findOrFail($this->editingId);
        $rsvp->update(['status' => $data['status'], 'attending_count' => $data['status'] === Rsvp::STATUS_ATTENDING ? $data['attendingCount'] : 0, 'notes' => $data['notes'] ?: null]);
        $this->formOpen = false; $this->reset(['editingId', 'notes']); session()->flash('success', 'RSVP berhasil diperbarui.');
    }

    private function accessibleRsvps(): Builder
    {
        return Rsvp::query()->whereHas('invitation', fn (Builder $query): Builder => $query->whereIn('event_id', $this->accessibleEvents()->select('id')));
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user(); return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void { $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())); }));
    }
};
?>

    <div class="space-y-8">
        <div><h1 class="text-3xl font-semibold text-brand-950">RSVP</h1></div>
        @if (session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <div class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-5 md:grid-cols-3"><input wire:model.live.debounce.300ms="search" class="rounded-xl border-slate-300 px-4 py-3" placeholder="Cari nama penerima..."><select wire:model.live="eventFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua event</option>@foreach ($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select><select wire:model.live="statusFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua status</option><option value="attending">Hadir</option><option value="not_attending">Tidak hadir</option><option value="pending">Pending</option><option value="cancelled">Dibatalkan</option></select></div>
        <div class="grid gap-4">@forelse ($rsvps as $rsvp)<article class="rounded-3xl border border-slate-200 bg-white p-6"><div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-semibold">{{ $rsvp->invitation->recipient->name }}</h2><p class="mt-2 text-sm text-slate-500">{{ $rsvp->invitation->event->name }} · {{ $rsvp->responded_at?->format('d M Y H:i') ?: 'Belum merespons' }}</p></div><div class="flex items-center gap-3"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase">{{ $rsvp->status }}</span>@if (auth()->user()->can('rsvps.update'))<a href="{{ route('admin.rsvps.edit', $rsvp) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">Edit</a>@endif</div></div><div class="mt-5 flex flex-wrap gap-5 text-sm text-slate-500"><span>Hadir: {{ $rsvp->attending_count }}</span><span>Peserta: {{ $rsvp->attendees->count() }}</span></div></article>@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">Belum ada data RSVP.</div>@endforelse</div>
    </div>