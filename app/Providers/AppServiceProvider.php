<?php

namespace App\Providers;

use App\Engines\ElasticsearchEngine;
use App\Search\IndexManager;
use App\Search\ProductSearchService;
use App\Services\QueueJobTracker;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register ProductSearchService
        $this->app->singleton(ProductSearchService::class, function ($app) {
            $client = ClientBuilder::create()
                ->setHosts(config('scout.elasticsearch.hosts', ['localhost:9200']))
                ->build();
            
            return new ProductSearchService(
                new IndexManager($client),
                $client
            );
        });

        // Register Elasticsearch Scout engine - must happen before ScoutServiceProvider runs
        $this->app->resolving(EngineManager::class, function ($engineManager) {
            $engineManager->extend('elasticsearch', function () {
                $client = ClientBuilder::create()
                    ->setHosts(config('scout.elasticsearch.hosts', ['localhost:9200']))
                    ->build();
                
                return new ElasticsearchEngine($client, new IndexManager($client));
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Track job lifecycle
        \Queue::before(function ($event) {
            app(QueueJobTracker::class)->markAsRunning($event->job);
        });

        \Queue::after(function ($event) {
            if ($event->job->hasFailed()) {
                return; // Handled by failing callback
            }
            app(QueueJobTracker::class)->markAsCompleted($event->job);
        });

        \Queue::failing(function ($event) {
            app(QueueJobTracker::class)->markAsFailed($event->job, $event->exception);
        });
    }
}
