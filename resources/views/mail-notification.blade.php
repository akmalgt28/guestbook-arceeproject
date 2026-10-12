<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $payload['subject'] ?? config('app.name') }}</title>
</head>
<body style="margin:0;background:#f4f6fb;color:#1d214c;font-family:Arial,sans-serif;line-height:1.6">
    <div style="max-width:620px;margin:0 auto;padding:32px 16px">
        <div style="background:#1d214c;border-radius:20px 20px 0 0;padding:24px 28px;color:#fff">
            <div style="font-size:20px;font-weight:700">{{ config('app.name', 'Guestbook') }}<span style="color:#f59e0b">.</span></div>
        </div>
        <div style="background:#fff;padding:32px 28px;border-radius:0 0 20px 20px">
            <p style="margin-top:0;color:#6475f4;font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase">{{ match ($payload['type'] ?? 'invitation') {
                'rsvp' => 'Konfirmasi kehadiran',
                'qr' => 'QR check-in',
                'reminder' => 'Pengingat acara',
                default => 'Undangan acara',
            } }}</p>
            <h1 style="margin:8px 0 16px;font-size:28px;line-height:1.2;color:#1d214c">{{ $payload['title'] ?? ($payload['event_name'] ?? 'Informasi acara') }}</h1>
            @if (filled($payload['recipient_name'] ?? null))<p>Halo {{ $payload['recipient_name'] }},</p>@endif
            <p style="white-space:pre-line">{{ $payload['body'] ?? 'Ada informasi baru untuk Anda.' }}</p>
            @if (filled($payload['action_url'] ?? null))<p style="margin:28px 0"><a href="{{ $payload['action_url'] }}" style="display:inline-block;background:#6475f4;color:#fff;text-decoration:none;border-radius:10px;padding:12px 18px;font-weight:700">{{ $payload['action_text'] ?? 'Buka undangan' }}</a></p>@endif
            @if (filled($payload['event_name'] ?? null))<p style="color:#64748b;font-size:13px">Event: {{ $payload['event_name'] }}</p>@endif
        </div>
        <p style="padding:0 12px;color:#94a3b8;font-size:12px;text-align:center">Pesan otomatis dari {{ config('app.name', 'Guestbook') }}.</p>
    </div>
</body>
</html>
