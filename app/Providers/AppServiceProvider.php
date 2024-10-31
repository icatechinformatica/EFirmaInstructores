<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {/*
        if ($this->app->environment('production')) {
            # forzamos el esquema a trabajar con https
            URL::forceScheme('https');
        }   */

        if(config('app.env') === 'production') {
            \URL::forceScheme('https');
        }
    }
}
