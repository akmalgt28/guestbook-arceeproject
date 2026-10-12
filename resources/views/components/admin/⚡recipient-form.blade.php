<?php

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public ?int $recipientId = null;
    public string $eventId = '';
    public string $type = Recipient::TYPE_INDIVIDUAL;
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $invitedGuestCount = '1';
    public string $notes = '';
    public string $customLink = '';

    public function mount(?int $recipientId = null): void
    {
        $this->recipientId = $recipientId;
        abort_unless(auth()->user()?->can($recipientId ? 'recipients.update' : 'recipients.create'), 403);
        if ($recipientId) {
            $recipient = $this->accessibleRecipients()->findOrFail($recipientId);
            $this->eventId = (string) $recipient->event_id;
            $this->type = $recipient->type;
            $this->name = $recipient->name;
            $this->phone = $recipient->phone ?? '';
            $this->email = $recipient->email ?? '';
            $this->invitedGuestCount = (string) $recipient->invited_guest_count;
            $this->notes = $recipient->notes ?? '';
            $this->customLink = (string) ($recipient->invitations()->first()?->custom_link ?? Invitation::generateCustomLink());
        } else {
            $this->customLink = Invitation::generateCustomLink();
        }
    }

    public function with(): array
    {
        return ['events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id')];
    }

    public function save(): void
    {
        $this->customLink = strtolower(trim($this->customLink));
        $existingInvitation = $this->recipientId ? $this->accessibleRecipients()->findOrFail($this->recipientId)->invitations()->first() : null;
        $data = $this->validate(['eventId' => ['required', 'integer'], 'type' => ['required', 'in:individual,family_representative'], 'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'invitedGuestCount' => ['required', 'integer', 'min:1', 'max:999'], 'notes' => ['nullable', 'string', 'max:5000'], 'customLink' => ['required', 'string', 'min:4', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(['admin', 'login', 'invite', 'scanner', 'api']), Rule::unique('invitations', 'custom_link')->ignore($existingInvitation?->id)]]);
        abort_unless($this->accessibleEvents()->whereKey($data['eventId'])->exists(), 403);
        $attributes = ['event_id' => $data['eventId'], 'type' => $data['type'], 'name' => $data['name'], 'phone' => $data['phone'] ?: null, 'email' => $data['email'] ?: null, 'invited_guest_count' => $data['invitedGuestCount'], 'notes' => $data['notes'] ?: null];
        $recipient = $this->recipientId ? $this->accessibleRecipients()->findOrFail($this->recipientId) : Recipient::query()->create($attributes);
        if ($this->recipientId) {
            $recipient->update($attributes);
        }
        $invitation = $recipient->invitations()->first();
        if ($invitation) {
            $invitation->update(['event_id' => $data['eventId'], 'custom_link' => $data['customLink']]);
        } else {
            Invitation::createWithToken(['event_id' => $data['eventId'], 'recipient_id' => $recipient->getKey(), 'status' => Invitation::STATUS_ACTIVE, 'custom_link' => $data['customLink']]);
        }
        session()->flash('success', 'Recipient berhasil disimpan.');
        $this->redirectRoute('admin.recipients');
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();
        return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey()))));
    }

    private function accessibleRecipients(): Builder
    {
        return Recipient::query()->whereIn('event_id', $this->accessibleEvents()->select('id'));
    }
};
?>
<div class="mx-auto max-w-4xl space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-semibold text-brand-950">{{ $recipientId ? 'Edit recipient' : 'Tambah recipient' }}</h1></div><a href="{{ route('admin.recipients') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700">Kembali</a></div>
    <form wire:submit="save" class="grid gap-5 rounded-3xl border border-slate-200 bg-white p-6 sm:grid-cols-2 sm:p-8">
        <div><label class="text-sm font-semibold text-slate-700">Event</label><select wire:model="eventId" class="mt-2 w-full px-4 py-3"><option value="">Pilih event</option>@foreach($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select>@error('eventId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="text-sm font-semibold text-slate-700">Tipe</label><select wire:model="type" class="mt-2 w-full px-4 py-3"><option value="individual">Individual</option><option value="family_representative">Perwakilan keluarga</option></select></div>
        <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Nama</label><input wire:model="name" class="mt-2 w-full px-4 py-3">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="text-sm font-semibold text-slate-700">Telepon</label><input wire:model="phone" class="mt-2 w-full px-4 py-3"></div><div><label class="text-sm font-semibold text-slate-700">Email</label><input wire:model="email" type="email" class="mt-2 w-full px-4 py-3"></div>
        <div><label class="text-sm font-semibold text-slate-700">Jumlah tamu</label><input wire:model="invitedGuestCount" type="number" min="1" class="mt-2 w-full px-4 py-3"></div><div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Custom link undangan</label><input wire:model="customLink" class="mt-2 w-full px-4 py-3" placeholder="contoh: keluarga-akmal">@error('customLink')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror@if($customLink)<div class="mt-2 flex items-center gap-2 text-sm text-slate-500"><span class="truncate">{{ url('/invite/'.$customLink) }}</span><button type="button" x-data x-on:click="navigator.clipboard.writeText(@js(url('/invite/'.$customLink)))" class="rounded-lg border border-slate-200 px-2 py-1 font-semibold text-slate-700">Salin</button></div>@endif</div><div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Catatan</label><textarea wire:model="notes" rows="4" class="mt-2 w-full px-4 py-3"></textarea></div>
        <div class="flex justify-end gap-3 sm:col-span-2"><a href="{{ route('admin.recipients') }}" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700">Batal</a><button class="rounded-xl bg-brand-950 px-5 py-3 font-semibold text-white">Simpan recipient</button></div>
    </form>
</div>