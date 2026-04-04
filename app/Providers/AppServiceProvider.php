<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ScraperAdapter;
use App\Services\Anthropic\AnthropicClient;
use App\Services\Scraping\Adapters\GenericHtmlScraper;
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
            return new ScraperOrchestrator(
                adapters: [
                    $app->make(GenericHtmlScraper::class),
                ],
            );
        });

        $this->app->bind(ScraperAdapter::class, GenericHtmlScraper::class);
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
