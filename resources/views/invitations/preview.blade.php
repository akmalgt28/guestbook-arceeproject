<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->name }} · Invitation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $theme = $schema['theme'];
        $activeBackground = collect($schema['backgrounds'] ?? [])->firstWhere('id', $schema['active_background_id'] ?? null);
        $backgroundElements = is_array($activeBackground) ? ($activeBackground['elements'] ?? []) : ($schema['background_elements'] ?? []);
        $previewBackground = is_array($activeBackground) ? ($activeBackground['color'] ?? $theme['background_color']) : $theme['background_color'];
    @endphp
    <style>
        :root { --preview-bg: {{ $theme['background_color'] }}; --preview-desktop-bg: {{ $theme['desktop_background_color'] ?? '#111827' }}; --preview-primary: {{ $theme['primary_color'] }}; --preview-text: {{ $theme['text_color'] }}; }
        html, body { min-height: 100%; }
        body { margin: 0; background: var(--preview-desktop-bg); color: var(--preview-text); }
        @media (max-width: 767px) {
            body { background: var(--preview-bg); }
        }
        .preview-element { position: absolute; pointer-events: none; z-index: 0; }
        .preview-element--ellipse { height: auto !important; aspect-ratio: 1 / 1; }
        .preview-bottom-nav { position: fixed; bottom: 0; left: 50%; z-index: 20; width: min(100%, 35rem); overflow-x: auto; scrollbar-width: none; transform: translateX(-50%); background: {{ data_get($schema, 'navigation.background_color', '#90CAF9') }}; color: {{ data_get($schema, 'navigation.text_color', '#5B5560') }}; }
        .preview-bottom-nav::-webkit-scrollbar { display: none; }
        .preview-bottom-nav { padding-top: 0.45rem; padding-bottom: 0.45rem; }
        .preview-bottom-nav > div { display: flex; min-width: max-content; gap: 0.25rem; }
        .preview-bottom-nav [data-preview-nav] { display: flex; aspect-ratio: 1 / 1; width: calc((min(100vw, 35rem) - 2.5rem) / 5); min-height: 4.25rem; flex: 0 0 calc((min(100vw, 35rem) - 2.5rem) / 5); margin: 0 auto; flex-direction: column; align-items: center; justify-content: center; }
        .preview-bottom-nav [data-preview-nav] span { font-size: 0.8rem; }
        .preview-bottom-nav [data-preview-nav] svg { width: 1.8rem; height: 1.8rem; }
        .preview-bottom-nav [data-preview-nav].is-active { background: {{ data_get($schema, 'navigation.active_color', '#0B4AA2') }}; color: {{ data_get($schema, 'navigation.icon_color', '#FFFFFF') }}; }
        
    </style>
</head>
<body>
    <main class="mx-auto min-h-screen w-full max-w-[35rem] overflow-hidden" style="background: {{ $previewBackground }}">
        <section class="relative flex min-h-screen flex-col overflow-hidden px-7 pb-10 pt-8">
            @foreach ($backgroundElements as $element)
                @php
                    $type = $element['type'] ?? 'rectangle';
                    $style = implode(';', [
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
                <div class="preview-element preview-element--{{ $type }}" style="{{ $style }}"></div>
            @endforeach
        </section>
        <nav class="preview-bottom-nav px-3 py-2" aria-label="Navigasi undangan"><div class="text-center text-[10px] font-medium"><a href="#top" data-preview-nav class="is-active rounded-2xl px-2 py-2 transition hover:bg-white/20"><i data-lucide="house" class="mx-auto size-6"></i><span class="mt-1 block">Opening</span></a><a href="#event-info" data-preview-nav class="rounded-2xl px-2 py-2 transition hover:bg-white/20"><i data-lucide="user-round" class="mx-auto size-6"></i><span class="mt-1 block">Profil</span></a><a href="#event-info" data-preview-nav class="rounded-2xl px-2 py-2 transition hover:bg-white/20"><i data-lucide="calendar-days" class="mx-auto size-6"></i><span class="mt-1 block">Event</span></a><a href="#rsvp" data-preview-nav class="rounded-2xl px-2 py-2 transition hover:bg-white/20"><i data-lucide="message-circle" class="mx-auto size-6"></i><span class="mt-1 block">RSVP</span></a><a href="#location" data-preview-nav class="rounded-2xl px-2 py-2 transition hover:bg-white/20"><i data-lucide="map-pin" class="mx-auto size-6"></i><span class="mt-1 block">Maps</span></a><a href="#gallery" data-preview-nav class="rounded-2xl px-2 py-2 transition hover:bg-white/20"><i data-lucide="images" class="mx-auto size-6"></i><span class="mt-1 block">Gallery</span></a><a href="#guestbook" data-preview-nav class="rounded-2xl px-2 py-2 transition hover:bg-white/20"><i data-lucide="book-open" class="mx-auto size-6"></i><span class="mt-1 block">Guestbook</span></a><a href="#contact" data-preview-nav class="rounded-2xl px-2 py-2 transition hover:bg-white/20"><i data-lucide="phone" class="mx-auto size-6"></i><span class="mt-1 block">Kontak</span></a></div></nav>
    </main>
    <script>(() => { const nav = document.querySelector(".preview-bottom-nav"); const links = [...document.querySelectorAll("[data-preview-nav]")]; const scrollToActive = (link, index) => { const firstFour = index < 4; const maxScroll = Math.max(0, nav.scrollWidth - nav.clientWidth); const centered = link.offsetLeft - (nav.clientWidth - link.offsetWidth) / 2; nav.scrollTo({ left: firstFour ? 0 : Math.min(maxScroll, Math.max(0, centered)), behavior: "smooth" }); }; links.forEach((link, index) => link.addEventListener("click", () => { links.forEach((item) => item.classList.remove("is-active")); link.classList.add("is-active"); scrollToActive(link, index); })); })();</script>
</body>
</html>