<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_id',
        'token_hash',
        'status',
        'generated_at',
        'revoked_at',
        'last_scanned_at',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public static function generateTokenHash(): string
    {
        return hash('sha256', Str::random(64));
    }

    public function regenerate(): self
    {
        $this->forceFill([
            'token_hash' => self::generateTokenHash(),
            'status' => self::STATUS_ACTIVE,
            'generated_at' => now(),
            'revoked_at' => null,
            'last_scanned_at' => null,
        ])->save();

        return $this;
    }

    public function revoke(): self
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();

        return $this;
    }

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_scanned_at' => 'datetime',
        ];
    }
}
