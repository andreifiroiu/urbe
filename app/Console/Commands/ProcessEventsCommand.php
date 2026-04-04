<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Processing\EventPipeline;
use Illuminate\Console\Command;

class ProcessEventsCommand extends Command
{
    protected $signature = 'eventpulse:process-events';

    protected $description = 'Process raw events through the classification and enrichment pipeline';

    public function handle(EventPipeline $pipeline): int
    {
        $this->info('Fetching unprocessed events...');

        $unprocessed = Event::where('is_classified', false)->get();

        if ($unprocessed->isEmpty()) {
            $this->info('No unprocessed events found.');

            return self::SUCCESS;
        }

        $this->info("Processing {$unprocessed->count()} unprocessed events...");

        $processed = $pipeline->processBatch($unprocessed);

        $this->info("Successfully processed {$processed->count()} events.");

        return self::SUCCESS;
    }
}
