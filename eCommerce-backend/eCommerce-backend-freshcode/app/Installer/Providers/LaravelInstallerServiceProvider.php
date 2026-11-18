<?php

namespace App\Installer\Providers;

use App\Support\EnvWriter;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use App\Installer\Middleware\canInstall;

class LaravelInstallerServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap the application events.
     *
     * @param \Illuminate\Routing\Router $router
     * @return void
     */
    public function boot(Router $router)
    {
        $this->ensureInstallerSessionDriver();
        $this->ensureAppKey();

        $router->middlewareGroup('install', [canInstall::class]);

        $this->loadRoutesFrom(base_path('routes/installer.php'));
    }

    private function ensureInstallerSessionDriver(): void
    {
        if (file_exists(storage_path('installed'))) {
            return;
        }

        config(['session.driver' => 'cookie']);

        try {
            $envPath = $this->app->environmentFilePath();
            $envContent = is_file($envPath) ? file_get_contents($envPath) : '';

            if ($envContent === false) {
                throw new \RuntimeException('Environment file could not be read.');
            }

            if (preg_match('/^\s*SESSION_DRIVER\s*=\s*["\']?cookie["\']?\s*$/mi', $envContent) === 1) {
                return;
            }

            $envContent = EnvWriter::stripKeys($envContent, ['SESSION_DRIVER']);
            EnvWriter::write($envPath, rtrim($envContent, "\n")."\nSESSION_DRIVER=cookie\n");
        } catch (\Throwable $e) {
            logger()->error('Failed to set the installer session driver: '.$e->getMessage());
        }
    }

    private function ensureAppKey(): void
    {
        if (trim((string) config('app.key')) !== '') {
            return;
        }

        if (! is_writable($this->app->environmentFilePath())) {
            return;
        }

        try {
            $envPath = $this->app->environmentFilePath();

            if (! preg_match('/^APP_KEY=/m', (string) file_get_contents($envPath))) {
                file_put_contents($envPath, PHP_EOL . 'APP_KEY=' . PHP_EOL, FILE_APPEND);
            }

            Artisan::call('key:generate', ['--force' => true]);
        } catch (\Throwable $e) {
            logger()->error('Failed to generate APP_KEY: ' . $e->getMessage());
        }
    }
}
