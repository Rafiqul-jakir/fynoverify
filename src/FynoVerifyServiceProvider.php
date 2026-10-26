<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify;

use Rafiquljakir\FynoVerify\Commands\CoverageCommand;
use Rafiquljakir\FynoVerify\Commands\DoctorCommand;
use Rafiquljakir\FynoVerify\Commands\PlanCommand;
use Rafiquljakir\FynoVerify\Commands\ScanCommand;
use Illuminate\Support\ServiceProvider;

final class FynoVerifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Defaults are also applied per key by ConfigurationValidator, so a partially
        // published config (or none at all) works.
        $this->mergeConfigFrom(__DIR__.'/../config/fynoVerify.php', FynoVerify::CONFIG_KEY);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/fynoVerify.php' => config_path('fynoVerify.php'),
        ], 'fynoVerify-config');

        $this->commands([
            ScanCommand::class,
            CoverageCommand::class,
            PlanCommand::class,
            DoctorCommand::class,
        ]);
    }
}
