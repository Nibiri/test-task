<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceInconsistency extends Model
{
    protected $fillable = [
        'deal_id',
        'external_id',
        'external_version',
        'source',
        'webhook_event_id',
        'refresh_operation_id',
        'incoming',
        'stored',
    ];

    protected function casts(): array
    {
        return [
            'incoming' => 'array',
            'stored' => 'array',
            'external_version' => 'integer',
        ];
    }
}
