<?php

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\GuestbookEntry;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public string $eventFilter = '';
    public bool $eventContext = false;
    public function mount(?string $eventFilter = null, bool $eventContext = false): void
    {
        $this->eventContext = $eventContext;

        if ($eventFilter !== null && $eventFilter !== '') {
            $this->eventFilter = $eventFilter;
            $this->eventContext = true;
        }
    }

    public string $statusFilter = '';

    public function with(): array
    {
        abort_unless(auth()->user()?->can('guestbook.view'), 403);
        return ['entries' => $this->accessibleEntries()->when($this->eventFilter !== '', fn (Builder $query): Builder => $query->where('event_id', $this->eventFilter))->when($this->statusFilter !== '', fn (Builder $query): Builder => $query->where('status', $this->statusFilter))->with('event')->latest()->get(), 'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id')];
    }

    public function moderate(int $entryId, string $status): void
    {
        abort_unless(auth()->user()?->can('guestbook.moderate'), 403);
        abort_unless(in_array($status, [GuestbookEntry::STATUS_VISIBLE, GuestbookEntry::STATUS_HIDDEN], true), 422);

        $entry = $this->accessibleEntries()->findOrFail($entryId);
        $oldValues = ['status' => $entry->status, 'published_at' => $entry->published_at?->toIso8601String()];
        $entry->update(['status' => $status, 'published_at' => $status === GuestbookEntry::STATUS_VISIBLE ? now() : null]);

        AuditLog::query()->create([
            'actor_id' => auth()->id(),
            'event_id' => $entry->event_id,
            'action' => 'guestbook.moderated',
            'subject_type' => GuestbookEntry::class,
            'subject_id' => $entry->getKey(),
            'old_values' => $oldValues,
            'new_values' => ['status' => $entry->status, 'published_at' => $entry->published_at?->toIso8601String()],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function delete(int $entryId): void
    {
        abort_unless(auth()->user()?->can('guestbook.moderate'), 403);

        $entry = $this->accessibleEntries()->findOrFail($entryId);
        AuditLog::query()->create([
            'actor_id' => auth()->id(),
            'event_id' => $entry->event_id,
            'action' => 'guestbook.deleted',
            'subject_type' => GuestbookEntry::class,
            'subject_id' => $entry->getKey(),
            'old_values' => ['status' => $entry->status, 'name' => $entry->name],
            'new_values' => ['deleted' => true],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        $entry->delete();
    }

    private function accessibleEntries(): Builder
    {
        return GuestbookEntry::query()->whereIn('event_id', $this->accessibleEvents()->select('id'));
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user(); return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void { $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())); }));
    }
};
?>

    <div class="space-y-8"><div><h1 class="text-3xl font-semibold text-brand-950">Pesan</h1></div><div class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-5 md:grid-cols-2">@if ($eventContext)<div class="flex h-[50px] items-center justify-between rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-slate-700"><span>{{ $events->get($eventFilter, 'Event aktif') }}</span><i data-lucide="lock" class="size-4 text-slate-500"></i></div>@else<select wire:model.live="eventFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua event</option>@foreach ($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select>@endif<select wire:model.live="statusFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua status</option><option value="visible">Visible</option><option value="hidden">Hidden</option></select></div><div class="grid gap-4">@forelse ($entries as $entry)<article class="rounded-3xl border border-slate-200 bg-white p-6"><div class="flex items-start justify-between gap-4"><div><h2 class="font-semibold">{{ $entry->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $entry->event->name }} · {{ $entry->created_at?->format('d M Y H:i') }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase">{{ $entry->status }}</span></div><p class="mt-5 whitespace-pre-line text-slate-700">{{ $entry->message }}</p><div class="mt-5 flex gap-2">@if ($entry->status === 'visible')<button wire:click="moderate({{ $entry->id }}, 'hidden')" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">Sembunyikan</button>@else<button wire:click="moderate({{ $entry->id }}, 'visible')" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Tampilkan</button>@endif<button wire:click="delete({{ $entry->id }})" wire:confirm="Hapus ucapan ini?" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Hapus</button></div></article>@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">Belum ada ucapan.</div>@endforelse</div></div>


