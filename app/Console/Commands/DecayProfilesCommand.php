<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InterestProfile\ProfileDecayer;
use Illuminate\Console\Command;

class DecayProfilesCommand extends Command
{
    protected $signature = 'eventpulse:decay-profiles';

    protected $description = 'Apply time-based decay to user interest profile scores';

    public function handle(ProfileDecayer $decayer): int
    {
        $this->info('Applying decay to user interest profiles...');

        $count = $decayer->decayAll();

        $this->info("Decayed profiles for {$count} users.");

        return self::SUCCESS;
    }
}
