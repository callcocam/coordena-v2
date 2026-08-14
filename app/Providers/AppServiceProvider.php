<?php

namespace App\Providers;

use App\Models\User;
use App\Support\TeamCredentialsResolver;
use App\Support\Translation\MergingFileLoader;
use Callcocam\WhatsAppCloud\Contracts\WhatsAppCredentialsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        // WhatsApp::for($team) usa a conexão do próprio time quando existir,
        // senão o número compartilhado do config `default`.
        $this->app->bind(WhatsAppCredentialsResolver::class, TeamCredentialsResolver::class);
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
        $this->registerAuthorizationGates();
    }

    /**
     * Restrict the WhatsApp Cloud template panel to an allowlist of e-mails.
     *
     * The panel (/whatsapp/cloud/templates) mutates the shared WABA, so [web,
     * auth] is not enough. The package appends `can:manage-whatsapp-templates`
     * to its middleware when WHATSAPP_CLOUD_PANEL_GATE is set. An empty
     * allowlist denies everyone — the safe default for a cross-tenant tool.
     */
    protected function registerAuthorizationGates(): void
    {
        Gate::define('manage-whatsapp-templates', function (User $user): bool {
            $allow = array_filter(array_map(
                'trim',
                explode(',', (string) config('services.whatsapp_cloud.panel_emails')),
            ));

            return $allow !== [] && in_array($user->email, $allow, true);
        });
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
