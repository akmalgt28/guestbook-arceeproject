<?php

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\QrCode;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public string $eventFilter = '';
    public string $statusFilter = '';
    public bool $formOpen = false;
    public string $eventId = '';
    public string $invitationId = '';

    public function with(): array
    {
        abort_unless(auth()->user()?->can('qr_codes.view'), 403);
        return [
            'qrCodes' => QrCode::query()->whereHas('invitation', fn (Builder $query): Builder => $query->whereIn('event_id', $this->accessibleEvents()->select('id')))->when($this->eventFilter !== '', fn (Builder $query): Builder => $query->whereHas('invitation', fn (Builder $query): Builder => $query->where('event_id', $this->eventFilter)))->when($this->statusFilter !== '', fn (Builder $query): Builder => $query->where('status', $this->statusFilter))->with('invitation.event', 'invitation.recipient')->latest()->get(),
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
            'invitations' => $this->eventId === '' ? collect() : Invitation::query()->where('event_id', $this->eventId)->with('recipient')->whereDoesntHave('qrCodes')->get(),
        ];
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('qr_codes.generate'), 403); $this->reset(['eventId', 'invitationId']); $this->formOpen = true;
    }

    public function generate(): void
    {
        abort_unless(auth()->user()?->can('qr_codes.generate'), 403);
        $data = $this->validate(['eventId' => ['required', 'integer'], 'invitationId' => ['required', 'integer']]);
        abort_unless($this->accessibleEvents()->whereKey($data['eventId'])->exists(), 403);
        $invitation = Invitation::query()->where('event_id', $data['eventId'])->findOrFail($data['invitationId']);
        $qrCode = QrCode::query()->firstOrCreate(['invitation_id' => $invitation->id], ['token_hash' => QrCode::generateTokenHash(), 'status' => QrCode::STATUS_ACTIVE, 'generated_at' => now()]);
        if ($qrCode->wasRecentlyCreated) {
            $this->auditQr('qr.generated', $qrCode, null, ['status' => $qrCode->status, 'generated_at' => $qrCode->generated_at?->toIso8601String()]);
        }
        $this->formOpen = false; $this->reset(['eventId', 'invitationId']); session()->flash('success', 'QR berhasil dibuat.');
    }

    public function regenerate(int $qrId): void
    {
        abort_unless(auth()->user()?->can('qr_codes.generate'), 403);
        $qrCode = $this->accessibleQrCodes()->findOrFail($qrId);
        $oldValues = ['status' => $qrCode->status, 'generated_at' => $qrCode->generated_at?->toIso8601String()];
        $qrCode->regenerate();
        $this->auditQr('qr.regenerated', $qrCode, $oldValues, ['status' => $qrCode->status, 'generated_at' => $qrCode->generated_at?->toIso8601String()]);
        session()->flash('success', 'QR berhasil dibuat ulang.');
    }

    public function disable(int $qrId): void
    {
        abort_unless(auth()->user()?->can('qr_codes.generate'), 403);
        $qrCode = $this->accessibleQrCodes()->findOrFail($qrId);
        $oldValues = ['status' => $qrCode->status, 'revoked_at' => $qrCode->revoked_at?->toIso8601String()];
        $qrCode->revoke();
        $this->auditQr('qr.disabled', $qrCode, $oldValues, ['status' => $qrCode->status, 'revoked_at' => $qrCode->revoked_at?->toIso8601String()]);
        session()->flash('success', 'QR berhasil dinonaktifkan.');
    }

    /** @param array<string, mixed>|null $oldValues @param array<string, mixed> $newValues */
    private function auditQr(string $action, QrCode $qrCode, ?array $oldValues, array $newValues): void
    {
        AuditLog::query()->create([
            'actor_id' => auth()->id(),
            'event_id' => $qrCode->invitation->event_id,
            'action' => $action,
            'subject_type' => QrCode::class,
            'subject_id' => $qrCode->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function accessibleQrCodes(): Builder
    {
        return QrCode::query()->whereHas('invitation', fn (Builder $query): Builder => $query->whereIn('event_id', $this->accessibleEvents()->select('id')));
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user(); return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void { $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())); }));
    }
};
?>

    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-semibold text-brand-950">QR Codes</h1></div>@if (auth()->user()->can('qr_codes.generate'))<a href="{{ route('admin.qr-codes.create') }}" class="rounded-xl bg-brand-950 px-5 py-3 text-sm font-semibold text-white">Generate QR</a>@endif</div>
        @if (session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <div class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-5 md:grid-cols-2"><select wire:model.live="eventFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua event</option>@foreach ($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select><select wire:model.live="statusFilter" class="rounded-xl border-slate-300 px-4 py-3"><option value="">Semua status</option><option value="active">Active</option><option value="revoked">Revoked</option></select></div>
        <div class="grid gap-4">@forelse ($qrCodes as $qr)<article class="rounded-3xl border border-slate-200 bg-white p-6"><div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-semibold">{{ $qr->invitation->recipient->name }}</h2><p class="mt-2 text-sm text-slate-500">{{ $qr->invitation->event->name }} · dibuat {{ $qr->generated_at?->format('d M Y H:i') }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase">{{ $qr->status }}</span></div><div class="mt-5 flex flex-wrap gap-2">@if (auth()->user()->can('qr_codes.generate'))<button wire:click="regenerate({{ $qr->id }})" wire:confirm="Regenerate QR ini?" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">Regenerate</button>@if ($qr->status === 'active')<button wire:click="disable({{ $qr->id }})" wire:confirm="Disable QR ini?" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Disable</button>@endif@endif <div x-data="{ copied: false }" class="inline-flex"><button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold" x-on:click="navigator.clipboard.writeText(@js(url('/checkin/scan/'.$qr->token_hash))); copied = true; setTimeout(() => copied = false, 1600)"><span x-text="copied ? 'Tersalin' : 'Salin link QR'"></span></button></div></div></article>@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">Belum ada QR code.</div>@endforelse</div>
    </div>


