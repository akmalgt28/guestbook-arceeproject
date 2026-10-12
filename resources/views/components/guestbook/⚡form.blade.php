<?php

use App\Models\GuestbookEntry;
use App\Models\Invitation;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component
{
    public string $token = '';
    public string $name = '';
    public string $message = '';
    public string $website = '';
    public int $entryLimit = 10;

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function with(): array
    {
        $invitation = Invitation::query()
            ->forPublicLink($this->token)
            ->where('status', Invitation::STATUS_ACTIVE)
            ->whereHas('event', fn ($query) => $query->published())
            ->firstOrFail();

        $entries = GuestbookEntry::query()
            ->where('event_id', $invitation->event_id)
            ->where('status', GuestbookEntry::STATUS_VISIBLE)
            ->latest('published_at')
            ->limit($this->entryLimit + 1)
            ->get(['name', 'message', 'published_at']);

        return [
            'entries' => $entries->take($this->entryLimit),
            'hasMoreEntries' => $entries->count() > $this->entryLimit,
        ];
    }

    public function loadMore(): void
    {
        $this->entryLimit += 10;
    }

    public function submit(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:255'], 'message' => ['required', 'string', 'max:2000'], 'website' => ['nullable', 'max:0']]);
        $invitation = Invitation::query()->with('recipient')->forPublicLink($this->token)->where('status', Invitation::STATUS_ACTIVE)->whereHas('event', fn ($query) => $query->published())->firstOrFail();
        $key = 'guestbook:'.$invitation->event_id.':'.request()->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Terlalu banyak ucapan. Coba lagi nanti.');
        RateLimiter::hit($key, 3600);
        GuestbookEntry::query()->create(['event_id' => $invitation->event_id, 'invitation_id' => $invitation->id, 'recipient_id' => $invitation->recipient_id, 'name' => $data['name'], 'message' => $data['message'], 'status' => GuestbookEntry::STATUS_VISIBLE, 'published_at' => now()]);
        $this->reset(['name', 'message', 'website']); session()->flash('guestbook_success', 'Ucapan berhasil dikirim.');
    }
};
?>

<div>
    @if (session('guestbook_success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('guestbook_success') }}</div>@endif
    @if ($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <form wire:submit="submit" class="mt-5 space-y-4"><input wire:model="name" class="w-full rounded-xl border-slate-300 px-4 py-3" placeholder="Nama" required><textarea wire:model="message" rows="4" class="w-full rounded-xl border-slate-300 px-4 py-3" placeholder="Tulis ucapan..." required></textarea><input wire:model="website" class="hidden" tabindex="-1" autocomplete="off"><button class="rounded-xl bg-[var(--invitation-primary)] px-5 py-3 font-semibold text-white" type="submit">Kirim ucapan</button></form><div class="mt-8 space-y-3"><h3 class="text-lg font-semibold text-slate-900">Ucapan tamu</h3>@forelse ($entries as $entry)<article class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="font-semibold text-slate-900">{{ $entry->name }}</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $entry->message }}</p><p class="mt-2 text-xs text-slate-400">{{ $entry->published_at?->format('d M Y H:i') }}</p></article>@empty<p class="rounded-2xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">Belum ada ucapan.</p>@endforelse</div>@if ($hasMoreEntries)<button wire:click="loadMore" class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-[var(--invitation-primary)] hover:text-[var(--invitation-primary)]">Muat ucapan lainnya</button>@endif
</div>




