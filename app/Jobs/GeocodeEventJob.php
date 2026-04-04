<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use App\Services\Processing\EventEnricher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeocodeEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'enrichment';

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $eventId,
    ) {}

    public function handle(EventEnricher $enricher): void
    {
        $event = Event::findOrFail($this->eventId);

        $enricher->enrichGeocoding($event);
    }
}
