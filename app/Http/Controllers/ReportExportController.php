<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportExport;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\GuestbookEntry;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\ReportExport;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        return view('admin.reports', ['events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'), 'exports' => ReportExport::query()->when(! auth()->user()->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where('user_id', auth()->id()))->latest()->limit(10)->get()]);
    }

    public function queue(Request $request, string $type): RedirectResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);
        abort_unless(in_array($type, ['recipient', 'rsvp', 'checkin', 'guestbook', 'summary'], true), 404);

        $validated = $request->validate(['event_id' => ['nullable', 'integer', 'exists:events,id']]);
        $eventId = $validated['event_id'] ?? null;
        if ($eventId !== null) {
            abort_unless($this->accessibleEvents()->whereKey($eventId)->exists(), 403);
        }

        $filename = 'guestbook-'.$type.'-'.now()->format('Ymd-His').'-'.auth()->id().'.csv';
        $reportExport = ReportExport::query()->create([
            'user_id' => auth()->id(),
            'event_id' => $eventId,
            'type' => $type,
            'status' => ReportExport::STATUS_PENDING,
            'filename' => $filename,
        ]);
        GenerateReportExport::dispatch($reportExport);

        return back()->with('success', 'Export masuk antrean. Download tersedia setelah proses selesai.');
    }

    public function download(ReportExport $reportExport): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);
        abort_unless(auth()->user()->hasRole('Super Admin') || $reportExport->user_id === auth()->id(), 403);
        abort_unless($reportExport->status === ReportExport::STATUS_COMPLETED && $reportExport->file_path && \Storage::disk('local')->exists($reportExport->file_path), 404);

        return \Storage::disk('local')->download($reportExport->file_path, $reportExport->filename);
    }

    public function export(Request $request, string $type)
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);
        abort_unless(in_array($type, ['recipient', 'rsvp', 'checkin', 'guestbook', 'summary'], true), 404);

        $validated = $request->validate(['event_id' => ['nullable', 'integer', 'exists:events,id']]);
        $eventId = $validated['event_id'] ?? null;
        if ($eventId !== null) {
            abort_unless($this->accessibleEvents()->whereKey($eventId)->exists(), 403);
        }

        return response()->streamDownload(function () use ($type, $eventId): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $this->headers($type), ',', '"', chr(92));
            $this->writeRows($stream, $type, $eventId);
            fclose($stream);
        }, 'guestbook-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<int, string> */
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

    /** @param resource $stream */
    private function writeRows($stream, string $type, ?int $eventId): void
    {
        match ($type) {
            'recipient' => $this->writeRecipients($stream, $eventId),
            'rsvp' => $this->writeRsvps($stream, $eventId),
            'checkin' => $this->writeCheckins($stream, $eventId),
            'guestbook' => $this->writeGuestbook($stream, $eventId),
            'summary' => $this->writeSummary($stream, $eventId),
        };
    }

    private function writeRecipients($stream, ?int $eventId): void
    {
        Recipient::query()->whereIn('event_id', $this->accessibleEvents()->select('id'))->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with('event')->lazyById(500)->each(function (Recipient $recipient) use ($stream): void {
            fputcsv($stream, [$recipient->id, $recipient->event->name, $recipient->name, $recipient->type, $recipient->phone, $recipient->email, $recipient->invited_guest_count, $recipient->notes], ',', '"', chr(92));
        });
    }

    private function writeRsvps($stream, ?int $eventId): void
    {
        Rsvp::query()->whereHas('invitation', fn (Builder $query): Builder => $query->whereIn('event_id', $this->accessibleEvents()->select('id'))->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId)))->with(['invitation.event', 'invitation.recipient'])->lazyById(500)->each(function (Rsvp $rsvp) use ($stream): void {
            fputcsv($stream, [$rsvp->id, $rsvp->invitation->event->name, $rsvp->invitation->recipient->name, $rsvp->status, $rsvp->attending_count, $rsvp->responded_at?->toDateTimeString(), $rsvp->notes], ',', '"', chr(92));
        });
    }

    private function writeCheckins($stream, ?int $eventId): void
    {
        Checkin::query()->whereIn('event_id', $this->accessibleEvents()->select('id'))->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with(['event', 'recipient', 'scanner'])->lazyById(500)->each(function (Checkin $checkin) use ($stream): void {
            fputcsv($stream, [$checkin->id, $checkin->event->name, $checkin->recipient->name, $checkin->method, $checkin->status, $checkin->actual_attendee_count, $checkin->scanner->name, $checkin->checked_in_at?->toDateTimeString(), $checkin->notes], ',', '"', chr(92));
        });
    }

    private function writeGuestbook($stream, ?int $eventId): void
    {
        GuestbookEntry::query()->whereIn('event_id', $this->accessibleEvents()->select('id'))->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with('event')->lazyById(500)->each(function (GuestbookEntry $entry) use ($stream): void {
            fputcsv($stream, [$entry->id, $entry->event->name, $entry->name, $entry->message, $entry->status, $entry->created_at?->toDateTimeString(), $entry->published_at?->toDateTimeString()], ',', '"', chr(92));
        });
    }

    private function writeSummary($stream, ?int $eventId): void
    {
        $events = $this->accessibleEvents()->when($eventId, fn (Builder $query): Builder => $query->whereKey($eventId))->cursor();
        foreach ($events as $event) {
            $rsvps = Rsvp::query()->whereHas('invitation', fn (Builder $query): Builder => $query->where('event_id', $event->id));
            $checkins = Checkin::query()->where('event_id', $event->id)->where('status', Checkin::STATUS_ACCEPTED);
            fputcsv($stream, [$event->name, $event->recipients()->count(), (clone $rsvps)->responded()->count(), (clone $rsvps)->where('status', Rsvp::STATUS_ATTENDING)->sum('attending_count'), (clone $rsvps)->where('status', Rsvp::STATUS_NOT_ATTENDING)->count(), Invitation::query()->where('event_id', $event->id)->where(function (Builder $query): void {
                $query->whereDoesntHave('rsvp')->orWhereHas('rsvp', fn (Builder $query): Builder => $query->where('status', Rsvp::STATUS_PENDING));
            })->count(), (clone $checkins)->count(), (clone $checkins)->sum('actual_attendee_count')]);
        }
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();

        return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
            $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey()));
        }));
    }
}
