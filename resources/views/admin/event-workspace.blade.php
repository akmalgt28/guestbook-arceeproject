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
            <article class="rounded-3xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Pesan</p><p class="mt-2 text-3xl font-semibold text-brand-950">{{ $event->guestbookEntries()->count() }}</p></article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Media</p><p class="mt-2 text-3xl font-semibold text-brand-950">{{ $event->mediaFiles()->count() }}</p></article>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['admin.events.recipients', 'Recipients'], ['admin.events.guestbook', 'Pesan'], ['admin.events.media', 'Media'], ['admin.events.checkins', 'Guestbook']] as [$route, $label])
                <a href="{{ route($route, $event) }}" class="rounded-2xl border border-slate-200 bg-white p-5 font-semibold text-brand-950 transition hover:border-brand-300 hover:bg-brand-50">{{ $label }}<span class="mt-2 block text-sm font-normal text-slate-500">Buka data {{ strtolower($label) }}</span></a>
            @endforeach
        </section>
    </div>
</x-layouts.admin>