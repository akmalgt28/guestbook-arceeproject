<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'event_id',
    'recipient_id',
    'token_hash',
    'custom_link',
    'status',
    'sent_at',
    'expires_at',
])]
class Invitation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_EXPIRED = 'expired';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function rsvp(): HasOne
    {
        return $this->hasOne(Rsvp::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function scopeForPublicLink(Builder $query, string $value): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->where('custom_link', $value)
            ->orWhere('token_hash', self::hashToken($value)));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{invitation: self, token: string}
     */
    public static function createWithToken(array $attributes): array
    {
        $token = Str::random(64);
        $invitation = self::query()->create([
            ...$attributes,
            'token_hash' => self::hashToken($token),
            'custom_link' => $attributes['custom_link'] ?? self::generateCustomLink(),
        ]);

        return [
            'invitation' => $invitation,
            'token' => $token,
        ];
    }

    public static function generateCustomLink(): string
    {
        return 'invite-'.Str::lower(Str::random(12));
    }

    public function issueToken(): string
    {
        $token = Str::random(64);
        $this->forceFill([
            'token_hash' => self::hashToken($token),
        ])->save();

        return $token;
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
