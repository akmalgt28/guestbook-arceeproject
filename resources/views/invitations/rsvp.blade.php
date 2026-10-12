<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RSVP - {{ $event->name }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 px-4 py-8 text-slate-800">
        @php
            $rsvpConfig = $config['rsvp'] ?? [];
            $fields = collect($rsvpConfig['fields'] ?? [])->keyBy('key');
            $field = fn (string $key): array => (array) $fields->get($key, []);
            $fieldLabel = fn (string $key, string $fallback): string => (string) data_get($field($key), 'label', $fallback);
            $fieldPlaceholder = fn (string $key): ?string => data_get($field($key), 'placeholder');
            $fieldVisible = fn (string $key): bool => (bool) data_get($field($key), 'visible', true);
        @endphp
        <main class="mx-auto max-w-2xl rounded-3xl bg-white p-6 sm:p-8">
            <a href="{{ route('invitations.show', $token) }}" class="text-sm font-semibold text-slate-500">Kembali ke undangan</a>
            <p class="mt-8 text-sm font-semibold uppercase tracking-widest text-slate-500">{{ $event->name }}</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $rsvpConfig['title'] ?? 'Konfirmasi kehadiran' }}</h1>
            <p class="mt-3 text-slate-600">{{ $rsvpConfig['description'] ?? 'Undangan untuk '.$recipient->name.'.' }}</p>

            @if (session('rsvp_success'))<div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-emerald-800">{{ session('rsvp_success') }}</div>@endif
            @if ($errors->any())<div class="mt-6 rounded-2xl bg-red-50 p-4 text-red-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @if (session('rsvp_success') && $qrCode?->status === \App\Models\QrCode::STATUS_ACTIVE)<div class="mt-6 rounded-2xl border border-slate-200 p-5 text-center"><p class="font-semibold">QR check-in kamu</p><div class="mx-auto mt-4 max-w-xs">{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(240)->margin(2)->generate(url('/checkin/scan/'.$qrCode->token_hash)) !!}</div><a class="mt-4 inline-flex rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold" href="data:image/svg+xml;base64,{{ base64_encode((string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(600)->margin(2)->generate(url('/checkin/scan/'.$qrCode->token_hash))) }}" download="qr-checkin-{{ $recipient->name }}.svg">Download QR</a></div>@endif

            <form class="mt-8 space-y-6" method="POST" action="{{ route('invitations.rsvp.submit', $token) }}" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                @csrf
                <div><label class="text-sm font-semibold" for="name">{{ $fieldLabel('name', 'Nama') }}</label><input class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" id="name" name="name" value="{{ old('name', $recipient->name) }}" placeholder="{{ $fieldPlaceholder('name') }}" required maxlength="255"></div>
                <div class="grid gap-6 sm:grid-cols-2">@if ($fieldVisible('phone'))<div><label class="text-sm font-semibold" for="phone">{{ $fieldLabel('phone', 'Nomor telepon') }}</label><input class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" id="phone" name="phone" value="{{ old('phone', $recipient->phone) }}" placeholder="{{ $fieldPlaceholder('phone') }}" @required(data_get($field('phone'), 'required', false)) maxlength="50"></div>@endif @if ($fieldVisible('email'))<div><label class="text-sm font-semibold" for="email">{{ $fieldLabel('email', 'Email') }}</label><input class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" id="email" name="email" type="email" value="{{ old('email', $recipient->email) }}" placeholder="{{ $fieldPlaceholder('email') }}" @required(data_get($field('email'), 'required', false)) maxlength="255"></div>@endif</div>
                <fieldset><legend class="text-sm font-semibold">Konfirmasi</legend><div class="mt-3 grid gap-3 sm:grid-cols-2"><label class="rounded-xl border border-slate-300 p-4"><input class="mr-2" type="radio" name="status" value="attending" @checked(old('status', $rsvp?->status) === 'attending') required> Hadir</label><label class="rounded-xl border border-slate-300 p-4"><input class="mr-2" type="radio" name="status" value="not_attending" @checked(old('status', $rsvp?->status) === 'not_attending')> Tidak hadir</label></div></fieldset>
                <div><label class="text-sm font-semibold" for="attending_count">{{ $fieldLabel('attending_count', 'Jumlah yang hadir') }}</label><input class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" id="attending_count" name="attending_count" type="number" min="0" max="100" value="{{ old('attending_count', $rsvp?->attending_count ?? $recipient->invited_guest_count) }}" placeholder="{{ $fieldPlaceholder('attending_count') }}" required></div>
                @if ($familyMembers->isNotEmpty())<fieldset><legend class="text-sm font-semibold">Anggota keluarga yang hadir</legend><div class="mt-3 space-y-3">@foreach ($familyMembers as $familyMember)<label class="block rounded-xl border border-slate-300 p-4"><input class="mr-2" type="checkbox" name="family_member_ids[]" value="{{ $familyMember->id }}" @checked(in_array($familyMember->id, old('family_member_ids', $rsvp?->attendees?->pluck('family_member_id')->filter()->all() ?? [])))>{{ $familyMember->name }}@if ($familyMember->relationship) <span class="text-sm text-slate-500">({{ $familyMember->relationship }})</span>@endif</label>@endforeach</div></fieldset>@endif
                @if ($fieldVisible('notes'))<div><label class="text-sm font-semibold" for="notes">{{ $fieldLabel('notes', 'Catatan') }}</label><textarea class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" id="notes" name="notes" rows="3" placeholder="{{ $fieldPlaceholder('notes') }}" @required(data_get($field('notes'), 'required', false)) maxlength="1000">{{ old('notes', $rsvp?->notes) }}</textarea></div>@endif
                <div class="hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                <button class="w-full rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" type="submit" x-bind:disabled="submitting"><span x-show="!submitting">{{ $rsvpConfig['submit_label'] ?? 'Kirim konfirmasi' }}</span><span x-cloak x-show="submitting">Mengirim...</span></button>
            </form>
        </main>
    </body>
</html>


