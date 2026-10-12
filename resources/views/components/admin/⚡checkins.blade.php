<?php

use App\Models\AuditLog;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
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

    public string $methodFilter = '';
    public string $statusFilter = '';
    public string $operatorFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $manualGuestName = '';
    public string $manualGuestOrganization = '';
    public string $manualGuestAttendeeCount = '1';

    public function with(): array
    {
        abort_unless(auth()->user()?->can('checkins.view'), 403);

        $checkinsQuery = $this->filteredCheckins();

        return [
            'checkins' => (clone $checkinsQuery)->latest('checked_in_at')->with(['event', 'recipient', 'scanner'])->get(),
            'totalCheckins' => (clone $checkinsQuery)->count(),
            'totalGuests' => (clone $checkinsQuery)->sum('actual_attendee_count'),
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
            'operators' => User::query()->whereIn('id', $this->accessibleCheckins()->select('checked_in_by')->distinct())->orderBy('name')->pluck('name', 'id'),
        ];
    }

    public function addManualGuest(): void
    {
        abort_unless(auth()->user()?->can('checkins.create'), 403);
        $data = $this->validate([
            'eventFilter' => ['required', 'integer'],
            'manualGuestName' => ['required', 'string', 'max:255'],
            'manualGuestOrganization' => ['nullable', 'string', 'max:255'],
            'manualGuestAttendeeCount' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        DB::transaction(function () use ($data): void {
            $event = $this->accessibleEvents()->whereKey($data['eventFilter'])->firstOrFail();
            $recipient = $event->recipients()->create([
                'type' => 'individual',
                'name' => $data['manualGuestName'],
                'organization' => $data['manualGuestOrganization'] ?: null,
                'invited_guest_count' => $data['manualGuestAttendeeCount'],
            ]);
            $checkin = Checkin::query()->create([
                'event_id' => $event->id,
                'recipient_id' => $recipient->id,
                'checked_in_by' => auth()->id(),
                'method' => 'manual',
                'status' => Checkin::STATUS_ACCEPTED,
                'actual_attendee_count' => $data['manualGuestAttendeeCount'],
                'checked_in_at' => now(),
            ]);

            AuditLog::query()->create([
                'actor_id' => auth()->id(),
                'event_id' => $event->id,
                'action' => 'checkin.manual_guest_created',
                'subject_type' => Checkin::class,
                'subject_id' => $checkin->getKey(),
                'new_values' => [
                    'recipient_name' => $recipient->name,
                    'organization' => $recipient->organization,
                    'actual_attendee_count' => $checkin->actual_attendee_count,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        $this->reset(['manualGuestName', 'manualGuestOrganization', 'manualGuestAttendeeCount']);
        $this->manualGuestAttendeeCount = '1';
        session()->flash('success', 'Tamu manual berhasil ditambahkan dan dicatat check-in.');
    }

    private function filteredCheckins(): Builder
    {
        return $this->accessibleCheckins()
            ->when(strlen(trim($this->search)) >= 3, fn (Builder $query): Builder => $query->whereHas('recipient', fn (Builder $query): Builder => $query->where('name', 'ilike', '%' . trim($this->search) . '%')))
            ->when($this->eventFilter !== '', fn (Builder $query): Builder => $query->where('event_id', $this->eventFilter))
            ->when($this->methodFilter !== '', fn (Builder $query): Builder => $query->where('method', $this->methodFilter))
            ->when($this->statusFilter !== '', fn (Builder $query): Builder => $query->where('status', $this->statusFilter))
            ->when($this->operatorFilter !== '', fn (Builder $query): Builder => $query->where('checked_in_by', $this->operatorFilter))
            ->when($this->dateFrom !== '', fn (Builder $query): Builder => $query->whereDate('checked_in_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query): Builder => $query->whereDate('checked_in_at', '<=', $this->dateTo));
    }

    private function accessibleCheckins(): Builder
    {
        return Checkin::query()->whereIn('event_id', $this->accessibleEvents()->select('id'));
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();

        return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
            $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey()));
        }));
    }
};
?>


    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600">Attendance workspace</p>
                <h1 class="mt-2 text-3xl font-semibold text-brand-950">Guestbook</h1>
                <p class="mt-2 text-sm text-slate-500">Catat kedatangan tamu melalui QR atau input manual.</p>
            </div>
        </div>
        @if (session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <section class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="font-semibold text-brand-950">Filter check-in</h2><p class="mt-1 text-sm text-slate-500">Cari riwayat berdasarkan event, metode, status, petugas, atau tanggal.</p></div></div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <input wire:model.live.debounce.300ms="search" class="rounded-xl border-slate-300 px-4 py-3" minlength="3" placeholder="Cari nama (min. 3 huruf)...">
                @if ($eventContext)<div class="flex h-[50px] items-center justify-between rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-slate-700"><span>{{ $events->get($eventFilter, 'Event aktif') }}</span><i data-lucide="lock" class="size-4 text-slate-500"></i></div>@else<select wire:model.live="eventFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua event</option>@foreach ($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select>@endif
                <select wire:model.live="methodFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua metode</option><option value="qr">QR</option><option value="manual">Manual</option></select>
                <select wire:model.live="statusFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua status</option><option value="accepted">Diterima</option><option value="rejected">Ditolak</option></select>
                <select wire:model.live="operatorFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua petugas</option>@foreach ($operators as $id => $operatorName)<option value="{{ $id }}">{{ $operatorName }}</option>@endforeach</select>
                <div class="grid grid-cols-2 gap-2"><input wire:model.live="dateFrom" type="date" class="min-w-0 rounded-xl border-slate-300 px-3 py-3" aria-label="Tanggal mulai"><input wire:model.live="dateTo" type="date" class="min-w-0 rounded-xl border-slate-300 px-3 py-3" aria-label="Tanggal akhir"></div>
            </div>
        </section>
        <div class="grid gap-4 sm:grid-cols-2"><div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5"><p class="text-sm text-emerald-700">Total check-in</p><p class="mt-1 text-2xl font-semibold text-emerald-950">{{ $totalCheckins }}</p></div><div class="rounded-2xl border border-sky-100 bg-sky-50 p-5"><p class="text-sm text-sky-700">Total tamu hadir</p><p class="mt-1 text-2xl font-semibold text-sky-950">{{ $totalGuests }}</p></div></div>
        <section class="rounded-3xl border border-brand-200 bg-brand-50 p-5 sm:p-6">
            <div><h2 class="font-semibold text-brand-950">Tambah tamu manual</h2><p class="mt-1 text-sm text-slate-600">Gunakan saat tamu datang tanpa QR atau belum ada di daftar.</p></div>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div><label class="text-sm font-semibold text-slate-700">Event</label>@if ($eventContext)<div class="mt-2 flex w-full h-[50px] items-center justify-between rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-slate-700"><span>{{ $events->get($eventFilter, 'Event aktif') }}</span><i data-lucide="lock" class="size-4 text-slate-500"></i></div>@else<select wire:model="eventFilter" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"><option value="">Pilih event</option>@foreach ($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select>@endif@error('eventFilter')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Nama tamu</label><input wire:model="manualGuestName" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" placeholder="Nama lengkap">@error('manualGuestName')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Instansi</label><input wire:model="manualGuestOrganization" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" placeholder="Nama instansi (opsional)">@error('manualGuestOrganization')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Jumlah hadir</label><input wire:model="manualGuestAttendeeCount" type="number" min="1" max="999" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" placeholder="1">@error('manualGuestAttendeeCount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            </div>
            <div class="mt-5 flex justify-end"><button wire:click="addManualGuest" class="rounded-xl bg-brand-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-800">Simpan tamu dan check-in</button></div>
        </section>
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-brand-950">Riwayat check-in</h2></div><div class="divide-y divide-slate-100">@forelse ($checkins as $checkin)<div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4"><div><p class="font-semibold text-brand-950">{{ $checkin->recipient->name }}</p><p class="text-sm text-slate-500">{{ $checkin->recipient->organization ?: 'Instansi tidak diisi' }} · {{ $checkin->event->name }} · {{ $checkin->checked_in_at->format('d M Y H:i') }}</p></div><div class="text-right text-sm text-slate-500"><p class="font-semibold uppercase text-slate-700">{{ $checkin->method }}</p><p>{{ $checkin->actual_attendee_count }} orang · {{ $checkin->scanner->name }}</p></div></div>@empty<div class="p-10 text-center text-slate-500">Belum ada check-in.</div>@endforelse</div></section>
    </div>

