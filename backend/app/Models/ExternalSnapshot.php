<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'external_id',
        'client_reference',
        'version',
        'updated_at',
        'title',
        'status',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'amount' => 'decimal:2',
            'updated_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
