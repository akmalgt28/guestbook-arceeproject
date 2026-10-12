<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'recipient_id',
    'name',
    'relationship',
    'is_expected',
])]
class FamilyMember extends Model
{
    use HasFactory, SoftDeletes;

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    protected function casts(): array
    {
        return [
            'is_expected' => 'boolean',
        ];
    }
}
