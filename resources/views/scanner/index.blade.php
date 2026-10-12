<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Scanner Check-in</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-white">
        <main x-data="scannerPage()" class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-8">
            <header class="flex items-center justify-between gap-4">
                <div><p class="text-sm font-semibold uppercase tracking-[0.25em] text-amber-300">Guestbook</p><h1 class="mt-2 text-3xl font-semibold">Scanner check-in</h1></div>
                <a href="{{ route('admin.dashboard') }}" class="rounded-full border border-white/20 px-4 py-2 text-sm text-white/80">Panel admin</a>
            </header>

            <div class="mt-8 grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
                <section class="rounded-3xl bg-white p-5 text-slate-900 shadow-2xl sm:p-8">
                    <label class="text-sm font-semibold" for="event">Event aktif</label>
                    <select id="event" x-model="eventId" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3">
                        <option value="">Pilih event</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}">{{ $event->name }} — {{ $event->event_date?->format('d M Y') }}</option>
                        @endforeach
                    </select>

                    <div id="qr-reader" class="mt-6 overflow-hidden rounded-2xl bg-slate-100"></div>
                    <div class="mt-4 flex flex-wrap gap-3"><button type="button" x-on:click="start" x-bind:disabled="!eventId || scanning" class="rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Mulai kamera</button><button type="button" x-on:click="stop" x-bind:disabled="!scanning" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold disabled:cursor-not-allowed disabled:opacity-40">Berhenti</button></div>
                    <p class="mt-4 text-sm text-slate-500" x-text="cameraStatus"></p>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur sm:p-7"><h2 class="text-xl font-semibold">Input manual</h2><p class="mt-2 text-sm text-white/60">Pakai jika kamera gagal atau QR tidak terbaca.</p><form class="mt-5 space-y-3" x-on:submit.prevent="submitManual"><input x-model="manualToken" class="w-full rounded-xl border-0 bg-white px-4 py-3 text-slate-900" placeholder="Token QR" autocomplete="off"><button class="w-full rounded-xl bg-amber-300 px-5 py-3 font-semibold text-slate-950 disabled:opacity-40" type="submit" x-bind:disabled="!eventId || !manualToken.trim()">Kirim token</button></form></section>
                                        <section x-show="result || error" x-cloak class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur sm:p-7"><h2 class="text-xl font-semibold">Status</h2><p x-show="result" class="mt-4 rounded-xl bg-amber-300 p-4 text-slate-950" x-text="result"></p><p x-show="error" class="mt-4 rounded-xl bg-red-400 p-4 text-red-950" x-text="error"></p></section>

                    <div x-show="candidate" x-cloak x-on:keydown.escape.window="closeCandidate" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="candidate-title">
                        <section class="w-full max-w-md rounded-3xl bg-white p-6 text-slate-900 shadow-2xl">
                            <div class="flex items-start justify-between gap-4">
                                <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">QR terbaca</p><h2 id="candidate-title" class="mt-2 text-2xl font-semibold" x-text="candidate?.name"></h2></div>
                                <button type="button" x-on:click="closeCandidate" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">Tutup</button>
                            </div>
                            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-sm text-slate-500">Instansi</p><p class="mt-1 font-semibold" x-text="candidate?.organization || 'Instansi tidak diisi'"></p><p class="mt-3 text-sm text-slate-500">Kontak</p><p class="mt-1 font-semibold" x-text="candidate?.phone || candidate?.email || 'Kontak tidak tersedia'"></p><p class="mt-3 text-sm text-slate-500">Jumlah tamu terdaftar</p><p class="mt-1 font-semibold" x-text="(candidate?.guest_count || 0) + ' orang'"></p></div>
                            <p x-show="alreadyCheckedIn" class="mt-4 rounded-xl bg-red-100 p-4 text-sm text-red-800">Tamu sudah check-in sebelumnya.</p>
                            <div x-show="!alreadyCheckedIn" class="mt-5"><label for="actual-attendee-count" class="text-sm font-semibold">Jumlah hadir aktual</label><input id="actual-attendee-count" x-model.number="actualAttendeeCount" type="number" min="1" max="999" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" autofocus><button type="button" x-on:click="confirmCheckin" class="mt-4 w-full rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white disabled:cursor-wait disabled:opacity-50" x-bind:disabled="loading || !actualAttendeeCount">Simpan check-in</button></div>
                        </section>
                    </div>
                </aside>
            </div>
        </main>

        <script>
            function scannerPage() {
                return {
                    eventId: '', manualToken: '', scanner: null, scanning: false, cameraStatus: 'Kamera belum dimulai.', result: '', error: '', candidate: null, pendingQrCodeId: null, alreadyCheckedIn: false, actualAttendeeCount: 1, loading: false,
                    async start() {
                        this.error = ''; this.result = '';
                        if (!this.eventId) { this.error = 'Pilih event terlebih dahulu.'; return; }
                        if (!window.Html5Qrcode) { this.error = 'Scanner belum termuat. Refresh halaman.'; return; }
                        try {
                            this.scanner = new window.Html5Qrcode('qr-reader');
                            await this.scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 250, height: 250 } }, (value) => { this.submitToken(value); this.stop(); }, () => {});
                            this.scanning = true; this.cameraStatus = 'Kamera aktif. Arahkan ke QR code.';
                        } catch (exception) { this.error = 'Kamera tidak dapat digunakan. Periksa permission browser.'; this.cameraStatus = 'Kamera gagal dimulai.'; }
                    },
                    async stop() { if (this.scanner) { try { await this.scanner.stop(); } catch (exception) {} this.scanner.clear(); } this.scanning = false; this.cameraStatus = 'Kamera berhenti.'; },
                    submitManual() { this.submitToken(this.manualToken); },
                    async submitToken(token) {
                        const value = token.trim();
                        if (!this.eventId || !value || this.loading) return;
                        this.loading = true; this.error = ''; this.result = ''; this.candidate = null;
                        try {
                            const response = await fetch('{{ route('checkin.scan') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ event_id: this.eventId, qr_token: value }) });
                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message || 'QR tidak dapat diproses.');
                            this.manualToken = value; this.pendingQrCodeId = data.qr_code_id; this.candidate = data.recipient; this.alreadyCheckedIn = data.already_checked_in; this.actualAttendeeCount = Number(data.recipient.guest_count || 1);
                        } catch (exception) { this.error = exception.message || 'QR tidak dapat diproses.'; }
                        finally { this.loading = false; }
                    },
                    async confirmCheckin() {
                        if (!this.pendingQrCodeId || !this.actualAttendeeCount || this.loading) return;
                        this.loading = true; this.error = '';
                        try {
                            const response = await fetch('{{ route('checkin.confirm') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ event_id: this.eventId, qr_code_id: this.pendingQrCodeId, actual_attendee_count: this.actualAttendeeCount }) });
                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message || 'Check-in gagal disimpan.');
                            this.result = data.message || 'Check-in berhasil.'; this.closeCandidate();
                        } catch (exception) { this.error = exception.message || 'Check-in gagal disimpan.'; }
                        finally { this.loading = false; }
                    },
                    closeCandidate() { this.candidate = null; this.pendingQrCodeId = null; this.alreadyCheckedIn = false; },
                };
            }
        </script>
    </body>
</html>


