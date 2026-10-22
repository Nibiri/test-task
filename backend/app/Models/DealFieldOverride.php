<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealFieldOverride extends Model
{
    protected $fillable = [
        'deal_id',
        'field_name',
        'base_value',
        'local_value',
        'is_conflicted',
    ];

    protected function casts(): array
    {
        return [
            'is_conflicted' => 'boolean',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
