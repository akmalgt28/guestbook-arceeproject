<?php

use App\Models\Checkin;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        $accessibleEvents = $this->accessibleEvents();
        $eventIds = $accessibleEvents->select('id');
        $rsvpQuery = Rsvp::query()->whereHas('invitation', fn (Builder $query): Builder => $query->whereIn('event_id', $eventIds));
        $checkinQuery = Checkin::query()->whereIn('event_id', $eventIds)->where('status', Checkin::STATUS_ACCEPTED);

        $attendingGuests = (clone $rsvpQuery)->where('status', Rsvp::STATUS_ATTENDING)->sum('attending_count');
        $checkedInGuests = (clone $checkinQuery)->sum('actual_attendee_count');

        return [
            'stats' => [
                'events' => (clone $accessibleEvents)->count(),
                'recipients' => Recipient::query()->whereIn('event_id', $eventIds)->count(),
                'rsvps' => (clone $rsvpQuery)->responded()->count(),
                'attending' => $attendingGuests,
                'notAttending' => (clone $rsvpQuery)->where('status', Rsvp::STATUS_NOT_ATTENDING)->count(),
                'checkins' => (clone $checkinQuery)->count(),
                'checkedInGuests' => $checkedInGuests,
                'pending' => Invitation::query()->whereIn('event_id', $eventIds)->where(function (Builder $query): void {
                    $query->whereDoesntHave('rsvp')->orWhereHas('rsvp', fn (Builder $query): Builder => $query->where('status', Rsvp::STATUS_PENDING));
                })->count(),
                'attendanceRate' => $attendingGuests > 0 ? round(($checkedInGuests / $attendingGuests) * 100) : 0,
            ],
            'upcomingEvents' => $accessibleEvents->published()->whereDate('event_date', '>=', today())->orderBy('event_date')->limit(5)->get(),
        ];
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();

        return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
            $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey()));
        }));
    }
};
?>

    <div class="space-y-8">
        <div><h1 class="text-3xl font-semibold text-brand-950">Dashboard</h1></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Event', $stats['events'], 'text-brand-700'], ['Penerima', $stats['recipients'], 'text-sky-700'], ['RSVP', $stats['rsvps'], 'text-violet-700'], ['Belum respons', $stats['pending'], 'text-accent-600'], ['Hadir RSVP', $stats['attending'], 'text-emerald-700'], ['Tidak hadir', $stats['notAttending'], 'text-rose-700'], ['Check-in', $stats['checkins'], 'text-cyan-700'], ['Tamu check-in', $stats['checkedInGuests'], 'text-indigo-700']] as [$label, $value, $color])
                <div class="rounded-3xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold {{ $color }}">{{ $label }}</p><p class="mt-5 text-3xl font-semibold text-brand-950">{{ number_format($value) }}</p></div>
            @endforeach
        </div>
        <section class="grid gap-4 lg:grid-cols-[1fr_1.5fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6"><p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-600">Perbandingan</p><h2 class="mt-2 text-xl font-semibold text-brand-950">RSVP ke check-in</h2><p class="mt-2 text-sm text-slate-500">{{ $stats['checkedInGuests'] }} dari {{ $stats['attending'] }} tamu RSVP hadir sudah check-in.</p><div class="mt-5 h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ min(100, $stats['attendanceRate']) }}%"></div></div><p class="mt-3 text-2xl font-semibold text-brand-700">{{ $stats['attendanceRate'] }}%</p></div>
            <div class="rounded-3xl border border-slate-200 bg-white p-6"><div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-semibold text-brand-950">Event mendatang</h2><p class="mt-1 text-sm text-slate-500">Event published yang akan datang.</p></div><a href="{{ route('admin.events') }}" class="text-sm font-semibold text-brand-700">Kelola event</a></div><div class="mt-6 divide-y divide-slate-100">@forelse ($upcomingEvents as $event)<div class="flex items-center justify-between gap-4 py-4"><div><p class="font-semibold text-slate-900">{{ $event->name }}</p><p class="mt-1 text-sm text-slate-500">{{ $event->location ?: 'Lokasi belum diisi' }}</p></div><time class="text-sm font-semibold text-slate-600">{{ $event->event_date?->format('d M Y') }}</time></div>@empty<p class="py-6 text-sm text-slate-500">Belum ada event mendatang.</p>@endforelse</div></div>
        </section>
    </div>
