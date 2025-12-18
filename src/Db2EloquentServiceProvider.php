<?php

namespace CodyJHeiser\Db2Eloquent;

use CodyJHeiser\Db2Eloquent\Console\Commands\MakeDb2ModelCommand;
use Illuminate\Support\ServiceProvider;

class Db2EloquentServiceProvider extends ServiceProvider
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
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeDb2ModelCommand::class,
            ]);
        }
    }
}
