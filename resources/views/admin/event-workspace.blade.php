<x-layouts.admin :title="$event->name">
    <div class="mx-auto max-w-7xl space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600">Event workspace</p>
                <h1 class="mt-2 text-3xl font-semibold text-brand-950">{{ $event->name }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $event->event_date?->format('d M Y') }} · {{ $event->location ?: 'Lokasi belum diisi' }}</p>
            </div>
            <a href="{{ route('admin.events.edit', $event) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700">Edit event</a>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-3xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Recipients</p><p class="mt-2 text-3xl font-semibold text-brand-950">{{ $event->recipients()->count() }}</p></article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">RSVP</p><p class="mt-2 text-3xl font-semibold text-brand-950">{{ $event->invitations()->whereHas('rsvp')->count() }}</p></article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Guestbook</p><p class="mt-2 text-3xl font-semibold text-brand-950">{{ $event->guestbookEntries()->count() }}</p></article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Media</p><p class="mt-2 text-3xl font-semibold text-brand-950">{{ $event->mediaFiles()->count() }}</p></article>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-xl font-semibold text-brand-950">Undangan event</h2><p class="mt-1 text-sm text-slate-500">Template yang dipakai event ini.</p></div>
                <a href="{{ route('admin.events.invitation-editor', $event) }}" class="rounded-xl bg-brand-950 px-4 py-2.5 text-sm font-semibold text-white">Atur invitation</a>
            </div>
            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                @forelse ($event->templates as $template)
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="font-semibold text-brand-950">{{ $template->name }}</p><p class="mt-1 text-sm text-slate-500">/{{ $template->slug }}</p></div><span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Aktif</span></div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada template yang dipilih untuk event ini.</p>
                @endforelse
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['admin.events.recipients', 'Recipients'], ['admin.events.rsvp', 'RSVP'], ['admin.events.guestbook', 'Guestbook'], ['admin.events.media', 'Media'], ['admin.events.qr-codes', 'QR Check-in'], ['admin.events.checkins', 'Check-in']] as [$route, $label])
                <a href="{{ route($route, $event) }}" class="rounded-2xl border border-slate-200 bg-white p-5 font-semibold text-brand-950 transition hover:border-brand-300 hover:bg-brand-50">{{ $label }}<span class="mt-2 block text-sm font-normal text-slate-500">Buka data {{ strtolower($label) }}</span></a>
            @endforeach
        </section>
    </div>
</x-layouts.admin>