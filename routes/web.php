<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\PublicInvitationController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ScannerController;
use App\Models\Event;
use App\Models\InvitationTemplate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invitation/{eventSlug}', [PublicInvitationController::class, 'preview'])
    ->where('eventSlug', '[A-Za-z0-9-]+')
    ->name('invitations.preview');

Route::get('/invite/{token}', [PublicInvitationController::class, 'show'])
    ->middleware('throttle:60,1')
    ->where('token', '[A-Za-z0-9-]+')
    ->name('invitations.show');

Route::get('/invite/{token}/rsvp', [PublicInvitationController::class, 'rsvpForm'])
    ->middleware('throttle:60,1')
    ->where('token', '[A-Za-z0-9-]+')
    ->name('invitations.rsvp.form');

Route::post('/invite/{token}/rsvp', [PublicInvitationController::class, 'submitRsvp'])
    ->middleware('throttle:10,1')
    ->where('token', '[A-Za-z0-9-]+')
    ->name('invitations.rsvp.submit');

Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
Route::post('/logout', [AdminAuthController::class, 'logout'])->middleware('auth')->name('logout');

$resolveAccessibleEvent = static function (Event $event): Event {
    $user = auth()->user();

    abort_unless(
        $user->hasRole('Super Admin') || $event->created_by === $user->getKey() || $event->admins()->whereKey($user->getKey())->exists(),
        403,
    );

    return $event;
};
Route::get('/admin', fn () => view('admin.dashboard'))->middleware('auth')->name('admin.dashboard');

Route::get('/admin/users', fn () => view('admin.users'))->middleware('auth')->name('admin.users');
Route::get('/admin/users/create', fn () => view('admin.user-form', ['userId' => null]))->middleware('auth')->name('admin.users.create');
Route::get('/admin/users/{user}/edit', fn (int $user) => view('admin.user-form', ['userId' => $user]))->middleware('auth')->whereNumber('user')->name('admin.users.edit');

Route::get('/admin/events', fn () => view('admin.events'))->middleware('auth')->name('admin.events');
Route::get('/admin/events/create', fn () => view('admin.event-form', ['eventId' => null]))->middleware('auth')->name('admin.events.create');
Route::get('/admin/events/{event}/edit', fn (Event $event) => view('admin.event-form', ['eventId' => $event->getKey()]))->middleware('auth')->whereNumber('event')->name('admin.events.edit');
Route::get('/admin/events/{event}/invitation-editor', fn (Event $event) => view('admin.event-invitation-editor', ['eventId' => $event->getKey()]))->middleware('auth')->whereNumber('event')->name('admin.events.invitation-editor');
Route::get('/admin/events/{event}/invitation-preview', function (Event $event) use ($resolveAccessibleEvent) {
    $event = $resolveAccessibleEvent($event);
    $template = $event->templates()->first();
    abort_if($template === null, 404);

    $pivotSettings = $template->pivot?->settings;
    if (! is_array($pivotSettings)) {
        $pivotSettings = json_decode((string) $pivotSettings, true) ?: [];
    }

    $schema = InvitationTemplate::mergedSchema($template->schema ?? [], $pivotSettings);

    return view('invitations.preview', ['event' => $event, 'schema' => $schema, 'backgroundOnly' => true]);
})->middleware('auth')->whereNumber('event')->name('admin.events.invitation-preview');
Route::get('/admin/events/{event}/overview', function (Event $event) use ($resolveAccessibleEvent) {
    return view('admin.event-workspace', ['event' => $resolveAccessibleEvent($event)]);
})->middleware('auth')->whereNumber('event')->name('admin.events.overview');

Route::get('/admin/events/{event}/invitation', fn (Event $event) => redirect()->route('admin.events.invitation-editor', $event))->middleware('auth')->whereNumber('event')->name('admin.events.invitation');
Route::get('/admin/events/{event}/recipients', fn (Event $event) => view('admin.recipients', ['eventId' => $resolveAccessibleEvent($event)->getKey()]))->middleware('auth')->whereNumber('event')->name('admin.events.recipients');
Route::get('/admin/events/{event}/rsvp', fn (Event $event) => view('admin.rsvps', ['eventId' => $resolveAccessibleEvent($event)->getKey()]))->middleware('auth')->whereNumber('event')->name('admin.events.rsvp');
Route::get('/admin/events/{event}/guestbook', fn (Event $event) => view('admin.guestbook', ['eventId' => $resolveAccessibleEvent($event)->getKey()]))->middleware('auth')->whereNumber('event')->name('admin.events.guestbook');
Route::get('/admin/events/{event}/media', fn (Event $event) => view('admin.media', ['eventId' => $resolveAccessibleEvent($event)->getKey()]))->middleware('auth')->whereNumber('event')->name('admin.events.media');
Route::get('/admin/events/{event}/qr-codes', fn (Event $event) => view('admin.qr-codes', ['eventId' => $resolveAccessibleEvent($event)->getKey()]))->middleware('auth')->whereNumber('event')->name('admin.events.qr-codes');
Route::get('/admin/events/{event}/checkins', fn (Event $event) => view('admin.checkins', ['eventId' => $resolveAccessibleEvent($event)->getKey()]))->middleware('auth')->whereNumber('event')->name('admin.events.checkins');
Route::get('/admin/recipients', fn () => view('admin.recipients'))->middleware('auth')->name('admin.recipients');
Route::get('/admin/recipients/create', fn () => view('admin.recipient-form', ['recipientId' => null]))->middleware('auth')->name('admin.recipients.create');
Route::get('/admin/recipients/{recipient}/edit', fn (int $recipient) => view('admin.recipient-form', ['recipientId' => $recipient]))->middleware('auth')->whereNumber('recipient')->name('admin.recipients.edit');
Route::get('/admin/recipients/import', fn () => view('admin.recipient-import'))->middleware('auth')->name('admin.recipients.import');

Route::get('/admin/invitation-templates', fn () => view('admin.invitation-templates'))->middleware('auth')->name('admin.invitation-templates');
Route::get('/admin/invitation-templates/create', fn () => view('admin.invitation-template-form', ['templateId' => null]))->middleware('auth')->name('admin.invitation-templates.create');
Route::get('/admin/invitation-templates/{template}/edit', fn (int $template) => view('admin.invitation-template-form', ['templateId' => $template]))->middleware('auth')->whereNumber('template')->name('admin.invitation-templates.edit');

Route::get('/admin/media', fn () => view('admin.media'))->middleware('auth')->name('admin.media');
Route::get('/admin/media/upload', fn () => view('admin.media-upload'))->middleware('auth')->name('admin.media.upload');

Route::get('/admin/rsvps', fn () => view('admin.rsvps'))->middleware('auth')->name('admin.rsvps');
Route::get('/admin/rsvps/{rsvp}/edit', fn (int $rsvp) => view('admin.rsvp-form', ['rsvpId' => $rsvp]))->middleware('auth')->whereNumber('rsvp')->name('admin.rsvps.edit');

Route::get('/admin/qr-codes', fn () => view('admin.qr-codes'))->middleware('auth')->name('admin.qr-codes');
Route::get('/admin/qr-codes/create', fn () => view('admin.qr-code-form'))->middleware('auth')->name('admin.qr-codes.create');

Route::get('/admin/guestbook', fn () => view('admin.guestbook'))->middleware('auth')->name('admin.guestbook');

Route::get('/admin/checkins', fn () => redirect()->route('admin.events'))->middleware('auth')->name('admin.checkins');

Route::get('/admin/reports', [ReportExportController::class, 'index'])->middleware('auth')->name('reports.index');

Route::post('/admin/reports/queue/{type}', [ReportExportController::class, 'queue'])->middleware('auth')->where('type', 'recipient|rsvp|checkin|guestbook|summary')->name('reports.queue');

Route::get('/admin/reports/download/{reportExport}', [ReportExportController::class, 'download'])->middleware('auth')->name('reports.download');

Route::get('/admin/reports/export/{type}', [ReportExportController::class, 'export'])->middleware('auth')->where('type', 'recipient|rsvp|checkin|guestbook|summary')->name('reports.export');

Route::get('/scanner', [ScannerController::class, 'index'])
    ->middleware('auth')
    ->name('scanner.index');

Route::middleware('auth')->group(function (): void {
    Route::post('/checkin/scan', [CheckinController::class, 'scan'])->name('checkin.scan');
    Route::post('/checkin/confirm', [CheckinController::class, 'confirm'])->name('checkin.confirm');
});
