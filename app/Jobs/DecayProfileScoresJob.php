<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\InterestProfile\ProfileDecayer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DecayProfileScoresJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'default';

    public int $tries = 1;

    public function __construct() {}

    public function handle(ProfileDecayer $decayer): void
    {
        $decayer->decayAll();
    }
}
