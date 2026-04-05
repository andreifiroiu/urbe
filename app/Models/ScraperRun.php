<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScraperRunFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScraperRun extends Model
{
    /** @use HasFactory<ScraperRunFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'city',
        'status',
        'events_found',
        'events_created',
        'events_updated',
        'events_skipped',
        'errors_count',
        'error_log',
        'started_at',
        'finished_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'error_log' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'events_found' => 'integer',
            'events_created' => 'integer',
            'events_updated' => 'integer',
            'events_skipped' => 'integer',
            'errors_count' => 'integer',
        ];
    }
}
