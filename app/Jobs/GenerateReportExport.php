<?php

namespace App\Jobs;

use App\Models\Checkin;
use App\Models\Event;
use App\Models\GuestbookEntry;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\ReportExport;
use App\Models\Rsvp;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public ReportExport $reportExport) {}

    public function handle(): void
    {
        $export = $this->reportExport->fresh();
        $user = User::query()->findOrFail($export->user_id);
        $events = Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
            $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey()));
        }));
        $eventIds = $events->select('id');
        $path = 'reports/'.$export->filename;
        $fullPath = Storage::disk('local')->path($path);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        $stream = fopen($fullPath, 'w');
        $export->update(['status' => ReportExport::STATUS_PROCESSING]);
        fputcsv($stream, $this->headers($export->type), ',', '"', chr(92));
        $this->writeRows($stream, $export->type, $export->event_id, $eventIds, $events);
        fclose($stream);
        $export->update(['status' => ReportExport::STATUS_COMPLETED, 'file_path' => $path, 'completed_at' => now(), 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->reportExport->fresh()?->update(['status' => ReportExport::STATUS_FAILED, 'error' => $exception?->getMessage()]);
    }

    private function headers(string $type): array
    {
        return match ($type) {
            'recipient' => ['ID', 'Event', 'Nama', 'Tipe', 'Telepon', 'Email', 'Jumlah Tamu Diundang', 'Catatan'],
            'rsvp' => ['ID', 'Event', 'Nama', 'Status', 'Jumlah Hadir', 'Waktu Respons', 'Catatan'],
            'checkin' => ['ID', 'Event', 'Nama', 'Metode', 'Status', 'Jumlah Aktual', 'Petugas', 'Waktu Check-in', 'Catatan'],
            'guestbook' => ['ID', 'Event', 'Nama', 'Ucapan', 'Status', 'Waktu Dibuat', 'Waktu Terbit'],
            default => ['Event', 'Penerima', 'RSVP', 'Hadir RSVP', 'Tidak Hadir', 'Belum Respons', 'Check-in', 'Tamu Check-in'],
        };
    }

    private function writeRows($stream, string $type, ?int $eventId, Builder $eventIds, Builder $events): void
    {
        if ($type === 'recipient') {
            Recipient::query()->whereIn('event_id', $eventIds)->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with('event')->lazyById(500)->each(fn (Recipient $row) => fputcsv($stream, [$row->id, $row->event->name, $row->name, $row->type, $row->phone, $row->email, $row->invited_guest_count, $row->notes]), ',', '"', chr(92));
        } elseif ($type === 'rsvp') {
            Rsvp::query()->whereHas('invitation', fn (Builder $query): Builder => $query->whereIn('event_id', $eventIds)->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId)))->with(['invitation.event', 'invitation.recipient'])->lazyById(500)->each(fn (Rsvp $row) => fputcsv($stream, [$row->id, $row->invitation->event->name, $row->invitation->recipient->name, $row->status, $row->attending_count, $row->responded_at?->toDateTimeString(), $row->notes]), ',', '"', chr(92));
        } elseif ($type === 'checkin') {
            Checkin::query()->whereIn('event_id', $eventIds)->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with(['event', 'recipient', 'scanner'])->lazyById(500)->each(fn (Checkin $row) => fputcsv($stream, [$row->id, $row->event->name, $row->recipient->name, $row->method, $row->status, $row->actual_attendee_count, $row->scanner->name, $row->checked_in_at?->toDateTimeString(), $row->notes]), ',', '"', chr(92));
        } elseif ($type === 'guestbook') {
            GuestbookEntry::query()->whereIn('event_id', $eventIds)->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with('event')->lazyById(500)->each(fn (GuestbookEntry $row) => fputcsv($stream, [$row->id, $row->event->name, $row->name, $row->message, $row->status, $row->created_at?->toDateTimeString(), $row->published_at?->toDateTimeString()]), ',', '"', chr(92));
        } else {
            foreach ($events->when($eventId, fn (Builder $query): Builder => $query->whereKey($eventId))->cursor() as $event) {
                $rsvps = Rsvp::query()->whereHas('invitation', fn (Builder $query): Builder => $query->where('event_id', $event->id));
                $checkins = Checkin::query()->where('event_id', $event->id)->where('status', Checkin::STATUS_ACCEPTED);
                fputcsv($stream, [$event->name, $event->recipients()->count(), (clone $rsvps)->responded()->count(), (clone $rsvps)->where('status', Rsvp::STATUS_ATTENDING)->sum('attending_count'), (clone $rsvps)->where('status', Rsvp::STATUS_NOT_ATTENDING)->count(), Invitation::query()->where('event_id', $event->id)->where(function (Builder $query): void {
                    $query->whereDoesntHave('rsvp')->orWhereHas('rsvp', fn (Builder $query): Builder => $query->where('status', Rsvp::STATUS_PENDING));
                })->count(), (clone $checkins)->count(), (clone $checkins)->sum('actual_attendee_count')]);
            }
        }
    }
}
