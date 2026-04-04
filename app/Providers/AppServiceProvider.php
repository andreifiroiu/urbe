<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ScraperAdapter;
use App\Services\Scraping\Adapters\GenericHtmlScraper;
use App\Services\Scraping\ScraperOrchestrator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
        //
    }
}
