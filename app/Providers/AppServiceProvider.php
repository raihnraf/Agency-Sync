<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
