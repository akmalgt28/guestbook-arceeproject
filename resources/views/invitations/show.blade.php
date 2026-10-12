<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @php
        $radiusMap = ['none' => '0px', 'md' => '0.75rem', 'xl' => '1.5rem', 'full' => '9999px'];
        $buttonRadiusMap = ['rounded' => '0.75rem', 'pill' => '9999px', 'square' => '0.25rem'];
        $bodySizeMap = ['sm' => '0.875rem', 'base' => '1rem', 'lg' => '1.125rem'];
        $decorations = $schema['decorations'] ?? ['enabled' => true, 'primary' => '#0B4AA2', 'blue' => '#168DE2', 'light' => '#8BC4F0'];
        $activeBackground = collect($schema['backgrounds'] ?? [])->firstWhere('id', $schema['active_background_id'] ?? null);
        $backgroundElements = is_array($activeBackground) ? ($activeBackground['elements'] ?? []) : ($schema['background_elements'] ?? []);
        $invitationBackground = is_array($activeBackground) ? ($activeBackground['color'] ?? $schema['background_color']) : $schema['background_color'];
    @endphp
    <style>
        :root {
            --invitation-primary: {{ $schema['primary_color'] }};
            --invitation-secondary: {{ $schema['secondary_color'] }};
            --invitation-background: {{ $invitationBackground }};
            --invitation-desktop-background: {{ $schema['desktop_background_color'] ?? '#111827' }};
            --invitation-text: {{ $schema['text_color'] }};
            --invitation-font: '{{ $schema['body_font_family'] }}', sans-serif;
            --invitation-heading-font: '{{ $schema['heading_font_family'] }}', sans-serif;
            --invitation-radius: {{ $radiusMap[$schema['radius']] ?? $radiusMap['xl'] }};
            --invitation-button-radius: {{ $buttonRadiusMap[$schema['button_style']] ?? $buttonRadiusMap['rounded'] }};
            --invitation-nav-background: {{ data_get($schema, 'config.navigation.background_color', '#90CAF9') }};
            --invitation-nav-active: {{ data_get($schema, 'config.navigation.active_color', '#0B4AA2') }};
            --invitation-nav-text: {{ data_get($schema, 'config.navigation.text_color', '#5B5560') }};
            --invitation-nav-icon: {{ data_get($schema, 'config.navigation.icon_color', '#FFFFFF') }};
            --invitation-body-size: {{ $bodySizeMap[$schema['body_size']] ?? $bodySizeMap['base'] }};
            --invitation-circle-primary: {{ $decorations['primary'] }};
            --invitation-circle-blue: {{ $decorations['blue'] }};
            --invitation-circle-light: {{ $decorations['light'] }};
        }
        html { background: var(--invitation-desktop-background); }
        body { background: var(--invitation-desktop-background); color: var(--invitation-text); font-size: var(--invitation-body-size); }
        .invitation-shell { min-height: 100vh; width: 100%; max-width: 35rem; background: var(--invitation-background); }
        .invitation-cover { background-color: var(--invitation-secondary); background-image: radial-gradient(circle at 0% 22%, var(--invitation-circle-primary) 0 1.2rem, transparent 1.25rem), radial-gradient(circle at 100% 12%, var(--invitation-circle-primary) 0 1.4rem, transparent 1.45rem), radial-gradient(circle at 8% 72%, var(--invitation-circle-primary) 0 1rem, transparent 1.05rem), radial-gradient(circle at 94% 78%, var(--invitation-circle-primary) 0 1.1rem, transparent 1.15rem); color: var(--invitation-primary); }
        .invitation-cover::before { content: ""; position: absolute; inset: 0; pointer-events: none; background: radial-gradient(circle at 12% 28%, var(--invitation-circle-light) 0 0.8rem, transparent 0.85rem), radial-gradient(circle at 88% 22%, var(--invitation-circle-light) 0 0.75rem, transparent 0.8rem), radial-gradient(circle at 18% 84%, var(--invitation-circle-light) 0 0.9rem, transparent 0.95rem); opacity: 0.95; }
        .invitation-decoration { position: absolute; border-radius: 9999px; pointer-events: none; }
        .invitation-floating-image { position: absolute; z-index: 1; pointer-events: none; object-fit: contain; }
        .invitation-background-element { position: absolute; z-index: 0; pointer-events: none; }
        .invitation-background-element--line, .invitation-background-element--arrow { height: max(2px, 0.7%); border-radius: 9999px; transform-origin: left center; }
        .invitation-background-element--arrow::after { content: ""; position: absolute; right: -1px; top: 50%; width: 9px; height: 9px; border-top: 2px solid currentColor; border-right: 2px solid currentColor; transform: translateY(-50%) rotate(45deg); }
        .invitation-background-element--ellipse { height: auto !important; aspect-ratio: 1 / 1; }
        .invitation-background-element--polygon { clip-path: polygon(50% 0%, 100% 100%, 0% 100%); }
        .invitation-background-element--star { clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 100%, 50% 74%, 21% 100%, 32% 57%, 2% 35%, 39% 35%); }
        .invitation-cover > :not(.invitation-decoration) { position: relative; z-index: 1; }
        .invitation-decoration { z-index: 0; }
        .invitation-cover[data-decorations="off"]::before, .invitation-cover[data-decorations="off"] .invitation-decoration { display: none; }
        .invitation-info-page { background-color: var(--invitation-primary); background-image: radial-gradient(circle at 0% 16%, var(--invitation-circle-blue) 0 1rem, transparent 1.05rem), radial-gradient(circle at 100% 40%, var(--invitation-circle-blue) 0 1rem, transparent 1.05rem), radial-gradient(circle at 10% 92%, var(--invitation-circle-blue) 0 1.1rem, transparent 1.15rem); }
        .invitation-bottom-nav { position: fixed; bottom: 0; left: 50%; z-index: 20; width: min(100%, 35rem); overflow-x: auto; scrollbar-width: none; transform: translateX(-50%); transition: opacity 180ms ease, visibility 180ms ease; background: var(--invitation-nav-background); color: var(--invitation-nav-text); }
        .invitation-bottom-nav::-webkit-scrollbar { display: none; }
        .invitation-bottom-nav { padding-top: 0.45rem; padding-bottom: 0.45rem; }
        .invitation-bottom-nav > div { display: flex; min-width: max-content; gap: 0.25rem; }
        .invitation-bottom-nav [data-nav-link] { display: flex; aspect-ratio: 1 / 1; width: calc((min(100vw, 35rem) - 2.5rem) / 5); min-height: 4.25rem; flex: 0 0 calc((min(100vw, 35rem) - 2.5rem) / 5); margin: 0 auto; flex-direction: column; align-items: center; justify-content: center; }
        .invitation-bottom-nav [data-nav-link] span { font-size: 0.8rem; }
        .invitation-bottom-nav [data-nav-link] svg { width: 1.8rem; height: 1.8rem; }
        .invitation-bottom-nav [data-nav-link].is-active { background: var(--invitation-nav-active); color: var(--invitation-nav-icon); }
        .invitation-card { border-radius: var(--invitation-radius); }
        .invitation-button { border-radius: var(--invitation-button-radius); }
        @media (max-width: 767px) {
            html, body { background: var(--invitation-background); }
            .invitation-shell { max-width: none; }
        }
    </style>
</head>
<body id="top" class="min-h-screen invitation-page" style="font-family: var(--invitation-font)">
    <div class="invitation-shell mx-auto min-h-screen w-full overflow-x-hidden" style="background: {{ $invitationBackground }}">
        @php
            $config = $schema['config'] ?? [];
            $sections = collect($config['sections'] ?? []);
            $hero = $sections->firstWhere('key', 'hero')['settings'] ?? [];
            $eventInfo = $sections->firstWhere('key', 'event_info')['settings'] ?? [];
            $location = $sections->firstWhere('key', 'location')['settings'] ?? [];
            $rsvp = $sections->firstWhere('key', 'rsvp')['settings'] ?? [];
            $qr = $sections->firstWhere('key', 'qr')['settings'] ?? [];
            $gallery = $sections->firstWhere('key', 'gallery')['settings'] ?? [];
            $heroMedia = $config['media']['hero'] ?? [];
            $eventConfig = $config['event'] ?? [];
            $buttons = $config['buttons'] ?? [];
            $floatingImages = $config['floating_images'] ?? [];
        @endphp
        <main class="px-0 pb-24 pt-0">
            @if (in_array('hero', $schema['sections'], true))
                <section id="cover" data-decorations="{{ $decorations['enabled'] ? 'on' : 'off' }}" class="invitation-cover relative flex min-h-[100vh] flex-col overflow-hidden rounded-none">
                    @include('invitations.partials.floating-images', ['items' => $floatingImages['hero'] ?? []])
                    <div class="invitation-decoration -right-5 -top-4 size-20 bg-[var(--invitation-primary)]"></div>
                    <div class="invitation-decoration right-5 top-9 size-14 bg-sky-500"></div>
                    <div class="invitation-decoration right-14 top-[4.5rem] size-10 bg-sky-300"></div>
                    <div class="invitation-decoration -left-5 top-36 size-16 bg-sky-500"></div>
                    <div class="invitation-decoration left-8 top-48 size-10 bg-sky-300"></div>
                    <div class="invitation-decoration -right-3 bottom-64 size-12 bg-sky-500"></div>
                    <div class="invitation-decoration right-10 bottom-56 size-14 bg-sky-300"></div>
                    <div class="invitation-decoration -left-5 bottom-24 size-14 bg-sky-500"></div>
                    @foreach ($backgroundElements as $element)
                        @php
                            $elementType = $element['type'] ?? 'rectangle';
                            $elementStyle = implode(';', [
                                'left: '.(float) ($element['x'] ?? 0).'%',
                                'top: '.(float) ($element['y'] ?? 0).'%',
                                'width: '.(float) ($element['width'] ?? 10).'%',
                                'height: '.(float) ($element['height'] ?? 10).'%',
                                'background: '.($element['fill'] ?? '#168DE2'),
                                'opacity: '.((float) ($element['opacity'] ?? 100) / 100),
                                'border-radius: '.(float) ($element['radius'] ?? 0).'px',
                                'transform: rotate('.(float) ($element['rotation'] ?? 0).'deg)',
                            ]);
                        @endphp
                        @if ($elementType === 'image' && filled($element['url'] ?? null))
                            <img src="{{ $element['url'] }}" alt="" class="invitation-background-element object-cover" style="{{ $elementStyle }}">
                        @else
                            <div class="invitation-background-element invitation-background-element--{{ $elementType }}" style="{{ $elementStyle }}"></div>
                        @endif
                    @endforeach
                    <div class="flex items-start px-8 pt-6"><span class="text-2xl font-black tracking-tight">ARC</span></div>
                    <div class="relative mx-5 mt-20 px-3 text-center">
                        <p class="text-sm font-medium">{{ $event->name }}</p>
                        <h1 class="mt-1 font-serif text-3xl font-bold leading-tight">{{ $hero['title'] ?: 'Nama Acaranya Entah Apa' }}</h1>
                        @if (filled($hero['subtitle'] ?? null))<p class="mt-1 text-base">{{ $hero['subtitle'] }}</p>@else<p class="mt-1 text-base">tema membuat apapun jadi hebat</p>@endif
                    </div>
                    @if (filled($heroMedia['url'] ?? null))
                        <img class="mx-auto mt-8 size-60 rounded-full border-2 border-[var(--invitation-primary)] object-cover" style="object-position: {{ $heroMedia['position'] ?? 'center' }}" src="{{ $heroMedia['url'] }}" alt="{{ $heroMedia['alt'] ?: $event->name }}" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden')">
                        <div class="hidden mx-auto mt-8 size-60 rounded-full border-2 border-[var(--invitation-primary)] bg-sky-100"></div>
                    @else
                        <div class="mx-auto mt-8 size-60 rounded-full border-2 border-[var(--invitation-primary)] bg-[radial-gradient(circle_at_35%_30%,#ffffff_0_12%,transparent_13%),linear-gradient(145deg,#9acff0,#f4fbff_48%,#6ca8d2)]"></div>
                    @endif
                    <div class="mt-auto px-6 pb-5 text-center">
                        <p class="text-sm">{{ $hero['recipient_label'] ?? 'Kepada Yth.' }}</p>
                        <p class="font-serif text-xl font-bold">{{ $recipient->name }}</p>
                        <p class="text-sm">{{ $recipient->type === 'family_representative' ? 'Perwakilan keluarga' : 'Perwakilan / individual' }}</p>
                    </div>
                    <a href="#invitation-content" class="flex h-16 items-center justify-center gap-1 rounded-t-3xl rounded-b-none bg-[var(--invitation-primary)] text-sm font-semibold text-white"><i data-lucide="arrow-up-to-line" class="size-4"></i><span>Buka</span></a>
                </section>
            @endif

            <div id="invitation-content" class="invitation-info-page mt-0 space-y-6 px-4 py-8 sm:px-5">
                @if ($countdownEnabled && $eventStartsAt && data_get($config, 'layout') !== 'mobile_tabs')
                    <section class="rounded-3xl bg-[var(--invitation-primary)] p-6 text-center text-white"><p class="text-sm uppercase tracking-widest opacity-80">Menuju acara</p><p class="mt-3 text-3xl font-semibold" data-countdown>-- hari -- jam -- menit</p></section>
                @endif

                @if (in_array('event_info', $schema['sections'], true))
                    <section id="event-info" class="invitation-card relative overflow-hidden rounded-3xl bg-white p-6 sm:p-8">@include('invitations.partials.floating-images', ['items' => $floatingImages['event_info'] ?? []])
                        @if (($eventConfig['show_type'] ?? true) && ($eventInfo['show_type'] ?? true))<p class="text-sm font-semibold uppercase tracking-widest text-[var(--invitation-primary)]">{{ ucfirst($event->type) }}</p>@endif
                        <h2 class="mt-2 text-3xl font-semibold">{{ $eventInfo['title'] ?: $event->name }}</h2>
                        @if (($eventConfig['show_description'] ?? true) && ($eventInfo['show_description'] ?? true) && $event->description)<p class="mt-4 whitespace-pre-line leading-7 text-slate-600">{{ $event->description }}</p>@endif
                        <div class="mt-6 grid gap-4 sm:grid-cols-2"><div class="rounded-2xl bg-[var(--invitation-secondary)] p-4"><p class="text-sm text-slate-500">{{ $eventConfig['date_label'] ?? 'Tanggal' }}</p><p class="mt-1 font-semibold">{{ $event->event_date?->translatedFormat('l, d F Y') }}</p></div><div class="rounded-2xl bg-[var(--invitation-secondary)] p-4"><p class="text-sm text-slate-500">{{ $eventConfig['time_label'] ?? 'Waktu' }}</p><p class="mt-1 font-semibold">{{ $event->start_time?->format('H:i') ?? '-' }}{{ $event->end_time ? ' - '.$event->end_time->format('H:i') : '' }}</p></div></div>
                    </section>
                @endif

                @if (in_array('location', $schema['sections'], true) && ($event->location || $event->map_url))
                    <section class="invitation-card relative overflow-hidden rounded-3xl bg-white p-6 sm:p-8">@include('invitations.partials.floating-images', ['items' => $floatingImages['location'] ?? []])<h2 class="text-2xl font-semibold">{{ $location['title'] ?? 'Lokasi' }}</h2>@if ($event->location)<p class="mt-3 text-slate-600">{{ $event->location }}</p>@endif @if ($event->map_url && ($location['show_map_button'] ?? true))<a class="mt-5 inline-flex invitation-button rounded-full bg-[var(--invitation-primary)] px-5 py-3 text-sm font-semibold text-white" href="{{ $event->map_url }}" target="{{ $buttons['map']['target'] ?? '_blank' }}" rel="noreferrer">{{ $buttons['map']['label'] ?? 'Buka peta' }}</a>@endif</section>
                @endif

                @if (in_array('gallery', $schema['sections'], true) && $mediaFiles->isNotEmpty())
                    <section class="invitation-card relative overflow-hidden rounded-3xl bg-white p-6 sm:p-8">@include('invitations.partials.floating-images', ['items' => $floatingImages['gallery'] ?? []])<h2 class="text-2xl font-semibold">{{ $gallery['title'] ?? 'Galeri' }}</h2><div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach ($mediaFiles as $mediaFile)<div class="relative"><img class="aspect-square w-full rounded-2xl object-cover" src="{{ $mediaFile->url }}" alt="{{ $mediaFile->name }}" loading="lazy" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden')"><div class="hidden aspect-square place-items-center rounded-2xl bg-[var(--invitation-secondary)] px-3 text-center text-xs text-slate-500">Gambar tidak tersedia.</div></div>@endforeach</div></section>
                @endif

                @if (in_array('speakers', $schema['sections'], true) && filled(data_get($sections->firstWhere('key', 'speakers'), 'settings.items')))
                    <section class="invitation-card rounded-3xl bg-white p-6 sm:p-8"><h2 class="text-2xl font-semibold">{{ data_get($sections->firstWhere('key', 'speakers'), 'settings.title', 'Narasumber') }}</h2><div class="mt-5 grid gap-4 sm:grid-cols-2">@foreach (data_get($sections->firstWhere('key', 'speakers'), 'settings.items', []) as $speaker)<article class="rounded-2xl border border-slate-200 p-4">@if (filled($speaker['image']['url'] ?? null))<img class="aspect-square w-full rounded-xl object-cover" src="{{ $speaker['image']['url'] }}" alt="{{ $speaker['image']['alt'] ?? ($speaker['name'] ?? 'Narasumber') }}">@endif<h3 class="mt-4 font-semibold">{{ $speaker['name'] ?? '' }}</h3>@if (filled($speaker['role'] ?? null))<p class="mt-1 text-sm text-slate-500">{{ $speaker['role'] }}</p>@endif @if (filled($speaker['description'] ?? null))<p class="mt-3 text-sm leading-6 text-slate-600">{{ $speaker['description'] }}</p>@endif</article>@endforeach</div></section>
                @endif

                @if (in_array('rsvp', $schema['sections'], true))
                    <section id="rsvp" class="invitation-card relative overflow-hidden rounded-3xl bg-white p-6 text-center sm:p-8">@include('invitations.partials.floating-images', ['items' => $floatingImages['rsvp'] ?? []])<h2 class="text-2xl font-semibold">{{ $rsvp['title'] ?? ($config['rsvp']['title'] ?? 'Konfirmasi kehadiran') }}</h2><p class="mt-2 text-slate-600">{{ $config['rsvp']['description'] ?? 'Silakan konfirmasi kehadiran melalui formulir.' }}</p><a href="{{ route('invitations.rsvp.form', $token) }}" class="mt-5 inline-flex invitation-button rounded-full bg-[var(--invitation-primary)] px-6 py-3 font-semibold text-white">{{ $buttons['rsvp']['label'] ?? 'Isi RSVP' }}</a></section>
                @endif

                @if (in_array('qr', $schema['sections'], true) && $qrCode?->status === \App\Models\QrCode::STATUS_ACTIVE)
                    <section class="invitation-card rounded-3xl bg-white p-6 text-center sm:p-8"><h2 class="text-2xl font-semibold">{{ $qr['title'] ?? ($config['qr']['title'] ?? 'QR Check-in') }}</h2><p class="mt-2 text-slate-600">{{ $config['qr']['description'] ?? 'Tunjukkan QR ini saat datang ke acara.' }}</p><div class="mx-auto mt-5 max-w-xs rounded-2xl border border-slate-200 p-4">{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(240)->margin(2)->generate(url('/checkin/scan/'.$qrCode->token_hash)) !!}</div>@if ($config['qr']['allow_download'] ?? true)<a class="mt-5 inline-flex rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold" href="data:image/svg+xml;base64,{{ base64_encode((string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(600)->margin(2)->generate(url('/checkin/scan/'.$qrCode->token_hash))) }}" download="qr-checkin-{{ $recipient->name }}.svg">{{ $buttons['qr_download']['label'] ?? 'Download QR' }}</a>@endif</section>
                @endif

                @if (in_array('guestbook', $schema['sections'], true))
                    <section id="guestbook" class="invitation-card relative overflow-hidden rounded-3xl bg-white p-6 sm:p-8">@include('invitations.partials.floating-images', ['items' => $floatingImages['guestbook'] ?? []])<h2 class="text-2xl font-semibold">Guestbook</h2><p class="mt-2 text-slate-600">Tinggalkan ucapan untuk acara ini.</p><livewire:guestbook.form :token="$token" /></section>
                @endif

                @if (in_array('contact', $schema['sections'], true) && ($event->contact_name || $event->contact_phone))
                    <section id="contact" class="invitation-card rounded-3xl bg-white p-6 text-center"><p class="text-sm text-slate-500">Kontak</p><p class="mt-1 font-semibold">{{ $event->contact_name }}</p>@if ($event->contact_phone)<p class="mt-1 text-slate-600">{{ $event->contact_phone }}</p>@endif</section>
                @endif
            </div>
        </main>
        <nav class="invitation-bottom-nav invisible pointer-events-none opacity-0 px-3 py-2" aria-label="Navigasi undangan"><div class="text-center text-[10px] font-medium">
            
                <a href="#top" data-nav-link class="rounded-2xl px-2 py-2 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><i data-lucide="house" class="mx-auto size-6" style="color: var(--invitation-nav-icon)"></i><span class="mt-1 block">Opening</span></a>
                <a href="#event-info" data-nav-link class="rounded-2xl px-2 py-2 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><i data-lucide="user-round" class="mx-auto size-6" style="color: var(--invitation-nav-icon)"></i><span class="mt-1 block">Profil</span></a>
                <a href="#event-info" data-nav-link class="rounded-2xl px-2 py-2 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><i data-lucide="calendar-days" class="mx-auto size-6" style="color: var(--invitation-nav-icon)"></i><span class="mt-1 block">Event</span></a>
                <a href="#rsvp" data-nav-link class="rounded-2xl px-2 py-2 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><i data-lucide="message-circle" class="mx-auto size-6" style="color: var(--invitation-nav-icon)"></i><span class="mt-1 block">RSVP</span></a>
                <a href="#location" data-nav-link class="rounded-2xl px-2 py-2 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><i data-lucide="map-pin" class="mx-auto size-6" style="color: var(--invitation-nav-icon)"></i><span class="mt-1 block">Maps</span></a><a href="#gallery" data-nav-link class="rounded-2xl px-2 py-2 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><i data-lucide="images" class="mx-auto size-6" style="color: var(--invitation-nav-icon)"></i><span class="mt-1 block">Gallery</span></a><a href="#guestbook" data-nav-link class="rounded-2xl px-2 py-2 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><i data-lucide="book-open" class="mx-auto size-6" style="color: var(--invitation-nav-icon)"></i><span class="mt-1 block">Guestbook</span></a><a href="#contact" data-nav-link class="rounded-2xl px-2 py-2 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><i data-lucide="phone" class="mx-auto size-6" style="color: var(--invitation-nav-icon)"></i><span class="mt-1 block">Kontak</span></a>
            </div>
        </nav>
    </div>
    @if ($countdownEnabled && $eventStartsAt && data_get($config, 'layout') !== 'mobile_tabs')
        <script>
            const countdownTarget = new Date(@js($eventStartsAt)).getTime();
            const countdownElement = document.querySelector('[data-countdown]');
            const updateCountdown = () => {
                if (!countdownElement) return;
                const distance = countdownTarget - Date.now();
                if (distance <= 0) { countdownElement.textContent = 'Acara sedang berlangsung'; return; }
                const days = Math.floor(distance / 86400000);
                const hours = Math.floor((distance % 86400000) / 3600000);
                const minutes = Math.floor((distance % 3600000) / 60000);
                countdownElement.textContent = `${days} hari ${hours} jam ${minutes} menit`;
            };
            updateCountdown();
            setInterval(updateCountdown, 60000);
        </script>
    @endif
    <script>
        (() => {
            const cover = document.querySelector('#cover');
            const nav = document.querySelector('.invitation-bottom-nav');
            const navLinks = [...document.querySelectorAll('[data-nav-link]')];
            navLinks[0]?.classList.add('is-active');
            const scrollToActive = (link, index) => {
                const firstFour = index < 4;
                const maxScroll = Math.max(0, nav.scrollWidth - nav.clientWidth);
                const centered = link.offsetLeft - (nav.clientWidth - link.offsetWidth) / 2;
                nav.scrollTo({ left: firstFour ? 0 : Math.min(maxScroll, Math.max(0, centered)), behavior: 'smooth' });
            };
            navLinks.forEach((link, index) => link.addEventListener('click', () => {
                navLinks.forEach((item) => item.classList.remove('is-active'));
                link.classList.add('is-active');
                scrollToActive(link, index);
            }));
            const showMenu = () => {
                const visible = window.scrollY > Math.max(240, (cover?.offsetHeight ?? 600) * 0.8);
                nav?.classList.toggle('invisible', !visible);
                nav?.classList.toggle('opacity-0', !visible);
                nav?.classList.toggle('pointer-events-none', !visible);
            };
            window.addEventListener('scroll', showMenu, { passive: true });
            showMenu();
        })();
    </script>
    @livewireScripts
</body>
</html>








