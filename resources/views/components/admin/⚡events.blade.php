<?php

use App\Models\Event;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public bool $formOpen = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $type = 'other';
    public string $description = '';
    public string $eventDate = '';
    public string $startTime = '';
    public string $endTime = '';
    public string $location = '';
    public string $mapUrl = '';
    public string $contactName = '';
    public string $contactPhone = '';
    public string $status = 'draft';
    public bool $countdownEnabled = true;

    public function with(): array
    {
        abort_unless(auth()->user()?->can('events.view'), 403);
        $events = $this->accessibleEvents()
            ->when($this->search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                $query->where('name', 'ilike', "%{$this->search}%")->orWhere('location', 'ilike', "%{$this->search}%");
            }))
            ->withCount(['recipients', 'invitations'])
            ->latest('event_date')
            ->get();

        return compact('events');
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('events.create'), 403);
        $this->resetForm();
        $this->formOpen = true;
    }

    public function openEdit(int $eventId): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);
        $event = $this->accessibleEvents()->findOrFail($eventId);
        $this->editingId = $event->id;
        $this->name = $event->name;
        $this->type = $event->type;
        $this->description = $event->description ?? '';
        $this->eventDate = $event->event_date?->format('Y-m-d') ?? '';
        $this->startTime = $event->start_time?->format('H:i') ?? '';
        $this->endTime = $event->end_time?->format('H:i') ?? '';
        $this->location = $event->location ?? '';
        $this->mapUrl = $event->map_url ?? '';
        $this->contactName = $event->contact_name ?? '';
        $this->contactPhone = $event->contact_phone ?? '';
        $this->status = $event->status;
        $this->countdownEnabled = (bool) data_get($event->settings, 'countdown_enabled', true);
        $this->formOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can($this->editingId ? 'events.update' : 'events.create'), 403);
        $data = $this->validate();
        $attributes = [
            'name' => $data['name'], 'type' => $data['type'], 'description' => $data['description'] ?: null,
            'event_date' => $data['eventDate'], 'start_time' => $data['startTime'] ?: null, 'end_time' => $data['endTime'] ?: null,
            'location' => $data['location'] ?: null, 'map_url' => $data['mapUrl'] ?: null, 'contact_name' => $data['contactName'] ?: null,
            'contact_phone' => $data['contactPhone'] ?: null, 'status' => $data['status'], 'settings' => ['countdown_enabled' => $data['countdownEnabled']],
        ];
        if ($this->editingId) {
            $this->accessibleEvents()->findOrFail($this->editingId)->update($attributes);
        } else {
            Event::query()->create([...$attributes, 'created_by' => auth()->id()]);
        }
        $this->formOpen = false;
        $this->resetForm();
        session()->flash('success', 'Event berhasil disimpan.');
    }

    public function togglePublish(int $eventId): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);
        $event = $this->accessibleEvents()->findOrFail($eventId);
        $event->update(['status' => $event->status === Event::STATUS_PUBLISHED ? Event::STATUS_DRAFT : Event::STATUS_PUBLISHED]);
    }

    public function archive(int $eventId): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);
        $this->accessibleEvents()->findOrFail($eventId)->update(['status' => Event::STATUS_ARCHIVED]);
    }

    public function delete(int $eventId): void
    {
        abort_unless(auth()->user()?->can('events.delete'), 403);
        $this->accessibleEvents()->findOrFail($eventId)->delete();
        session()->flash('success', 'Event berhasil dihapus.');
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();
        return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
            $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey()));
        }));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'eventDate', 'startTime', 'endTime', 'location', 'mapUrl', 'contactName', 'contactPhone']);
        $this->type = 'other'; $this->status = 'draft'; $this->countdownEnabled = true; $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:wedding,birthday,corporate,graduation,religious,other'],
            'description' => ['nullable', 'string', 'max:5000'], 'eventDate' => ['required', 'date'], 'startTime' => ['nullable', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i', 'after_or_equal:startTime'], 'location' => ['nullable', 'string', 'max:255'],
            'mapUrl' => ['nullable', 'url', 'max:255'], 'contactName' => ['nullable', 'string', 'max:255'], 'contactPhone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:draft,published,archived'], 'countdownEnabled' => ['boolean'],
        ];
    }
};
?><div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-3xl font-semibold text-brand-950">Events</h1></div>
        @if (auth()->user()->can('events.create'))<a href="{{ route('admin.events.create') }}" class="rounded-xl bg-brand-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-800">Tambah event</a>@endif
    </div>
    @if (session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    <div class="rounded-3xl border border-slate-200 bg-white p-5"><label for="event-search" class="sr-only">Cari event</label><input id="event-search" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500" placeholder="Cari nama atau lokasi event..."></div>
    <div class="grid gap-4">@forelse ($events as $event)<article class="rounded-3xl border border-slate-200 bg-white p-6"><div class="flex flex-wrap items-start justify-between gap-4"><div><div class="flex flex-wrap items-center gap-3"><h2 class="text-xl font-semibold text-brand-950">{{ $event->name }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $event->status }}</span></div><p class="mt-2 text-sm text-slate-500">{{ $event->event_date?->format('d M Y') }} · {{ $event->location ?: 'Lokasi belum diisi' }}</p></div><div class="flex flex-wrap gap-2">@if (auth()->user()->can('events.view'))<a href="{{ route('admin.events.overview', $event) }}" class="rounded-lg bg-brand-950 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-800">Buka event</a>@endif @if (auth()->user()->can('events.update'))<a href="{{ route('invitations.preview', data_get($event->settings, 'invitation.custom_link', Str::slug($event->name))) }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-brand-300 hover:text-brand-700">Preview</a><button wire:click="togglePublish({{ $event->id }})" class="rounded-lg {{ $event->status === Event::STATUS_PUBLISHED ? 'border border-amber-200 text-amber-700 hover:bg-amber-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} px-3 py-2 text-sm font-semibold">{{ $event->status === Event::STATUS_PUBLISHED ? 'Unpublish' : 'Publish' }}</button>@endif @if (auth()->user()->can('events.delete'))<button wire:click="delete({{ $event->id }})" wire:confirm="Hapus event ini?" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Hapus</button>@endif</div></div><div class="mt-5 flex gap-5 text-sm text-slate-500"><span>{{ $event->recipients_count }} penerima</span><span>{{ $event->invitations_count }} invitation</span></div></article>@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">Belum ada event.</div>@endforelse</div>
</div>