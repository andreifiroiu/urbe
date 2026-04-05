<?php

declare(strict_types=1);

use App\Contracts\ScraperAdapter;
use App\DTOs\RawEvent;
use App\Jobs\RunScraperJob;
use App\Models\ScraperRun;
use App\Services\Scraping\Adapters\ZileSiNoptiScraper;
use App\Services\Scraping\ScraperOrchestrator;
use Illuminate\Support\Facades\Queue;

// ---------------------------------------------------------------------------
// Fakes
// ---------------------------------------------------------------------------

class FakeZileSiNoptiAdapter implements ScraperAdapter
{
    public function adapterKey(): string
    {
        return 'zilesinopti';
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return 'zilesinopti@zilesinopti.ro';
    }

    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $onEvent(new RawEvent(
            title: 'Test Event',
            description: null,
            sourceUrl: 'https://zilesinopti.ro/evenimente/test/',
            sourceId: 'test',
            source: 'zilesinopti',
            venue: null,
            address: null,
            city: $cityConfig['label'],
            startsAt: null,
            endsAt: null,
            priceMin: null,
            priceMax: null,
            currency: null,
            isFree: null,
            imageUrl: null,
            metadata: [],
        ));
    }
}

class ThrowingAdapter implements ScraperAdapter
{
    public function adapterKey(): string
    {
        return 'zilesinopti';
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return 'zilesinopti@zilesinopti.ro';
    }

    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        throw new RuntimeException('Scraper network error');
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('resolveAdapter', function () {
    it('returns the registered adapter instance for a known key', function () {
        $orchestrator = app(ScraperOrchestrator::class);

        $adapter = $orchestrator->resolveAdapter('zilesinopti');

        expect($adapter)->toBeInstanceOf(ScraperAdapter::class)
            ->and($adapter->adapterKey())->toBe('zilesinopti');
    });

    it('throws InvalidArgumentException for an unknown key', function () {
        $orchestrator = app(ScraperOrchestrator::class);

        expect(fn () => $orchestrator->resolveAdapter('does_not_exist'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('getCityConfig', function () {
    it('returns the full city config array for timisoara', function () {
        $orchestrator = app(ScraperOrchestrator::class);

        $config = $orchestrator->getCityConfig('timisoara');

        expect($config['label'])->toBe('Timișoara')
            ->and($config['timezone'])->toBe('Europe/Bucharest')
            ->and($config['radius_km'])->toBe(25);
    });

    it('throws InvalidArgumentException for an unknown city', function () {
        $orchestrator = app(ScraperOrchestrator::class);

        expect(fn () => $orchestrator->getCityConfig('atlantis'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('getEnabledSources', function () {
    it('returns only enabled sources that have a registered adapter', function () {
        $orchestrator = app(ScraperOrchestrator::class);

        $sources = $orchestrator->getEnabledSources('timisoara');

        // zilesinopti is the only enabled source with a registered adapter in the test config
        expect($sources)->toBeArray()
            ->and(count($sources))->toBeGreaterThanOrEqual(1);

        foreach ($sources as $source) {
            expect($source['enabled'])->toBeTrue()
                ->and($source)->toHaveKey('adapter')
                ->and($source)->toHaveKey('url');
        }
    });

    it('returns empty array for a city with no enabled sources', function () {
        config(['eventpulse.cities.empty_city' => [
            'label' => 'Empty City',
            'timezone' => 'UTC',
            'coordinates' => [0.0, 0.0],
            'radius_km' => 10,
            'sources' => [
                ['adapter' => 'zilesinopti', 'url' => 'https://example.com/', 'enabled' => false, 'interval_hours' => 4],
            ],
        ]]);

        $orchestrator = app(ScraperOrchestrator::class);

        expect($orchestrator->getEnabledSources('empty_city'))->toBeEmpty();
    });
});

describe('runAll and runCity', function () {
    it('dispatches one RunScraperJob per enabled source via runAll', function () {
        Queue::fake();

        $orchestrator = app(ScraperOrchestrator::class);
        $orchestrator->runAll();

        Queue::assertPushed(RunScraperJob::class);
    });

    it('dispatches jobs only for the specified city via runCity', function () {
        Queue::fake();

        $orchestrator = app(ScraperOrchestrator::class);
        $orchestrator->runCity('timisoara');

        $enabledCount = count($orchestrator->getEnabledSources('timisoara'));
        Queue::assertPushed(RunScraperJob::class, $enabledCount);
    });

    it('dispatches no jobs for a city with no enabled sources', function () {
        Queue::fake();

        config(['eventpulse.cities.empty_city' => [
            'label' => 'Empty City',
            'timezone' => 'UTC',
            'coordinates' => [0.0, 0.0],
            'radius_km' => 10,
            'sources' => [],
        ]]);

        $orchestrator = app(ScraperOrchestrator::class);
        $orchestrator->runCity('empty_city');

        Queue::assertNothingPushed();
    });
});

describe('runSource', function () {
    it('creates a ScraperRun record and marks it completed on success', function () {
        // Bind the fake adapter into the container for this test
        $this->app->bind(
            ZileSiNoptiScraper::class,
            FakeZileSiNoptiAdapter::class,
        );

        $orchestrator = app(ScraperOrchestrator::class);
        $saved = $orchestrator->runSource('timisoara', 'zilesinopti');

        expect($saved)->toBeInt()->toBe(1);

        $run = ScraperRun::where('source', 'zilesinopti')
            ->where('city', 'timisoara')
            ->latest()
            ->first();

        expect($run)->not->toBeNull()
            ->and($run->status)->toBe('completed')
            ->and($run->events_found)->toBe(1)
            ->and($run->finished_at)->not->toBeNull();
    });

    it('marks the ScraperRun as failed and returns 0 on exception', function () {
        $this->app->bind(
            ZileSiNoptiScraper::class,
            ThrowingAdapter::class,
        );

        $orchestrator = app(ScraperOrchestrator::class);
        $saved = $orchestrator->runSource('timisoara', 'zilesinopti');

        expect($saved)->toBeInt()->toBe(0);

        $run = ScraperRun::where('source', 'zilesinopti')
            ->where('city', 'timisoara')
            ->latest()
            ->first();

        expect($run)->not->toBeNull()
            ->and($run->status)->toBe('failed')
            ->and($run->errors_count)->toBe(1)
            ->and($run->error_log)->toContain('Scraper network error');
    });
});
