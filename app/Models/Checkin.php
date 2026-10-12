<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'qr_code_id',
    'recipient_id',
    'checked_in_by',
    'method',
    'status',
    'actual_attendee_count',
    'checked_in_at',
    'notes',
])]
class Checkin extends Model
{
    use HasFactory;

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    protected function casts(): array
    {
        return [
            'actual_attendee_count' => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }
}
