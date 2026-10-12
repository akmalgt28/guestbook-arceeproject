@php
    $icons = [
        'home' => '<i data-lucide="house" class="size-5"></i>',
        'calendar' => '<i data-lucide="calendar-days" class="size-5"></i>',
        'users' => '<i data-lucide="users" class="size-5"></i>',
        'image' => '<i data-lucide="image" class="size-5"></i>',
        'check' => '<i data-lucide="check" class="size-5"></i>',
        'qr' => '<i data-lucide="qr-code" class="size-5"></i>',
        'heart' => '<i data-lucide="heart" class="size-5"></i>',
        'check-circle' => '<i data-lucide="circle-check" class="size-5"></i>',
        'chart' => '<i data-lucide="chart-no-axes-column" class="size-5"></i>',
        'scan' => '<i data-lucide="scan-line" class="size-5"></i>',
    ];
    $navigation = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home'],
        ['label' => 'Events', 'route' => 'admin.events', 'icon' => 'calendar'],
        ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart'],
    ];
    $contextEvent = request()->route('event');
    $eventNavigation = [
        ['label' => 'Overview', 'route' => 'admin.events.overview', 'icon' => 'home'],
        ['label' => 'Recipients', 'route' => 'admin.events.recipients', 'icon' => 'users'],
        ['label' => 'Pesan', 'route' => 'admin.events.guestbook', 'icon' => 'heart'],
        ['label' => 'Media', 'route' => 'admin.events.media', 'icon' => 'image'],
        ['label' => 'Guestbook', 'route' => 'admin.events.checkins', 'icon' => 'check-circle'],
    ];
    $currentNavigation = collect($navigation)->first(fn (array $item): bool => request()->routeIs($item['route'], $item['route'].'.*'))
        ?? collect($eventNavigation)->first(fn (array $item): bool => request()->routeIs($item['route'], $item['route'].'.*'))
        ?? ['label' => 'Admin', 'route' => null, 'icon' => 'home'];
@endphp
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Admin' }} · {{ config('app.name', 'Guestbook') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>.admin-sidebar { scrollbar-width: none; -ms-overflow-style: none; } .admin-sidebar::-webkit-scrollbar { display: none; }</style>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900" x-data="{ sidebar: false, accountOpen: false }">
        <div x-show="sidebar" x-cloak x-on:click="sidebar = false" class="fixed inset-0 z-30 bg-brand-950/50 backdrop-blur-sm lg:hidden"></div>
        <div class="min-h-screen">
            <aside class="admin-sidebar fixed inset-y-0 left-0 z-40 flex h-screen w-64 -translate-x-full flex-col overflow-y-auto bg-brand-950 px-4 py-5 text-white transition-transform lg:translate-x-0" :class="sidebar ? 'translate-x-0' : ''">
                <div class="flex items-center justify-between px-2.5">
                    <a href="{{ route('admin.dashboard') }}" class="group flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-2xl bg-white text-base font-black text-brand-950">G</span>
                        <span><span class="block text-lg font-bold tracking-tight">Guestbook<span class="text-accent-500">.</span></span><span class="block text-[11px] font-medium uppercase tracking-[0.2em] text-brand-200">Event workspace</span></span>
                    </a>
                    <button x-on:click="sidebar = false" class="rounded-lg p-2 text-brand-200 hover:bg-white/10 lg:hidden" aria-label="Tutup menu"><i data-lucide="x" class="size-5"></i></button>
                </div>

                <nav class="mt-4 flex-1 space-y-1">
                    @foreach ($navigation as $item)
                        @php($active = request()->routeIs($item['route'], $item['route'].'.*'))
                        <a href="{{ route($item['route']) }}" @class([
                            'group flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition',
                            'bg-white text-brand-950' => $active,
                            'text-brand-100/75 hover:bg-white/10 hover:text-white' => ! $active,
                        ])>
                            <span @class(['grid size-7 place-items-center text-sm transition', 'text-brand-700' => $active, 'text-brand-200 group-hover:text-white' => ! $active])>{!! $icons[$item['icon']] !!}</span>
                            <span class="flex-1">{{ $item['label'] }}</span>
                            @if ($active)<span class="size-2 rounded-full bg-accent-500"></span>@endif
                        </a>
                    @endforeach
                    @if ($contextEvent)
                        <div class="mt-5 border-t border-white/10 px-3 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-300">Event aktif</div>
                        <div class="mb-2 rounded-2xl border border-white/10 bg-white/5 px-3 py-3"><p class="truncate text-sm font-semibold text-white">{{ $contextEvent->name }}</p><a href="{{ route('admin.events') }}" class="mt-1 inline-flex text-xs text-brand-200 hover:text-white">Ganti event</a></div>
                        @foreach ($eventNavigation as $item)
                            @php($active = request()->routeIs($item['route'], $item['route'].'.*'))
                            <a href="{{ route($item['route'], $contextEvent) }}" @class([
                                'group flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition',
                                'bg-white text-brand-950' => $active,
                                'text-brand-100/75 hover:bg-white/10 hover:text-white' => ! $active,
                            ])><span @class(['grid size-7 place-items-center text-sm transition', 'text-brand-700' => $active, 'text-brand-200 group-hover:text-white' => ! $active])>{!! $icons[$item['icon']] !!}</span><span class="flex-1">{{ $item['label'] }}</span>@if($active)<span class="size-2 rounded-full bg-accent-500"></span>@endif</a>
                        @endforeach
                    @endif
                    <div class="mt-5 border-t border-white/10 px-3 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-300">Administrasi</div>
                    @if (auth()->user()->can('users.view'))
                        <a href="{{ route('admin.users') }}" @class([
                            'group flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition',
                            'bg-white text-brand-950' => request()->routeIs('admin.users*'),
                            'text-brand-100/75 hover:bg-white/10 hover:text-white' => ! request()->routeIs('admin.users*'),
                        ])><span @class(['grid size-7 place-items-center text-sm transition', 'text-brand-700' => request()->routeIs('admin.users*'), 'text-brand-200 group-hover:text-white' => ! request()->routeIs('admin.users*')])>{!! $icons['users'] !!}</span><span class="flex-1">Users</span>@if(request()->routeIs('admin.users*'))<span class="size-2 rounded-full bg-accent-500"></span>@endif</a>
                    @endif
                    <div class="mt-5 border-t border-white/10 px-3 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-300">Tools</div>
                    <a href="{{ route('scanner.index') }}" @class([
                        'group mt-4 flex items-center gap-3 rounded-2xl border px-3.5 py-3 text-sm font-semibold transition',
                        'border-accent-500/50 bg-accent-500 text-brand-950' => request()->routeIs('scanner.index'),
                        'border-white/10 bg-white/5 text-brand-100 hover:border-accent-500/40 hover:bg-accent-500/10 hover:text-white' => ! request()->routeIs('scanner.index'),
                    ])><span class="grid size-7 place-items-center rounded-lg">{!! $icons['scan'] !!}</span><span class="flex-1">Scanner check-in</span></a>                </nav>
                <div class="space-y-3 pt-4">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3.5"><p class="text-xs font-semibold text-white">Butuh bantuan?</p><p class="mt-1 text-xs leading-5 text-brand-200">Kelola data event dan tamu dengan aman.</p></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/15 px-4 py-2.5 text-[13px] font-semibold text-brand-100 transition hover:border-white/30 hover:bg-white/10 hover:text-white">Keluar <i data-lucide="log-out" class="size-4"></i></button></form>
                </div>
            </aside>
            <div class="min-w-0 lg:ml-64">
                <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200/80 bg-white/85 px-4 backdrop-blur-xl sm:px-8">
                    <div class="flex items-center gap-4"><button x-on:click="sidebar = true" class="grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-600 lg:hidden" aria-label="Buka menu"><i data-lucide="menu" class="size-5"></i></button><div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600">{{ $currentNavigation['label'] }}</p></div></div>
                    <button type="button" x-on:click="accountOpen = true" class="group flex items-center gap-3 rounded-2xl border border-transparent px-2 py-1.5 text-left transition hover:border-brand-200 hover:bg-brand-50"><div class="hidden text-right sm:block"><p class="text-sm font-bold text-slate-900">{{ auth()->user()->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ auth()->user()->getRoleNames()->join(', ') }}</p></div><div class="grid size-10 place-items-center rounded-2xl bg-brand-100 font-bold text-brand-700">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</div></button>
                </header>
                <div x-show="accountOpen" x-cloak x-on:keydown.escape.window="accountOpen = false" class="z-50 w-72" style="position: fixed; top: 5rem; right: 1rem; width: 18rem;" x-transition>
                    <div x-on:click.outside="accountOpen = false" class="w-full rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600">Account</p><h2 class="mt-1 text-base font-semibold text-brand-950">{{ auth()->user()->name }}</h2><p class="mt-0.5 text-xs text-slate-500">{{ auth()->user()->email }}</p></div><button type="button" x-on:click="accountOpen = false" class="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-500" aria-label="Tutup"><i data-lucide="x" class="size-4"></i></button></div>
                        <div class="mt-6 grid gap-3">
                            @if (auth()->user()->can('users.update'))<a href="{{ route('admin.users.edit', auth()->id()) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-brand-300 hover:bg-brand-50"><span>Pengaturan akun</span><i data-lucide="chevron-right" class="size-4 text-brand-700"></i></a>@endif
                            @if (auth()->user()->can('users.view'))<a href="{{ route('admin.users') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-brand-300 hover:bg-brand-50"><span>Kelola user</span><i data-lucide="chevron-right" class="size-4 text-brand-700"></i></a>@endif
                            <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center justify-between rounded-2xl border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 hover:bg-red-50">Keluar<i data-lucide="log-out" class="size-4"></i></button></form>
                        </div>
                    </div>
                </div>                <main class="min-h-[calc(100vh-5rem)] bg-[radial-gradient(circle_at_top_right,_rgba(100,117,244,0.10),_transparent_32rem)] px-4 py-8 sm:px-8 lg:px-10">{{ $slot }}</main>
            </div>
        </div>
        @livewireScripts
    </body>
</html>





