<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LlmUsageLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'operation',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'float',
            'metadata' => 'array',
        ];
    }
}
