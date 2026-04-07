<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApifyUsageLog extends Model
{
    use HasUuids;

    /** Log table is append-only — no updated_at column. */
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'run_id',
        'query',
        'events_returned',
        'cost_usd',
        'duration_seconds',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'events_returned' => 'integer',
            'cost_usd' => 'float',
            'duration_seconds' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @param  Builder<ApifyUsageLog>  $query */
    public function scopeToday(Builder $query): void
    {
        $query->whereDate('created_at', today());
    }
}
