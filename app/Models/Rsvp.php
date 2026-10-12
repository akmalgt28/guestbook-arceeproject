<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invitation_id',
    'status',
    'attending_count',
    'responded_at',
    'notes',
])]
class Rsvp extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ATTENDING = 'attending';

    public const STATUS_NOT_ATTENDING = 'not_attending';

    public const STATUS_CANCELLED = 'cancelled';

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(RsvpAttendee::class);
    }

    public function scopeResponded(Builder $query): Builder
    {
        return $query->whereNotNull('responded_at');
    }

    protected function casts(): array
    {
        return [
            'attending_count' => 'integer',
            'responded_at' => 'datetime',
        ];
    }
}
