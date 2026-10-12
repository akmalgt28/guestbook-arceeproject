<?php

use App\Models\Event;
use App\Models\InvitationTemplate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public int $eventId;

    public bool $showPageLayoutModal = false;

    public string $pageCategory = 'opening';

    public string $selectedPageLayout = 'opening-classic';

    public ?string $editingSectionKey = null;

    public array $sectionDraft = [];

    public function mount(int $eventId): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);
        $this->eventId = $eventId;
        $this->accessibleEvents()->findOrFail($eventId);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'event' => $this->accessibleEvents()->with('templates')->findOrFail($this->eventId),
        ];
    }

    public function openPageLayoutModal(): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);
        $this->showPageLayoutModal = true;
    }

    public function closePageLayoutModal(): void
    {
        $this->showPageLayoutModal = false;
    }

    public function selectPageLayout(string $layout): void
    {
        $this->selectedPageLayout = $layout;
    }

    public function updatedPageCategory(string $category): void
    {
        $layout = collect($this->pageLayouts())->firstWhere('category', $category);
        $this->selectedPageLayout = $layout['key'] ?? '';
    }

    public function addPage(): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);
        $event = $this->accessibleEvents()->with('templates')->findOrFail($this->eventId);
        $template = $event->templates->first();
        abort_if($template === null, 422, 'Event belum memiliki template undangan.');
        $layout = collect($this->pageLayouts())->firstWhere('key', $this->selectedPageLayout);
        abort_if($layout === null || $layout['category'] !== $this->pageCategory, 422, 'Layout halaman tidak valid.');
        $pivotSettings = $template->pivot?->settings;
        if (! is_array($pivotSettings)) {
            $pivotSettings = json_decode((string) $pivotSettings, true) ?: [];
        }
        $customPages = is_array($pivotSettings['custom_pages'] ?? null) ? $pivotSettings['custom_pages'] : [];
        $customPages[] = ['id' => 'page-'.Str::lower(Str::random(12)), 'category' => $layout['category'], 'layout' => $layout['key'], 'title' => $layout['label'], 'visible' => true, 'settings' => []];
        $pivotSettings['custom_pages'] = $customPages;
        $event->templates()->updateExistingPivot($template->getKey(), ['settings' => $pivotSettings]);
        $this->showPageLayoutModal = false;
        session()->flash('success', 'Layout halaman berhasil ditambahkan.');
    }

    /** @return list<array{key: string, category: string, label: string, description: string}> */
    private function pageLayouts(): array
    {
        return [
            ['key' => 'opening-classic', 'category' => 'opening', 'label' => 'Opening Classic', 'description' => 'Pembuka klasik'],
            ['key' => 'opening-arch', 'category' => 'opening', 'label' => 'Opening Arch', 'description' => 'Pembuka frame lengkung'],
            ['key' => 'opening-editorial', 'category' => 'opening', 'label' => 'Opening Editorial', 'description' => 'Pembuka tipografi'],
            ['key' => 'event-card', 'category' => 'event_info', 'label' => 'Event Card', 'description' => 'Detail acara dalam kartu'],
            ['key' => 'event-timeline', 'category' => 'event_info', 'label' => 'Event Timeline', 'description' => 'Detail acara dalam timeline'],
            ['key' => 'location-map', 'category' => 'location', 'label' => 'Location Map', 'description' => 'Lokasi dan peta'],
            ['key' => 'gallery-grid', 'category' => 'gallery', 'label' => 'Gallery Grid', 'description' => 'Galeri kotak'],
            ['key' => 'gallery-masonry', 'category' => 'gallery', 'label' => 'Gallery Masonry', 'description' => 'Galeri masonry'],
            ['key' => 'gallery-slider', 'category' => 'gallery', 'label' => 'Gallery Slider', 'description' => 'Galeri slider'],
            ['key' => 'rsvp-card', 'category' => 'rsvp', 'label' => 'RSVP Card', 'description' => 'Form RSVP'],
            ['key' => 'qr-centered', 'category' => 'qr', 'label' => 'QR Centered', 'description' => 'QR terpusat'],
            ['key' => 'guestbook-card', 'category' => 'guestbook', 'label' => 'Guestbook Card', 'description' => 'Guestbook'],
            ['key' => 'contact-simple', 'category' => 'contact', 'label' => 'Contact Simple', 'description' => 'Kontak sederhana'],
        ];
    }

    public function saveSectionEditor(): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);

        $data = $this->validate([
            'editingSectionKey' => ['required', 'string', Rule::in(array_keys(InvitationTemplate::SECTION_DEFINITIONS))],
            'sectionDraft.title' => ['nullable', 'string', 'max:255'],
            'sectionDraft.subtitle' => ['nullable', 'string', 'max:1000'],
            'sectionDraft.recipient_label' => ['nullable', 'string', 'max:255'],
            'sectionDraft.logo_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $event = $this->accessibleEvents()->with('templates')->findOrFail($this->eventId);
        $template = $event->templates->first();
        abort_if($template === null, 422, 'Event belum memiliki template undangan.');

        $pivotSettings = $template->pivot?->settings;
        if (! is_array($pivotSettings)) {
            $pivotSettings = json_decode((string) $pivotSettings, true) ?: [];
        }

        $schema = InvitationTemplate::mergedSchema($template->schema ?? [], $pivotSettings);
        $sections = $schema['sections'];
        foreach ($sections as $index => $section) {
            if (($section['key'] ?? null) !== $data['editingSectionKey']) {
                continue;
            }

            $sectionSettings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
            foreach (['title', 'subtitle', 'recipient_label'] as $key) {
                if (array_key_exists($key, $this->sectionDraft)) {
                    $sectionSettings[$key] = trim((string) $this->sectionDraft[$key]);
                }
            }
            $sections[$index]['settings'] = $sectionSettings;
            break;
        }

        $pivotSettings['sections'] = array_values($sections);
        if ($data['editingSectionKey'] === 'hero') {
            $pivotSettings['media']['hero']['url'] = filled($this->sectionDraft['logo_url'] ?? null) ? trim((string) $this->sectionDraft['logo_url']) : null;
        }

        $event->templates()->updateExistingPivot($template->getKey(), ['settings' => $pivotSettings]);
        session()->flash('success', 'Konten halaman berhasil disimpan.');
    }

    public function togglePage(string $key): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);

        $event = $this->accessibleEvents()->with('templates')->findOrFail($this->eventId);
        $template = $event->templates->first();
        abort_if($template === null, 422, 'Event belum memiliki template undangan.');

        $pivotSettings = $template->pivot?->settings;
        if (! is_array($pivotSettings)) {
            $pivotSettings = json_decode((string) $pivotSettings, true) ?: [];
        }
        $schema = InvitationTemplate::mergedSchema($template->schema ?? [], $pivotSettings);
        $sections = $schema['sections'];

        foreach ($sections as $index => $section) {
            if (($section['key'] ?? null) !== $key) {
                continue;
            }
            $sections[$index]['visible'] = ! (bool) ($section['visible'] ?? true);
            $pivotSettings['sections'] = array_values($sections);
            $event->templates()->updateExistingPivot($template->getKey(), ['settings' => $pivotSettings]);
            return;
        }
    }

    public function movePage(int $index, int $direction): void
    {
        abort_unless(auth()->user()?->can('events.update'), 403);

        $event = $this->accessibleEvents()->with('templates')->findOrFail($this->eventId);
        $template = $event->templates->first();
        abort_if($template === null, 422, 'Event belum memiliki template undangan.');

        $pivotSettings = $template->pivot?->settings;
        if (! is_array($pivotSettings)) {
            $pivotSettings = json_decode((string) $pivotSettings, true) ?: [];
        }

        $schema = InvitationTemplate::mergedSchema($template->schema ?? [], $pivotSettings);
        $sections = $schema['sections'];
        $target = $index + $direction;

        if (! isset($sections[$index], $sections[$target])) {
            return;
        }

        [$sections[$index], $sections[$target]] = [$sections[$target], $sections[$index]];
        $pivotSettings['sections'] = array_values($sections);

        $event->templates()->updateExistingPivot($template->getKey(), ['settings' => $pivotSettings]);
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();

        return Event::query()->when(
            ! $user->hasRole('Super Admin'),
            fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('created_by', $user->getKey())
                    ->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())),
            ),
        );
    }
};
?>
@php
    $template = $event->templates->first();
    $pivotSettings = $template?->pivot?->settings;
    if (! is_array($pivotSettings)) {
        $pivotSettings = json_decode((string) $pivotSettings, true) ?: [];
    }
    $schema = InvitationTemplate::mergedSchema($template?->schema ?? [], $pivotSettings);
    $customLink = data_get($event->settings, 'invitation.custom_link');
    $previewUrl = route('invitations.preview', $customLink ?: \Illuminate\Support\Str::slug($event->name));
    $livePreviewUrl = route('admin.events.invitation-preview', $event);
    $layoutOptions = [
        ['key' => 'opening-classic', 'category' => 'opening', 'label' => 'Opening Classic', 'description' => 'Pembuka klasik'], ['key' => 'opening-arch', 'category' => 'opening', 'label' => 'Opening Arch', 'description' => 'Pembuka frame lengkung'], ['key' => 'opening-editorial', 'category' => 'opening', 'label' => 'Opening Editorial', 'description' => 'Pembuka tipografi'],
        ['key' => 'event-card', 'category' => 'event_info', 'label' => 'Event Card', 'description' => 'Detail acara dalam kartu'], ['key' => 'event-timeline', 'category' => 'event_info', 'label' => 'Event Timeline', 'description' => 'Detail acara timeline'], ['key' => 'location-map', 'category' => 'location', 'label' => 'Location Map', 'description' => 'Lokasi dan peta'],
        ['key' => 'gallery-grid', 'category' => 'gallery', 'label' => 'Gallery Grid', 'description' => 'Galeri kotak'], ['key' => 'gallery-masonry', 'category' => 'gallery', 'label' => 'Gallery Masonry', 'description' => 'Galeri masonry'], ['key' => 'gallery-slider', 'category' => 'gallery', 'label' => 'Gallery Slider', 'description' => 'Galeri slider'],
        ['key' => 'rsvp-card', 'category' => 'rsvp', 'label' => 'RSVP Card', 'description' => 'Form RSVP'], ['key' => 'qr-centered', 'category' => 'qr', 'label' => 'QR Centered', 'description' => 'QR terpusat'], ['key' => 'guestbook-card', 'category' => 'guestbook', 'label' => 'Guestbook Card', 'description' => 'Guestbook'], ['key' => 'contact-simple', 'category' => 'contact', 'label' => 'Contact Simple', 'description' => 'Kontak sederhana'],
    ];
    $activeBackground = collect($schema['backgrounds'] ?? [])->firstWhere('id', $schema['active_background_id'] ?? null);
    $backgroundElements = is_array($activeBackground) ? ($activeBackground['elements'] ?? []) : ($schema['background_elements'] ?? []);
    $invitationBackground = is_array($activeBackground) ? ($activeBackground['color'] ?? '#DCEEFF') : ($schema['background_color'] ?? '#DCEEFF');
    $sectionEditorData = [];
    $sectionLabels = [];
    foreach ($schema['sections'] ?? [] as $section) {
        $sectionKey = $section['key'] ?? null;
        if (! is_string($sectionKey)) {
            continue;
        }
        $sectionLabels[$sectionKey] = data_get(InvitationTemplate::SECTION_DEFINITIONS, $sectionKey.'.label', str($sectionKey)->headline());
        $sectionEditorData[$sectionKey] = array_merge(
            ['title' => '', 'subtitle' => '', 'recipient_label' => '', 'logo_url' => ''],
            is_array($section['settings'] ?? null) ? $section['settings'] : [],
        );
    }
    $sectionEditorData['hero']['logo_url'] = data_get($schema, 'media.hero.url', '');
    $actions = [
        ['label' => 'Pengaturan', 'icon' => 'settings', 'tone' => 'text-blue-500'],
        ['label' => 'Tema', 'icon' => 'palette', 'tone' => 'text-emerald-500'],
        ['label' => 'Music', 'icon' => 'music-2', 'tone' => 'text-orange-400'],
        ['label' => 'Background', 'icon' => 'image', 'tone' => 'text-pink-400'],
        ['label' => 'RSVP', 'icon' => 'mail', 'tone' => 'text-cyan-500'],
        ['label' => 'Layar Sapa', 'icon' => 'monitor', 'tone' => 'text-rose-400'],
        ['label' => 'Preview', 'icon' => 'eye', 'tone' => 'text-orange-400'],
        ['label' => 'Kirim', 'icon' => 'send', 'tone' => 'text-lime-500'],
        ['label' => 'Lucky Draw', 'icon' => 'grid-2x2', 'tone' => 'text-violet-500'],
    ];
@endphp
<div
    class="mx-auto w-full max-w-[1480px] space-y-6 pb-12"
    x-data="{
        layoutModalOpen: false,
        elementEditorOpen: false,
        elementType: '',
        elementDraft: '',
        openElement(type) {
            this.elementType = type;
            this.elementDraft = type === 'title' ? (this.sectionDraft.title || '') : type === 'subtitle' ? (this.sectionDraft.subtitle || '') : type === 'recipient' ? (this.sectionDraft.recipient_label || '') : (this.sectionDraft.logo_url || '');
            this.elementEditorOpen = true;
        },
        saveElement() {
            if (this.elementType === 'title') this.sectionDraft.title = this.elementDraft;
            if (this.elementType === 'subtitle') this.sectionDraft.subtitle = this.elementDraft;
            if (this.elementType === 'recipient') this.sectionDraft.recipient_label = this.elementDraft;
            if (this.elementType === 'logo') this.sectionDraft.logo_url = this.elementDraft;
            this.elementEditorOpen = false;
        },
        activeSection: @entangle('editingSectionKey').live,
        sectionDraft: @entangle('sectionDraft').live,
        sectionData: @js($sectionEditorData),
        sectionLabels: @js($sectionLabels),
        selectedLayout: @entangle('selectedPageLayout').live,
        category: @entangle('pageCategory').live,
        layouts: @js($layoutOptions),
        filteredLayouts() {
            return this.layouts.filter((layout) => layout.category === this.category);
        },
    }"
    x-on:keydown.escape.window="layoutModalOpen = false; elementEditorOpen = false"
>
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.events.overview', $event) }}" class="grid size-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-brand-300 hover:bg-brand-50" aria-label="Kembali"><i data-lucide="arrow-left" class="size-5"></i></a>
            <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Invitation editor</p><h1 class="mt-1 text-2xl font-semibold tracking-tight text-brand-950">{{ $event->name ?: 'Undangan Event' }}</h1><p class="mt-1 text-sm text-slate-500">Atur halaman, tampilan, dan fitur undangan dari satu workspace.</p></div>
        </div>
        <div class="flex items-center gap-3"><a href="{{ route('admin.events.overview', $event) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50"><i data-lucide="arrow-up-right" class="size-4"></i>Lihat event</a></div>
    </header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_430px] xl:items-start">
        <main class="min-w-0 space-y-6">
            

            <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($actions as $action)
                    <button type="button" class="group flex min-h-[82px] flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 text-center text-xs font-semibold text-brand-950 transition hover:-translate-y-0.5 hover:border-brand-300 hover:bg-brand-50"><i data-lucide="{{ $action['icon'] }}" class="size-5 {{ $action['tone'] }} transition group-hover:scale-110"></i><span>{{ $action['label'] }}</span></button>
                @endforeach
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                @foreach ([['Status Undangan', 'Aktif', true], ['Tipe Undangan', 'Swipe', true]] as [$label, $value, $enabled])
                    <div class="flex items-center justify-between rounded-2xl border border-lime-200 bg-lime-50 px-5 py-4"><div><p class="text-sm font-semibold text-brand-950">{{ $label }}</p><span class="mt-1 inline-flex rounded-md bg-lime-200 px-2 py-0.5 text-[10px] font-bold text-brand-950">{{ $value }}</span></div><button type="button" x-data="{ enabled: {{ $enabled ? 'true' : 'false' }} }" x-on:click="enabled = ! enabled" class="relative h-7 w-12 rounded-full bg-cyan-600 transition" :class="enabled ? 'bg-cyan-600' : 'bg-slate-300'" :aria-pressed="enabled"><span class="absolute top-1 size-5 rounded-full bg-white transition" :class="enabled ? 'right-1' : 'left-1'"></span></button></div>
                @endforeach
            </section>

            

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-100 pb-5"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">Struktur undangan</p><h2 class="mt-1 text-xl font-semibold text-brand-950">Halaman undangan</h2><p class="mt-1 text-sm text-slate-500">Atur urutan, tampilkan, dan edit isi tiap halaman.</p></div></div>
                <div class="mt-5 space-y-3">
                    @foreach ($schema['sections'] as $index => $section)
                        @php
                            $sectionKey = $section['key'];
                            $sectionLabel = data_get(InvitationTemplate::SECTION_DEFINITIONS, $sectionKey.'.label', str($sectionKey)->headline());
                            $sectionVisible = (bool) ($section['visible'] ?? true);
                        @endphp
                        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <button type="button" wire:click="movePage({{ $index }}, -1)" class="grid size-9 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-brand-300 hover:text-brand-700" aria-label="Naikkan {{ $sectionLabel }}"><i data-lucide="arrow-up-down" class="size-4"></i></button>
                            <div class="grid size-10 place-items-center rounded-xl bg-white text-brand-700"><i data-lucide="layout-panel-top" class="size-5"></i></div>
                            <div class="min-w-[8rem] flex-1"><p class="text-sm font-semibold text-brand-950">{{ $sectionLabel }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $sectionKey }}</p></div>
                            <button type="button" wire:click="togglePage('{{ $sectionKey }}')" class="relative h-7 w-12 rounded-full transition {{ $sectionVisible ? 'bg-cyan-600' : 'bg-slate-300' }}" aria-label="{{ $sectionVisible ? 'Sembunyikan' : 'Tampilkan' }} {{ $sectionLabel }}"><span class="absolute top-1 size-5 rounded-full bg-white transition {{ $sectionVisible ? 'right-1' : 'left-1' }}"></span></button>
                            <button type="button" x-on:click="activeSection = @js($sectionKey); sectionDraft = JSON.parse(JSON.stringify(sectionData[@js($sectionKey)] || {})); " class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-brand-950 transition hover:border-brand-400 hover:bg-brand-50"><i data-lucide="pencil" class="size-4"></i>Edit</button>
                        </div>
                        <div x-show="activeSection === @js($sectionKey)" x-cloak class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-3 py-2"><button type="button" class="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-600" aria-label="Halaman"><i data-lucide="house" class="size-4"></i></button><input x-model="sectionDraft.title" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium" placeholder="Nama section"><button type="button" x-on:click="openElement('logo')" class="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-600 hover:border-brand-300" aria-label="Edit gambar"><i data-lucide="image" class="size-4"></i></button><button type="button" class="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-600" aria-label="Duplikat"><i data-lucide="copy" class="size-4"></i></button><button type="button" class="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-600" aria-label="Hapus"><i data-lucide="trash-2" class="size-4"></i></button></div>
                            <div class="p-3 sm:p-5"><div class="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="relative min-h-[38rem] overflow-hidden" style="background: {{ $invitationBackground }}">
                                @foreach ($backgroundElements as $element)
                                    @php
                                        $elementType = $element['type'] ?? 'rectangle';
                                        $elementStyle = implode(';', ['left: '.(float) ($element['x'] ?? 0).'%', 'top: '.(float) ($element['y'] ?? 0).'%', 'width: '.(float) ($element['width'] ?? 10).'%', 'height: '.(float) ($element['height'] ?? 10).'%', 'background: '.($element['fill'] ?? '#168DE2'), 'opacity: '.((float) ($element['opacity'] ?? 100) / 100), 'border-radius: '.(float) ($element['radius'] ?? 0).'px', 'transform: rotate('.(float) ($element['rotation'] ?? 0).'deg)']);
                                    @endphp
                                    @if ($elementType === 'image' && filled($element['url'] ?? null))<img src="{{ $element['url'] }}" alt="" class="pointer-events-none absolute z-0 object-cover" style="{{ $elementStyle }}">@else<div class="pointer-events-none absolute z-0" style="{{ $elementStyle }}"></div>@endif
                                @endforeach
                                <div class="pointer-events-none absolute -right-10 -top-8 size-32 rounded-full bg-sky-500/80"></div><div class="pointer-events-none absolute -left-10 top-36 size-28 rounded-full bg-sky-300/80"></div><div class="pointer-events-none absolute -bottom-10 -right-8 size-36 rounded-full bg-brand-950/80"></div>
                                <div class="relative z-10 flex min-h-[38rem] flex-col px-8 py-7 text-center sm:px-12" style="color: {{ $schema['text_color'] ?? '#1F2937' }}"><div class="text-left text-xl font-black tracking-tight">ARC</div><div class="mt-16"><p class="text-sm font-medium">{{ $event->name }}</p><button type="button" x-on:click="openElement('title')" class="mx-auto mt-2 block max-w-full border border-dashed border-transparent px-2 text-4xl font-black leading-tight hover:border-cyan-300" x-text="sectionDraft.title || 'Nama acara'"></button><button type="button" x-on:click="openElement('subtitle')" class="mx-auto mt-2 block max-w-full border border-dashed border-transparent px-2 text-base hover:border-cyan-300" x-text="sectionDraft.subtitle || 'Tema acara kamu'"></button></div><button type="button" x-on:click="openElement('logo')" class="mx-auto mt-8 block size-36 rounded-full border-2 border-dashed border-current/40 bg-white/70 p-2"><template x-if="sectionDraft.logo_url"><img x-bind:src="sectionDraft.logo_url" alt="Logo halaman" class="size-full rounded-full object-contain"></template><template x-if="! sectionDraft.logo_url"><i data-lucide="image-plus" class="mx-auto size-8 opacity-60"></i></template></button><div class="mt-auto"><button type="button" x-on:click="openElement('recipient')" class="border border-dashed border-transparent px-2 text-sm hover:border-cyan-300" x-text="sectionDraft.recipient_label || 'Kepada Yth.'"></button><p class="mt-1 text-xl font-bold">Nama Tamu</p><p class="text-sm">Undangan</p></div><div class="mt-7 flex h-14 items-center justify-center rounded-t-3xl bg-brand-950 text-sm font-semibold text-white">Buka Undangan</div></div>
                            </div></div></div>
                            <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-white px-3 py-3"><p class="text-xs text-slate-500">Klik teks atau gambar pada preview untuk mengubahnya.</p><button type="button" wire:click="saveSectionEditor" class="rounded-xl bg-brand-950 px-4 py-2.5 text-sm font-semibold text-white">Simpan</button></div>
                        </div>                    @endforeach
                </div>
                <button type="button" x-on:click="layoutModalOpen = true" class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-cyan-700"><i data-lucide="plus" class="size-5"></i>Tambah halaman</button>
            </section>

            </main>

        <aside class="space-y-4 xl:sticky xl:top-24">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">Live preview</p><h2 class="mt-1 text-lg font-semibold text-brand-950">Tampilan undangan</h2></div><a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700" aria-label="Buka preview"><i data-lucide="maximize-2" class="size-4"></i></a></div><div class="bg-slate-100 p-5"><iframe src="{{ $livePreviewUrl }}" title="Live preview {{ $event->name }}" class="h-[800px] w-full rounded-2xl border border-slate-200 bg-white" loading="eager"></iframe></div></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="layers-3" class="size-5"></i></span><div><p class="text-sm font-semibold text-brand-950">Workspace event</p><p class="mt-1 text-xs text-slate-500">{{ $event->status === 'published' ? 'Event published' : 'Draft event' }}</p></div></div><div class="mt-4 grid grid-cols-2 gap-3"><a href="{{ route('admin.events.recipients', $event) }}" class="rounded-xl border border-slate-200 px-3 py-3 text-center text-xs font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50"><i data-lucide="users" class="mx-auto mb-1 size-4 text-brand-600"></i>Recipients</a><a href="{{ route('admin.events.rsvp', $event) }}" class="rounded-xl border border-slate-200 px-3 py-3 text-center text-xs font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50"><i data-lucide="clipboard-check" class="mx-auto mb-1 size-4 text-brand-600"></i>RSVP</a></div></section>
        </aside>
    <div x-show="elementEditorOpen" x-cloak x-on:click.self="elementEditorOpen = false" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/45 p-4" style="position:fixed;inset:0;z-index:10001;display:flex;align-items:center;justify-content:center;background:rgb(15 23 42 / 0.45);padding:1rem" role="dialog" aria-modal="true" aria-labelledby="element-editor-title">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white" style="width:min(100%,28rem);margin:auto;border-radius:1rem;background:#fff"><div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><h2 id="element-editor-title" class="font-semibold text-brand-950" x-text="elementType === 'logo' ? 'Edit gambar' : 'Edit teks'"></h2><button type="button" x-on:click="elementEditorOpen = false" class="grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Tutup"><i data-lucide="x" class="size-4"></i></button></div><div class="space-y-4 p-5"><template x-if="elementType === 'logo'"><div><label for="element-image" class="text-sm font-semibold text-slate-700">URL gambar atau logo</label><input id="element-image" x-model="elementDraft" type="url" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="https://contoh.com/logo.png"></div></template><template x-if="elementType !== 'logo'"><div><label for="element-text" class="text-sm font-semibold text-slate-700">Isi teks</label><textarea id="element-text" x-model="elementDraft" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea></div></template></div><div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4"><button type="button" x-on:click="elementEditorOpen = false" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Batal</button><button type="button" x-on:click="saveElement()" class="rounded-xl bg-brand-950 px-4 py-2.5 text-sm font-semibold text-white">Simpan</button></div></div>
    </div>
    <div x-show="layoutModalOpen" x-cloak x-on:click.self="layoutModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgb(15 23 42 / 0.45);padding:1rem" role="dialog" aria-modal="true" aria-labelledby="page-layout-title">
        <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl bg-white" style="width:min(100%,56rem);max-height:90vh;margin:auto;border-radius:1.5rem;background:#fff">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Tambah halaman</p><h2 id="page-layout-title" class="mt-1 text-xl font-semibold text-brand-950">Pilih Layout Halaman</h2></div><button type="button" x-on:click="layoutModalOpen = false" class="grid size-9 place-items-center rounded-xl text-slate-500 transition hover:bg-slate-100" aria-label="Tutup"><i data-lucide="x" class="size-5"></i></button></div>
            <div class="min-h-0 overflow-y-auto p-6" style="padding:1.5rem;overflow-y:auto">
                <div class="mb-6 flex items-center gap-3" style="margin-bottom:1.5rem"><label for="page-category" class="text-sm font-semibold text-slate-700">Kategori</label><select id="page-category" x-model="category" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"><option value="opening">Opening</option><option value="event_info">Event</option><option value="location">Maps</option><option value="gallery">Gallery</option><option value="rsvp">RSVP</option><option value="qr">QR Check-in</option><option value="guestbook">Guestbook</option><option value="contact">Contact</option></select></div>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" style="gap:1.25rem;margin-top:1.5rem"><template x-for="layout in filteredLayouts()" :key="layout.key"><button type="button" x-on:click="selectedLayout = layout.key" class="group rounded-2xl border p-3 text-left transition" :class="selectedLayout === layout.key ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-200' : 'border-slate-200 hover:border-brand-300'"><div class="relative aspect-[3/4] overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="mx-auto flex h-full max-w-[76%] flex-col items-center gap-2 rounded-lg border border-slate-200 bg-white p-3"><span class="mt-3 h-1.5 w-1/2 rounded-full bg-slate-300"></span><span class="mt-2 size-12 rounded-full bg-slate-200"></span><span class="h-1.5 w-3/4 rounded-full bg-slate-300"></span><span class="h-1.5 w-1/2 rounded-full bg-slate-200"></span><span class="mt-auto h-5 w-2/3 rounded-full bg-slate-200"></span></div></div><p class="mt-3 text-sm font-semibold text-brand-950" x-text="layout.label"></p><p class="mt-1 text-xs text-slate-500" x-text="layout.description"></p></button></template></div>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4"><button type="button" x-on:click="layoutModalOpen = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Batal</button><button type="button" wire:click="addPage" x-on:click="layoutModalOpen = false" class="rounded-xl bg-brand-950 px-4 py-2.5 text-sm font-semibold text-white">Tambah halaman</button></div>
        </div>
    </div>
</div>
</div>




