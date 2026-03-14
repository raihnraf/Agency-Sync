<?php

namespace App\Providers;

use App\Engines\ElasticsearchEngine;
use App\Search\IndexManager;
use App\Search\ProductSearchService;
use App\Services\QueueJobTracker;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Configure rate limiters
        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Rate limit exceeded',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Rate limit exceeded',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts',
                        'retry_after' => 60,
                    ], 429);
                });
        });

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
