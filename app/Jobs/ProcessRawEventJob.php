<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\RawEvent;
use App\Services\Processing\EventPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRawEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'processing';

    public int $tries = 2;

    public function __construct(
        public readonly RawEvent $rawEvent,
    ) {}

    public function handle(EventPipeline $pipeline): void
    {
        $pipeline->process($this->rawEvent);
    }
}
