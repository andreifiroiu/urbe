<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Anthropic\AnthropicClient;
use App\Services\Scraping\ScraperOrchestrator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AnthropicClient::class, function () {
            return new AnthropicClient(
                apiKey: (string) config('eventpulse.llm.api_key'),
                model: (string) config('eventpulse.llm.model'),
                maxTokens: (int) config('eventpulse.llm.max_tokens', 1024),
            );
        });

        $this->app->singleton(ScraperOrchestrator::class, function ($app) {
            /** @var array<string, array{enabled: bool}> $sources */
            $sources = config('eventpulse.scrapers.sources', []);
            $registry = ScraperOrchestrator::ADAPTER_REGISTRY;
            $adapters = [];

            foreach ($sources as $key => $cfg) {
                if ($cfg['enabled'] && isset($registry[$key])) {
                    $adapters[] = $app->make($registry[$key]);
                }
            }

            return new ScraperOrchestrator(adapters: $adapters);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('anthropic-api', function () {
            return Limit::perMinute(100);
        });
    }
}
