<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Scraping\ScraperOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScraperJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public ?string $source = null,
    ) {
        $this->onQueue('scraping');
    }

    public function handle(ScraperOrchestrator $orchestrator): void
    {
        if ($this->source !== null) {
            $orchestrator->runSingle($this->source);
        } else {
            $orchestrator->runAll();
        }
    }
}
