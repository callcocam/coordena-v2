<?php

namespace App\Providers;

use App\Support\Translation\MergingFileLoader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerTranslationLoader();
    }

    /**
     * Swap Laravel's translation loader for one that merges group subdirectories.
     *
     * The framework's default loader is preserved (its paths, JSON paths and
     * namespace hints are carried over) so framework/package translations and
     * the `{locale}.json` files keep working.
     */
    protected function registerTranslationLoader(): void
    {
        $this->app->extend('translation.loader', function (FileLoader $loader, $app): MergingFileLoader {
            $merging = new MergingFileLoader($app['files'], $loader->paths());

            foreach ($loader->jsonPaths() as $path) {
                $merging->addJsonPath($path);
            }

            foreach ($loader->namespaces() as $namespace => $hint) {
                $merging->addNamespace($namespace, $hint);
            }

            return $merging;
        });
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
            : null,
        );
    }
}
