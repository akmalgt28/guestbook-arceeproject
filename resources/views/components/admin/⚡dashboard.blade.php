<?php

use App\Models\Checkin;
use App\Models\Event;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        $accessibleEvents = $this->accessibleEvents();
        $eventIds = $accessibleEvents->select('id');
        $checkinQuery = Checkin::query()->whereIn('event_id', $eventIds)->where('status', Checkin::STATUS_ACCEPTED);
        return [
            'stats' => [
                'events' => (clone $accessibleEvents)->count(),
                'recipients' => Recipient::query()->whereIn('event_id', $eventIds)->count(),
                'checkins' => (clone $checkinQuery)->count(),
                'checkedInGuests' => (clone $checkinQuery)->sum('actual_attendee_count'),
            ],
            'upcomingEvents' => $accessibleEvents->published()->whereDate('event_date', '>=', today())->orderBy('event_date')->limit(5)->get(),
        ];
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();
        return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey()))));
    }
};
?>

<div class="space-y-8">
    <div><h1 class="text-3xl font-semibold text-brand-950">Dashboard</h1></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Event', $stats['events'], 'text-brand-700'], ['Penerima', $stats['recipients'], 'text-sky-700'], ['Guestbook', $stats['checkins'], 'text-cyan-700'], ['Tamu hadir', $stats['checkedInGuests'], 'text-indigo-700']] as [$label, $value, $color])
            <div class="rounded-3xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold {{ $color }}">{{ $label }}</p><p class="mt-5 text-3xl font-semibold text-brand-950">{{ number_format($value) }}</p></div>
        @endforeach
    </div>
    <section class="rounded-3xl border border-slate-200 bg-white p-6"><div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-semibold text-brand-950">Event mendatang</h2><p class="mt-1 text-sm text-slate-500">Event published yang akan datang.</p></div><a href="{{ route('admin.events') }}" class="text-sm font-semibold text-brand-700">Kelola event</a></div><div class="mt-6 divide-y divide-slate-100">@forelse ($upcomingEvents as $event)<div class="flex items-center justify-between gap-4 py-4"><div><p class="font-semibold text-slate-900">{{ $event->name }}</p><p class="mt-1 text-sm text-slate-500">{{ $event->location ?: 'Lokasi belum diisi' }}</p></div><time class="text-sm font-semibold text-slate-600">{{ $event->event_date?->format('d M Y') }}</time></div>@empty<p class="py-6 text-sm text-slate-500">Belum ada event mendatang.</p>@endforelse</div></section>
</div>
