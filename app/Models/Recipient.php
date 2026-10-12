<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'event_id',
    'type',
    'name',
    'phone',
    'email',
    'invited_guest_count',
    'notes',
])]
class Recipient extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_FAMILY_REPRESENTATIVE = 'family_representative';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    protected function casts(): array
    {
        return [
            'invited_guest_count' => 'integer',
        ];
    }
}
