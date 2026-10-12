<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ScannerController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('checkins.create'), Response::HTTP_FORBIDDEN);

        $user = auth()->user();
        $events = Event::query()
            ->published()
            ->when(
                ! $user->hasRole('Super Admin'),
                fn (Builder $query): Builder => $query->whereHas(
                    'admins',
                    fn (Builder $query): Builder => $query->whereKey($user->getKey()),
                ),
            )
            ->orderBy('event_date')
            ->get(['id', 'name', 'event_date']);

        return view('scanner.index', compact('events'));
    }
}
