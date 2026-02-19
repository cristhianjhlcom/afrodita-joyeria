<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Database\Seeders\DevelopmentTestingSeeder;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );

        $this->registerLocalFreshMigrationSeeder();
    }

    protected function registerLocalFreshMigrationSeeder(): void
    {
        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($event->command !== 'migrate:fresh') {
                return;
            }

            if (! app()->isLocal() || app()->runningUnitTests()) {
                return;
            }

            if ($event->input?->hasParameterOption('--seed')) {
                return;
            }

            Artisan::call('db:seed', [
                '--class' => DevelopmentTestingSeeder::class,
                '--force' => true,
            ]);

            $event->output?->writeln('');
            $event->output?->writeln('<info>DevelopmentTestingSeeder executed automatically for local migrate:fresh.</info>');
        });
    }
}
