<?php

use App\Models\AuditLog;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $eventFilter = '';
    public string $methodFilter = '';
    public string $statusFilter = '';
    public string $operatorFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $actualAttendeeCount = '1';
    public string $notes = '';
    public ?int $selectedInvitationId = null;

    public function with(): array
    {
        abort_unless(auth()->user()?->can('checkins.view'), 403);

        $checkinsQuery = $this->filteredCheckins();

        return [
            'checkins' => (clone $checkinsQuery)->latest('checked_in_at')->with(['event', 'recipient', 'scanner'])->get(),
            'totalCheckins' => (clone $checkinsQuery)->count(),
            'totalGuests' => (clone $checkinsQuery)->sum('actual_attendee_count'),
            'candidates' => $this->searchCandidates(),
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
            'operators' => User::query()->whereIn('id', $this->accessibleCheckins()->select('checked_in_by')->distinct())->orderBy('name')->pluck('name', 'id'),
        ];
    }

    public function selectCandidate(int $invitationId): void
    {
        abort_unless(auth()->user()?->can('checkins.create'), 403);
        $invitation = $this->accessibleInvitations()->with('rsvp')->findOrFail($invitationId);
        abort_if($invitation->checkins()->where('status', Checkin::STATUS_ACCEPTED)->exists(), 422, 'Tamu sudah check-in.');
        $this->selectedInvitationId = $invitation->id;
        $this->actualAttendeeCount = (string) max(1, $invitation->rsvp?->attending_count ?? 1);
    }

    public function manualCheckin(): void
    {
        abort_unless(auth()->user()?->can('checkins.create'), 403);
        $data = $this->validate([
            'selectedInvitationId' => ['required', 'integer'],
            'actualAttendeeCount' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data): void {
            $invitation = $this->accessibleInvitations()->whereKey($data['selectedInvitationId'])->lockForUpdate()->firstOrFail();
            if ($invitation->checkins()->where('status', Checkin::STATUS_ACCEPTED)->exists()) {
                abort(422, 'Tamu sudah check-in.');
            }
            $checkin = Checkin::query()->create([
                'event_id' => $invitation->event_id,
                'invitation_id' => $invitation->id,
                'recipient_id' => $invitation->recipient_id,
                'checked_in_by' => auth()->id(),
                'method' => 'manual',
                'status' => Checkin::STATUS_ACCEPTED,
                'actual_attendee_count' => $data['actualAttendeeCount'],
                'checked_in_at' => now(),
                'notes' => $data['notes'] ?: null,
            ]);

            AuditLog::query()->create([
                'actor_id' => auth()->id(),
                'event_id' => $invitation->event_id,
                'action' => 'checkin.created',
                'subject_type' => Checkin::class,
                'subject_id' => $checkin->getKey(),
                'new_values' => [
                    'method' => $checkin->method,
                    'actual_attendee_count' => $checkin->actual_attendee_count,
                    'notes' => $checkin->notes,
                    'checked_in_at' => $checkin->checked_in_at->toIso8601String(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        $this->reset(['selectedInvitationId', 'notes']);
        session()->flash('success', 'Check-in manual berhasil.');
    }

    private function searchCandidates(): \Illuminate\Support\Collection
    {
        if ($this->search === '' || $this->eventFilter === '') {
            return collect();
        }

        return $this->accessibleInvitations()
            ->where('event_id', $this->eventFilter)
            ->whereHas('recipient', function (Builder $query): void {
                $term = "%{$this->search}%";
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('LOWER(name) LIKE LOWER(?)', [$term])
                        ->orWhereRaw('LOWER(phone) LIKE LOWER(?)', [$term])
                        ->orWhereRaw('LOWER(email) LIKE LOWER(?)', [$term]);
                });
            })
            ->with(['recipient', 'checkins' => fn ($query) => $query->where('status', Checkin::STATUS_ACCEPTED)])
            ->limit(10)->get();
    }

    private function filteredCheckins(): Builder
    {
        return $this->accessibleCheckins()
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

    private function accessibleInvitations(): Builder
    {
        return Invitation::query()->whereIn('event_id', $this->accessibleEvents()->select('id'));
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
        <div><h1 class="text-3xl font-semibold text-brand-950">Check-in</h1></div>
        @if (session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <div class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-5 md:grid-cols-3 lg:grid-cols-6"><input wire:model.live.debounce.300ms="search" class="rounded-xl border-slate-300 px-4 py-3" placeholder="Cari nama, nomor, email..."><select wire:model.live="eventFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua event</option>@foreach ($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select><select wire:model.live="methodFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua metode</option><option value="qr">QR</option><option value="manual">Manual</option></select><select wire:model.live="statusFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua status</option><option value="accepted">Diterima</option><option value="rejected">Ditolak</option></select><select wire:model.live="operatorFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua petugas</option>@foreach ($operators as $id => $operatorName)<option value="{{ $id }}">{{ $operatorName }}</option>@endforeach</select><div class="flex gap-2"><input wire:model.live="dateFrom" type="date" class="min-w-0 w-full rounded-xl border-slate-300 px-3 py-3" aria-label="Tanggal mulai"><input wire:model.live="dateTo" type="date" class="min-w-0 w-full rounded-xl border-slate-300 px-3 py-3" aria-label="Tanggal akhir"></div></div><div class="grid gap-3 sm:grid-cols-2"><div class="rounded-2xl bg-emerald-50 p-4"><p class="text-sm text-emerald-700">Total check-in</p><p class="mt-1 text-2xl font-semibold text-emerald-950">{{ $totalCheckins }}</p></div><div class="rounded-2xl bg-sky-50 p-4"><p class="text-sm text-sky-700">Total tamu hadir</p><p class="mt-1 text-2xl font-semibold text-sky-950">{{ $totalGuests }}</p></div></div>
        @if ($candidates->isNotEmpty())<section class="rounded-3xl border border-amber-200 bg-brand-50 p-5"><h2 class="font-semibold text-brand-950">Kandidat tamu</h2><div class="mt-4 grid gap-3">@foreach ($candidates as $invitation)<div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white p-4"><div><p class="font-semibold">{{ $invitation->recipient->name }}</p><p class="text-sm text-slate-500">{{ $invitation->recipient->phone ?: $invitation->recipient->email ?: 'Kontak tidak tersedia' }}</p></div>@if ($invitation->checkins->isNotEmpty())<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">Sudah check-in</span>@else<button wire:click="selectCandidate({{ $invitation->id }})" class="rounded-xl bg-brand-950 px-4 py-2 text-sm font-semibold text-white">Pilih</button>@endif</div>@endforeach</div></section>@endif
        @if ($selectedInvitationId)<section class="rounded-3xl border border-slate-200 bg-white p-5"><h2 class="font-semibold text-brand-950">Konfirmasi check-in manual</h2><div class="mt-4 grid gap-3 md:grid-cols-[160px_1fr_auto]"><input wire:model="actualAttendeeCount" type="number" min="1" max="999" class="rounded-xl border-slate-300 px-4 py-3" placeholder="Jumlah hadir"><input wire:model="notes" class="rounded-xl border-slate-300 px-4 py-3" placeholder="Catatan manual (opsional)"><button wire:click="manualCheckin" class="rounded-xl bg-brand-950 px-5 py-3 font-semibold text-white">Simpan check-in</button></div>@error('actualAttendeeCount')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror</section>@endif
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-brand-950">Riwayat check-in</h2></div><div class="divide-y divide-slate-100">@forelse ($checkins as $checkin)<div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4"><div><p class="font-semibold text-brand-950">{{ $checkin->recipient->name }}</p><p class="text-sm text-slate-500">{{ $checkin->event->name }} · {{ $checkin->checked_in_at->format('d M Y H:i') }}</p></div><div class="text-right text-sm text-slate-500"><p class="font-semibold uppercase text-slate-700">{{ $checkin->method }}</p><p>{{ $checkin->actual_attendee_count }} orang · {{ $checkin->scanner->name }}</p></div></div>@empty<div class="p-10 text-center text-slate-500">Belum ada check-in.</div>@endforelse</div></section>
    </div>

