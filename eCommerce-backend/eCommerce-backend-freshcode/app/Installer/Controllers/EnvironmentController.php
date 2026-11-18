<?php

namespace App\Installer\Controllers;

use App\Installer\Helpers\EnvironmentManager;
use App\Installer\Request\UpdateRequest;
use App\Services\LicenseActivationStore;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Class EnvironmentController
 */
class EnvironmentController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $environmentManager;

    public function __construct(
        EnvironmentManager $environmentManager,
        private readonly LicenseActivationStore $activationStore
    ) {
        $this->environmentManager = $environmentManager;
    }

    /**
     * Display the Environment page.
     *
     * @return View
     */
    public function environment()
    {
        $activation = $this->activationStore->current();

        if (empty($activation['purchase_code']) || empty($activation['signature'])) {
            return redirect()->route('LaravelInstaller::license');
        }

        if ($this->databaseAlreadyConfigured()) {
            return redirect()->route('LaravelInstaller::superAdmin');
        }

        $envConfig = $this->environmentManager->getEnvContent();

        return view('vendor.installer.environment', compact('envConfig'));
    }

    /**
     * The environment form only writes database credentials that are already
     * configured, so the installer can continue at the super admin step.
     */
    private function databaseAlreadyConfigured(): bool
    {
        $connection = (string) config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! is_string($database) || $database === '') {
            return false;
        }

        try {
            DB::connection()->getPdo();

            return DB::connection()->getDatabaseName() === $database;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return string
     */
    public function save(UpdateRequest $request)
    {
        $message = $this->environmentManager->saveFile($request);

        return $message;

    }
}
