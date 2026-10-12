<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportExport;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\GuestbookEntry;
use App\Models\Recipient;
use App\Models\ReportExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    private const TYPES = ['recipient', 'checkin', 'guestbook', 'summary'];

    public function index(): View
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        return view('admin.reports', [
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
            'exports' => ReportExport::query()->when(! auth()->user()->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where('user_id', auth()->id()))->latest()->limit(10)->get(),
        ]);
    }

    public function queue(Request $request, string $type): RedirectResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);
        abort_unless(in_array($type, self::TYPES, true), 404);

        $eventId = $this->validatedEventId($request);
        $reportExport = ReportExport::query()->create([
            'user_id' => auth()->id(),
            'event_id' => $eventId,
            'type' => $type,
            'status' => ReportExport::STATUS_PENDING,
            'filename' => 'guestbook-'.$type.'-'.now()->format('Ymd-His').'-'.auth()->id().'.csv',
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
        abort_unless(in_array($type, self::TYPES, true), 404);
        $eventId = $this->validatedEventId($request);

        return response()->streamDownload(function () use ($type, $eventId): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $this->headers($type), ',', '"', chr(92));
            $this->writeRows($stream, $type, $eventId);
            fclose($stream);
        }, 'guestbook-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedEventId(Request $request): ?int
    {
        $eventId = $request->validate(['event_id' => ['nullable', 'integer', 'exists:events,id']])['event_id'] ?? null;
        if ($eventId !== null) {
            abort_unless($this->accessibleEvents()->whereKey($eventId)->exists(), 403);
        }

        return $eventId;
    }

    /** @return array<int, string> */
    private function headers(string $type): array
    {
        return match ($type) {
            'recipient' => ['ID', 'Event', 'Nama', 'Instansi', 'Tipe', 'Telepon', 'Email', 'Jumlah Tamu', 'Catatan'],
            'checkin' => ['ID', 'Event', 'Nama', 'Metode', 'Status', 'Jumlah Aktual', 'Petugas', 'Waktu Check-in', 'Catatan'],
            'guestbook' => ['ID', 'Event', 'Nama', 'Ucapan', 'Status', 'Waktu Dibuat', 'Waktu Terbit'],
            default => ['Event', 'Penerima', 'Check-in', 'Tamu Check-in'],
        };
    }

    /** @param resource $stream */
    private function writeRows($stream, string $type, ?int $eventId): void
    {
        match ($type) {
            'recipient' => Recipient::query()->whereIn('event_id', $this->accessibleEvents()->select('id'))->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with('event')->lazyById(500)->each(function (Recipient $recipient) use ($stream): void {
                fputcsv($stream, [$recipient->id, $recipient->event->name, $recipient->name, $recipient->organization, $recipient->type, $recipient->phone, $recipient->email, $recipient->invited_guest_count, $recipient->notes], ',', '"', chr(92));
            }),
            'checkin' => Checkin::query()->whereIn('event_id', $this->accessibleEvents()->select('id'))->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with(['event', 'recipient', 'scanner'])->lazyById(500)->each(function (Checkin $checkin) use ($stream): void {
                fputcsv($stream, [$checkin->id, $checkin->event->name, $checkin->recipient?->name, $checkin->method, $checkin->status, $checkin->actual_attendee_count, $checkin->scanner?->name, $checkin->checked_in_at?->toDateTimeString(), $checkin->notes], ',', '"', chr(92));
            }),
            'guestbook' => GuestbookEntry::query()->whereIn('event_id', $this->accessibleEvents()->select('id'))->when($eventId, fn (Builder $query): Builder => $query->where('event_id', $eventId))->with('event')->lazyById(500)->each(function (GuestbookEntry $entry) use ($stream): void {
                fputcsv($stream, [$entry->id, $entry->event->name, $entry->name, $entry->message, $entry->status, $entry->created_at?->toDateTimeString(), $entry->published_at?->toDateTimeString()], ',', '"', chr(92));
            }),
            default => $this->writeSummary($stream, $eventId),
        };
    }

    /** @param resource $stream */
    private function writeSummary($stream, ?int $eventId): void
    {
        foreach ($this->accessibleEvents()->when($eventId, fn (Builder $query): Builder => $query->whereKey($eventId))->cursor() as $event) {
            $checkins = Checkin::query()->where('event_id', $event->id)->where('status', Checkin::STATUS_ACCEPTED);
            fputcsv($stream, [$event->name, $event->recipients()->count(), (clone $checkins)->count(), (clone $checkins)->sum('actual_attendee_count')]);
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
