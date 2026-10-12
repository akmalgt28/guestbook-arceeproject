<?php

use App\Models\Event;
use App\Models\Recipient;
use App\Models\QrCode;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

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

    public ?int $selectedQrRecipientId = null;
    public ?string $selectedQrToken = null;
    public ?string $selectedQrRecipientName = null;
    public bool $formOpen = false;
    public bool $importOpen = false;
    public ?int $editingId = null;
    public string $eventId = '';
    public string $type = Recipient::TYPE_INDIVIDUAL;
    public string $name = '';
    public string $organization = '';
    public string $phone = '';
    public string $email = '';
    public string $invitedGuestCount = '1';
    public string $notes = '';
    public string $importEventId = '';
    public $csvFile;

    public function with(): array
    {
        abort_unless(auth()->user()?->can('recipients.view'), 403);
        $recipients = $this->accessibleRecipients()
            ->when($this->search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                $query->where('name', 'ilike', "%{$this->search}%")->orWhere('organization', 'ilike', "%{$this->search}%")->orWhere('email', 'ilike', "%{$this->search}%");
            }))
            ->when($this->eventFilter !== '', fn (Builder $query): Builder => $query->where('event_id', $this->eventFilter))
            ->select(['id', 'event_id', 'type', 'name', 'organization', 'phone', 'email', 'invited_guest_count'])
            ->with('event:id,name')
            ->latest()
            ->get();

        return [
            'recipients' => $recipients,
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
        ];
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('recipients.create'), 403);
        $this->resetForm();
        $this->formOpen = true;
    }

    public function openEdit(int $recipientId): void
    {
        abort_unless(auth()->user()?->can('recipients.update'), 403);
        $recipient = $this->accessibleRecipients()->findOrFail($recipientId);
        $this->editingId = $recipient->id;
        $this->eventId = (string) $recipient->event_id;
        $this->type = $recipient->type;
        $this->name = $recipient->name;
        $this->phone = $recipient->phone ?? '';
        $this->email = $recipient->email ?? '';
        $this->invitedGuestCount = (string) $recipient->invited_guest_count;
        $this->organization = $recipient->organization ?? '';
        $this->notes = $recipient->notes ?? '';
        $this->formOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can($this->editingId ? 'recipients.update' : 'recipients.create'), 403);
        $data = $this->validate($this->recipientRules());
        abort_unless($this->accessibleEvents()->whereKey($data['eventId'])->exists(), 403);
        $attributes = ['event_id' => $data['eventId'], 'type' => $data['type'], 'name' => $data['name'], 'organization' => $data['organization'] ?: null, 'phone' => $data['phone'] ?: null, 'email' => $data['email'] ?: null, 'invited_guest_count' => $data['invitedGuestCount'], 'notes' => $data['notes'] ?: null];
        if ($this->editingId) $this->accessibleRecipients()->findOrFail($this->editingId)->update($attributes); else Recipient::query()->create($attributes);
        $this->formOpen = false; $this->resetForm(); session()->flash('success', 'Recipient berhasil disimpan.');
    }


    public function showQr(int $recipientId): void
    {
        abort_unless(auth()->user()?->can('checkins.create'), 403);
        $recipient = $this->accessibleRecipients()->select(['id', 'name'])->findOrFail($recipientId);
        $qrCode = QrCode::query()->firstOrCreate(['recipient_id' => $recipient->id], [
            'token_hash' => QrCode::generateTokenHash(),
            'status' => QrCode::STATUS_ACTIVE,
            'generated_at' => now(),
        ]);
        $this->selectedQrRecipientId = $recipient->id;
        $this->selectedQrRecipientName = $recipient->name;
        $this->selectedQrToken = $qrCode->token_hash;
    }

    public function closeQr(): void
    {
        $this->reset(['selectedQrRecipientId', 'selectedQrRecipientName', 'selectedQrToken']);
    }

    public function deactivate(int $recipientId): void
    {
        abort_unless(auth()->user()?->can('recipients.delete'), 403);
        $this->accessibleRecipients()->findOrFail($recipientId)->delete();
        session()->flash('success', 'Recipient berhasil dinonaktifkan.');
    }

    public function importCsv(): void
    {
        abort_unless(auth()->user()?->can('recipients.create'), 403);
        $data = $this->validate(['importEventId' => ['required', 'integer'], 'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        abort_unless($this->accessibleEvents()->whereKey($data['importEventId'])->exists(), 403);
        $handle = fopen($this->csvFile->getRealPath(), 'rb');
        $headers = array_map(fn (string $header): string => strtolower(trim($header)), fgetcsv($handle) ?: []);
        $required = ['type', 'name', 'invited_guest_count'];
        abort_if(array_diff($required, $headers), 422, 'Kolom wajib: type, name, invited_guest_count.');
        $processed = 0;
        while (($row = fgetcsv($handle)) !== false && $processed < 5000) {
            if (count(array_filter($row, fn ($value): bool => filled($value))) === 0) continue;
            $values = array_pad($row, count($headers), null);
            $record = array_combine($headers, $values);
            validator($record, ['type' => ['required', 'in:individual,family_representative'], 'name' => ['required', 'string', 'max:255'], 'organization' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'invited_guest_count' => ['required', 'integer', 'min:1', 'max:999'], 'notes' => ['nullable', 'string']])->validate();
            $query = Recipient::query()->where('event_id', $data['importEventId']);
            if (filled($record['email'] ?? null)) $query->where('email', $record['email']); elseif (filled($record['phone'] ?? null)) $query->where('phone', $record['phone']); else $query->where('name', $record['name']);
            $query->firstOrNew()->fill([...$record, 'event_id' => $data['importEventId']])->save();
            $processed++;
        }
        fclose($handle); $this->importOpen = false; $this->reset(['csvFile', 'importEventId']); session()->flash('success', "Import selesai: {$processed} baris diproses.");
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();
        return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void { $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())); }));
    }

    private function accessibleRecipients(): Builder
    {
        return Recipient::query()->whereIn('event_id', $this->accessibleEvents()->select('id'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'eventId', 'name', 'organization', 'phone', 'email', 'notes']); $this->type = Recipient::TYPE_INDIVIDUAL; $this->invitedGuestCount = '1'; $this->resetValidation();
    }

    private function recipientRules(): array
    {
        return ['eventId' => ['required', 'integer'], 'type' => ['required', 'in:individual,family_representative'], 'name' => ['required', 'string', 'max:255'], 'organization' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'invitedGuestCount' => ['required', 'integer', 'min:1', 'max:999'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
};
?>

    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-semibold text-brand-950">Recipients</h1></div><div class="flex gap-2">@if (auth()->user()->can('recipients.create'))<a href="{{ ($eventContext ? route('admin.events.recipients.import', $eventFilter) : route('admin.recipients.import')) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold">Import CSV</a><a href="{{ $eventContext ? route('admin.events.recipients.create', $eventFilter) : route('admin.recipients.create') }}" class="rounded-xl bg-brand-950 px-4 py-3 text-sm font-semibold text-white">Tambah recipient</a>@endif</div></div>
        @if (session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <div class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-5 md:grid-cols-3"><input wire:model.live.debounce.300ms="search" class="rounded-xl border-slate-300 px-4 py-3" placeholder="Cari nama, instansi, telepon, email...">@if ($eventContext)<div class="flex h-[50px] items-center justify-between rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-slate-700"><span>{{ $events->get($eventFilter, 'Event aktif') }}</span><i data-lucide="lock" class="size-4 text-slate-500"></i></div>@else<select wire:model.live="eventFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua event</option>@foreach ($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select>@endif</div>

        @if ($selectedQrToken)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4" wire:click.self="closeQr" role="dialog" aria-modal="true" aria-labelledby="qr-modal-title">
                <section class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="qr-modal-title" class="text-lg font-semibold text-brand-950">QR tamu</h2>
                            <p class="mt-1 text-sm text-slate-500">Tunjukkan QR ini saat check-in.</p>
                        </div>
                        <button type="button" wire:click="closeQr" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">Tutup</button>
                    </div>
                    <div class="mt-6 flex justify-center rounded-2xl bg-slate-50 p-5">
                        {!! QrCodeGenerator::format('svg')->size(240)->margin(1)->generate($selectedQrToken) !!}
                    </div>
                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-center">
                        <p class="text-lg font-semibold text-brand-950">{{ $selectedQrRecipientName }}</p>
                        <p class="mt-1 text-sm text-slate-500">QR terhubung langsung ke data recipient.</p>
                    </div>
                </section>
            </div>
        @endif
        <div class="grid gap-4">@forelse ($recipients as $recipient)<article class="rounded-3xl border border-slate-200 bg-white p-6"><div class="flex flex-wrap items-start justify-between gap-4"><div><div class="flex flex-wrap items-center gap-3"><h2 class="text-xl font-semibold text-brand-950">{{ $recipient->name }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $recipient->type === 'family_representative' ? 'Perwakilan keluarga' : 'Individual' }}</span></div><p class="mt-2 text-sm text-slate-500">{{ $recipient->event->name }} · {{ $recipient->organization ?: ($recipient->phone ?: ($recipient->email ?: 'Kontak belum diisi')) }}</p></div><div class="flex gap-2">@if (auth()->user()->can('checkins.create'))<button wire:click="showQr({{ $recipient->id }})" wire:loading.attr="disabled" wire:target="showQr({{ $recipient->id }})" class="rounded-lg border border-brand-200 px-3 py-2 text-sm font-semibold text-brand-700 disabled:cursor-wait disabled:opacity-60">Tampilkan QR</button>@endif @if (auth()->user()->can('recipients.update'))<a href="{{ ($eventContext ? route('admin.events.recipients.edit', [$eventFilter, $recipient]) : route('admin.recipients.edit', $recipient)) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">Edit</a>@endif @if (auth()->user()->can('recipients.delete'))<button wire:click="deactivate({{ $recipient->id }})" wire:confirm="Nonaktifkan recipient ini?" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Nonaktifkan</button>@endif</div></div><div class="mt-5 flex flex-wrap gap-5 text-sm text-slate-500"><span>Tamu: {{ $recipient->invited_guest_count }}</span></div></article>@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">Belum ada recipient.</div>@endforelse</div>
    </div>
