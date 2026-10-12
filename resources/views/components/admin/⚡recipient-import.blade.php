<?php

use App\Models\Event;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;
    public string $importEventId = '';
    public bool $eventContext = false;
    public $csvFile;
    public function mount(?int $contextEventId = null): void { if ($contextEventId !== null) { $this->importEventId = (string) $contextEventId; $this->eventContext = true; } }
    public function with(): array { abort_unless(auth()->user()?->can('recipients.create'), 403); return ['events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id')]; }
    public function importCsv(): void
    {
        $data = $this->validate(['importEventId' => ['required', 'integer'], 'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        abort_unless($this->accessibleEvents()->whereKey($data['importEventId'])->exists(), 403);
        $handle = fopen($this->csvFile->getRealPath(), 'rb'); $headers = array_map(fn (string $header): string => strtolower(trim($header)), fgetcsv($handle) ?: []); $required = ['type', 'name', 'invited_guest_count']; abort_if(array_diff($required, $headers), 422, 'Kolom wajib: type, name, invited_guest_count.'); $processed = 0;
        while (($row = fgetcsv($handle)) !== false && $processed < 5000) { if (count(array_filter($row, fn ($value): bool => filled($value))) === 0) continue; $record = array_combine($headers, array_pad($row, count($headers), null)); validator($record, ['type' => ['required', 'in:individual,family_representative'], 'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'invited_guest_count' => ['required', 'integer', 'min:1', 'max:999'], 'notes' => ['nullable', 'string']])->validate(); $query = Recipient::query()->where('event_id', $data['importEventId']); if (filled($record['email'] ?? null)) $query->where('email', $record['email']); elseif (filled($record['phone'] ?? null)) $query->where('phone', $record['phone']); else $query->where('name', $record['name']); $query->firstOrNew()->fill([...$record, 'event_id' => $data['importEventId']])->save(); $processed++; }
        fclose($handle); session()->flash('success', "Import selesai: {$processed} baris diproses."); $this->redirectRoute('admin.recipients');
    }
    private function accessibleEvents(): Builder { $user = auth()->user(); return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())))); }
};
?>
<div class="mx-auto max-w-3xl space-y-8"><div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-semibold text-brand-950">Import recipient</h1></div><a href="{{ route('admin.recipients') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700">Kembali</a></div><form wire:submit="importCsv" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8"><p class="text-sm text-slate-600">Kolom wajib: type, name, invited_guest_count. Duplikat diperbarui berdasarkan email, telepon, atau nama dalam event.</p><div><label class="text-sm font-semibold text-slate-700">Event tujuan</label>@if ($eventContext)<div class="mt-2 flex h-[50px] items-center justify-between rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-slate-700"><span>{{ $events->get($importEventId, 'Event aktif') }}</span><i data-lucide="lock" class="size-4 text-slate-500"></i></div>@else<select wire:model="importEventId" class="mt-2 w-full px-4 py-3"><option value="">Pilih event</option>@foreach($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select>@endif</div><div><label class="text-sm font-semibold text-slate-700">File CSV</label><input wire:model="csvFile" type="file" accept=".csv,.txt" class="mt-2 w-full"></div><div class="flex justify-end gap-3"><a href="{{ route('admin.recipients') }}" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700">Batal</a><button class="rounded-xl bg-brand-950 px-5 py-3 font-semibold text-white">Import CSV</button></div></form></div>