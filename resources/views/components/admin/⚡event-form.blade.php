<?php

use App\Models\Event;
use App\Models\InvitationTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public ?int $eventId = null;
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
    public string $status = Event::STATUS_DRAFT;
    public bool $countdownEnabled = true;

    public string $templateId = '';

    public string $invitationCategory = 'general';

    public string $invitationLink = '';

    public function mount(?int $eventId = null): void
    {
        $this->eventId = $eventId;

        if ($eventId === null) {
            abort_unless(auth()->user()?->can('events.create'), 403);

            return;
        }

        abort_unless(auth()->user()?->can('events.update'), 403);
        $event = $this->accessibleEvents()->findOrFail($eventId);
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
        $this->templateId = (string) ($event->templates()->value('invitation_templates.id') ?? '');
        $this->invitationCategory = (string) data_get($event->settings, 'invitation.category', 'general');
        $this->invitationLink = (string) data_get($event->settings, 'invitation.custom_link', Str::slug($event->name));
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'templates' => InvitationTemplate::query()->active()->orderBy('name')->pluck('name', 'id'),
        ];
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can($this->eventId ? 'events.update' : 'events.create'), 403);

        $data = $this->validate();
        $attributes = [
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?: null,
            'event_date' => $data['eventDate'],
            'start_time' => $data['startTime'] ?: null,
            'end_time' => $data['endTime'] ?: null,
            'location' => $data['location'] ?: null,
            'map_url' => $data['mapUrl'] ?: null,
            'contact_name' => $data['contactName'] ?: null,
            'contact_phone' => $data['contactPhone'] ?: null,
            'status' => $data['status'],
            'settings' => [
                ...($this->eventId ? ($this->accessibleEvents()->findOrFail($this->eventId)->settings ?? []) : []),
                'countdown_enabled' => $data['countdownEnabled'],
                'invitation' => [
                    'category' => $data['invitationCategory'],
                    'custom_link' => $data['invitationLink'],
                    'configured' => true,
                ],
            ],
        ];

        if ($this->eventId) {
            $event = $this->accessibleEvents()->findOrFail($this->eventId);
            $event->update($attributes);
        } else {
            $event = Event::query()->create([...$attributes, 'created_by' => auth()->id()]);
        }

        if ($data['templateId'] !== '') {
            $event->templates()->sync([$data['templateId']]);
        } else {
            $event->templates()->detach();
        }

        session()->flash('success', $this->eventId ? 'Event berhasil diperbarui.' : 'Event berhasil dibuat.');
        $this->redirectRoute('admin.events');
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();

        return Event::query()->when(
            ! $user->hasRole('Super Admin'),
            fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->getKey())
                    ->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey()));
            }),
        );
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:wedding,birthday,corporate,graduation,religious,other'],
            'description' => ['nullable', 'string', 'max:5000'],
            'eventDate' => ['required', 'date'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i', 'after_or_equal:startTime'],
            'location' => ['nullable', 'string', 'max:255'],
            'mapUrl' => ['nullable', 'url', 'max:255'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:draft,published,archived'],
            'countdownEnabled' => ['boolean'],
            'templateId' => ['nullable', 'integer', 'exists:invitation_templates,id'],
            'invitationCategory' => ['required', 'in:general,wedding,birthday,corporate,other'],
            'invitationLink' => ['required', 'string', 'min:4', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }
};
?>

<div class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-brand-950">{{ $eventId ? 'Edit event' : 'Tambah event' }}</h1>
        </div>
        <a href="{{ route('admin.events') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:text-brand-700">Kembali ke events</a>
    </div>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <form wire:submit="save" class="space-y-8 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
        <section class="space-y-5">
            <div><h2 class="text-lg font-semibold text-brand-950">Informasi utama</h2><p class="mt-1 text-sm text-slate-500">Data dasar yang tampil di workspace dan undangan.</p></div>
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2"><label for="event-name" class="text-sm font-semibold text-slate-700">Nama event</label><input id="event-name" wire:model="name" type="text" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500" placeholder="Contoh: Annual Gathering 2026">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div x-data="{ open: false, selected: @entangle('type'), options: { other: 'Lainnya', wedding: 'Pernikahan', birthday: 'Ulang tahun', corporate: 'Corporate', graduation: 'Wisuda', religious: 'Keagamaan' } }"><label for="event-type" class="text-sm font-semibold text-slate-700">Jenis event</label><div class="relative mt-2"><input id="event-type" type="hidden" wire:model="type"><button type="button" x-on:click="open = !open" x-on:click.outside="open = false" class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-slate-900"><span x-text="options[selected]"></span><i data-lucide="chevron-down" class="size-4 text-brand-700"></i></button><div x-show="open" x-transition class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-1"><template x-for="(label, value) in options" :key="value"><button type="button" x-on:click="selected = value; open = false" x-text="label" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm text-slate-700 hover:bg-brand-50 hover:text-brand-800"></button></template></div></div>@error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div x-data="{ open: false, selected: @entangle('status'), options: { draft: 'Draft', published: 'Published', archived: 'Archived' } }"><label for="event-status" class="text-sm font-semibold text-slate-700">Status</label><div class="relative mt-2"><input id="event-status" type="hidden" wire:model="status"><button type="button" x-on:click="open = !open" x-on:click.outside="open = false" class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-slate-900"><span x-text="options[selected]"></span><i data-lucide="chevron-down" class="size-4 text-brand-700"></i></button><div x-show="open" x-transition class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-1"><template x-for="(label, value) in options" :key="value"><button type="button" x-on:click="selected = value; open = false" x-text="label" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm text-slate-700 hover:bg-brand-50 hover:text-brand-800"></button></template></div></div>@error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><label for="event-description" class="text-sm font-semibold text-slate-700">Deskripsi</label><textarea id="event-description" wire:model="description" rows="5" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500" placeholder="Jelaskan konteks event secara singkat."></textarea>@error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section class="space-y-5 border-t border-slate-100 pt-8">
            <div><h2 class="text-lg font-semibold text-brand-950">Waktu dan lokasi</h2><p class="mt-1 text-sm text-slate-500">Informasi ini dipakai untuk halaman undangan.</p></div>
            <div class="grid gap-5 md:grid-cols-2">
<div x-data="guestbookDatePicker(@entangle('eventDate'))"><label for="event-date" class="text-sm font-semibold text-slate-700">Tanggal</label><div class="relative mt-2"><button id="event-date" type="button" x-on:click="open = !open" x-on:click.outside="open = false" class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-slate-900"><span x-text="value || 'Pilih tanggal'"></span><i data-lucide="calendar-days" class="size-4 text-brand-700"></i></button><input type="hidden" wire:model="eventDate"><div x-show="open" x-transition class="absolute left-0 z-30 mt-2 h-80 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-4"><div class="flex items-center justify-between"><button type="button" x-on:click="previous" class="rounded-lg px-3 py-1 text-xl text-brand-700 hover:bg-brand-50">‹</button><span class="text-sm font-semibold capitalize text-brand-950" x-text="monthLabel"></span><button type="button" x-on:click="next" class="rounded-lg px-3 py-1 text-xl text-brand-700 hover:bg-brand-50">›</button></div><div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-slate-400"><template x-for="label in ['Mg','Sn','Sl','Rb','Km','Jm','Sb']"><span x-text="label"></span></template></div><div class="mt-2 grid grid-cols-7 gap-1 text-center"><template x-for="(day, index) in days" :key="index"><span><template x-if="day"><button type="button" x-on:click="select(day.value)" x-text="day.day" class="grid size-8 place-items-center rounded-lg text-sm hover:bg-brand-50" :class="{ 'bg-brand-700 text-white': value === day.value }"></button></template></span></template></div></div></div>@error('eventDate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
<div class="grid grid-cols-2 gap-4"><div x-data="guestbookTimePicker(@entangle('startTime'))"><label for="event-start" class="text-sm font-semibold text-slate-700">Mulai</label><div class="relative mt-2"><button id="event-start" type="button" x-on:click="open = !open" x-on:click.outside="open = false" class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-slate-900"><span x-text="value || 'Pilih waktu'"></span><i data-lucide="clock" class="size-4 text-brand-700"></i></button><input type="hidden" wire:model="startTime"><div x-show="open" x-transition class="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white p-2"><div class="grid grid-cols-2 gap-2 border-b border-slate-100 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-brand-600"><span>Jam</span><span>Menit</span></div><div class="grid max-h-56 grid-cols-2 gap-2 overflow-y-auto pt-1"><div><template x-for="hour in hours" :key="hour"><button type="button" x-on:click="choose(hour, minute || '00')" x-text="hour" class="block w-full rounded-lg px-3 py-2 text-sm hover:bg-brand-50"></button></template></div><div><template x-for="minuteOption in minutes" :key="minuteOption"><button type="button" x-on:click="choose(hour || '00', minuteOption)" x-text="minuteOption" class="block w-full rounded-lg px-3 py-2 text-sm hover:bg-brand-50"></button></template></div></div></div>@error('startTime')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div><div x-data="guestbookTimePicker(@entangle('endTime'))"><label for="event-end" class="text-sm font-semibold text-slate-700">Selesai</label><div class="relative mt-2"><button id="event-end" type="button" x-on:click="open = !open" x-on:click.outside="open = false" class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-slate-900"><span x-text="value || 'Pilih waktu'"></span><i data-lucide="clock" class="size-4 text-brand-700"></i></button><input type="hidden" wire:model="endTime"><div x-show="open" x-transition class="absolute right-0 z-30 mt-2 grid max-h-64 w-full grid-cols-2 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2"><div><template x-for="hour in hours" :key="hour"><button type="button" x-on:click="choose(hour, minute || '00')" x-text="hour" class="block w-full rounded-lg px-3 py-2 text-sm hover:bg-brand-50"></button></template></div><div><template x-for="minuteOption in minutes" :key="minuteOption"><button type="button" x-on:click="choose(hour || '00', minuteOption)" x-text="minuteOption" class="block w-full rounded-lg px-3 py-2 text-sm hover:bg-brand-50"></button></template></div></div></div>@error('endTime')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div></div>
                <div><label for="event-location" class="text-sm font-semibold text-slate-700">Lokasi</label><input id="event-location" wire:model="location" type="text" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500" placeholder="Nama venue">@error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="event-map" class="text-sm font-semibold text-slate-700">Link peta</label><input id="event-map" wire:model="mapUrl" type="url" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500" placeholder="https://maps.google.com/...">@error('mapUrl')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section class="space-y-5 border-t border-slate-100 pt-8">
            <div>
                <h2 class="text-lg font-semibold text-brand-950">Template undangan</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih template yang dipakai event ini. Template bisa kamu atur terpisah di menu Template undangan.</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <div><label for="invitation-category" class="text-sm font-semibold text-slate-700">Kategori undangan</label><select id="invitation-category" wire:model="invitationCategory" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500"><option value="general">Umum &amp; Seminar</option><option value="wedding">Pernikahan</option><option value="birthday">Ulang Tahun</option><option value="corporate">Corporate</option><option value="other">Lainnya</option></select>@error('invitationCategory')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="invitation-link" class="text-sm font-semibold text-slate-700">Custom URL</label><div class="mt-2 flex"><span class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-300 bg-slate-100 px-3 text-sm text-slate-500">/invite/</span><input id="invitation-link" wire:model="invitationLink" class="min-w-0 flex-1 rounded-r-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500" placeholder="nama-event"></div><p class="mt-1 text-xs text-slate-500">Gunakan huruf kecil, angka, dan tanda hubung.</p>@error('invitationLink')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            </div>            <div>
                <label for="event-template" class="text-sm font-semibold text-slate-700">Template aktif</label>
                <select id="event-template" wire:model="templateId" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Belum pilih template</option>
                    @foreach ($templates as $id => $templateName)
                        <option value="{{ $id }}">{{ $templateName }}</option>
                    @endforeach
                </select>
                @error('templateId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>
        <section class="space-y-5 border-t border-slate-100 pt-8">
            <div><h2 class="text-lg font-semibold text-brand-950">Kontak event</h2><p class="mt-1 text-sm text-slate-500">Kontak yang dapat dihubungi penerima undangan.</p></div>
            <div class="grid gap-5 md:grid-cols-2"><div><label for="contact-name" class="text-sm font-semibold text-slate-700">Nama kontak</label><input id="contact-name" wire:model="contactName" type="text" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500">@error('contactName')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div><div><label for="contact-phone" class="text-sm font-semibold text-slate-700">Nomor kontak</label><input id="contact-phone" wire:model="contactPhone" type="text" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 focus:border-brand-500 focus:ring-brand-500">@error('contactPhone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div></div>
            <label class="flex items-center gap-3"><input wire:model="countdownEnabled" type="checkbox" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500"><span class="text-sm font-semibold text-slate-700">Aktifkan countdown</span></label>
        </section>

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-6"><a href="{{ route('admin.events') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">Batal</a><button type="submit" class="rounded-xl bg-brand-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-800">Simpan event</button></div>
    </form>
</div>



