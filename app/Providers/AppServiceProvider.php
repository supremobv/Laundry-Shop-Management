<?php

namespace App\Providers;

use App\Models\BusinessSetting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped('business.settings', fn () => BusinessSetting::firstOrCreate(['id' => 1])->refresh());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (str_starts_with($view->name(), 'errors.')) {
                return;
            }
            $view->with('business', BusinessSetting::current());
        });
        Paginator::defaultView('partials.pagination');
    }
}
