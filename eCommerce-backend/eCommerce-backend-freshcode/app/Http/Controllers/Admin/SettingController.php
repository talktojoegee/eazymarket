<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeviceTypeEnum;
use App\Enums\LanguageSurfaceEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Events\BroadcastConnectionTested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TestPaymentRequest;
use App\Http\Requests\Settings\TestSmsRequest;
use App\Libs\libphonenumber\NumberParseException;
use App\Libs\libphonenumber\PhoneNumberUtil;
use App\Models\Setting;
use App\Models\UserFcmToken;
use App\Services\BroadcastSettingsService;
use App\Services\ClientLanguageService;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use App\Services\Payments\MercadoPagoService;
use App\Services\Payments\XenditService;
use App\Services\ProductApprovalService;
use App\Services\SettingService;
use App\Services\Shipping\ShiprocketClient;
use App\Services\Shipping\Testing\FakeShiprocketClient;
use App\Services\SmsService;
use App\Support\DemoMode;
use App\Support\OutboundHost;
use App\Support\SettingSecrets;
use App\Types\Api\ApiResponseType;
use App\Types\Settings\AdvertisementSettingType;
use App\Types\Settings\AppSettingType;
use App\Types\Settings\AuthenticationSettingType;
use App\Types\Settings\BroadcastDriverSettingType;
use App\Types\Settings\DeliveryBoySettingType;
use App\Types\Settings\EmailSettingType;
use App\Types\Settings\HomeGeneralSettingType;
use App\Types\Settings\NotificationSettingType;
use App\Types\Settings\PaymentSettingType;
use App\Types\Settings\Pos_settingsSettingType;
use App\Types\Settings\SellerSettingType;
use App\Types\Settings\ShippingSettingType;
use App\Types\Settings\StorageSettingType;
use App\Types\Settings\SubscriptionSettingType;
use App\Types\Settings\SystemSettingType;
use App\Types\Settings\SystemUpdateSettingType;
use App\Types\Settings\WatchAndBuySettingType;
use App\Types\Settings\WhatsAppSettingType;
use App\Types\Settings\WebSettingType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SettingController extends Controller
{
    use AuthorizesRequests;

    protected SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index(): View
    {
        try {
            $this->authorize('viewAny', Setting::class);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        }

        return view('admin.settings.all_settings');
    }

    public function testBroadcastConnection(Request $request, BroadcastSettingsService $broadcastSettings): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::BROADCAST_DRIVER()]);
        $validated = $request->validate([
            'test_id' => ['required', 'uuid'],
        ]);

        if (! $broadcastSettings->isConfigured()) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.broadcast_configuration_required'),
                data: [],
                status: 422,
            );
        }

        try {
            $realtime = $broadcastSettings->apply();
            BroadcastConnectionTested::dispatch((int) $request->user()->id, $validated['test_id']);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.broadcast_test_published'),
                data: ['driver' => $realtime['driver']],
            );
        } catch (\Throwable $e) {
            Log::warning('Broadcast connection test failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.broadcast_test_failed'),
                data: [],
                status: 422,
            );
        }
    }

    public function testPayment(
        TestPaymentRequest $request,
        XenditService $xenditService,
        MercadoPagoService $mercadoPagoService,
    ): JsonResponse {
        $secretKey = $request->secretKey();
        if ($secretKey === '' || $secretKey === SettingSecrets::MASK) {
            $settings = $this->settingService->getSettingValues(SettingTypeEnum::PAYMENT());
            $secretKey = $request->gateway() === 'mercado_pago'
                ? (string) ($settings['mercadoPagoAccessToken'] ?? '')
                : (string) ($settings['xenditSecretKey'] ?? '');
        }

        $result = $request->gateway() === 'mercado_pago'
            ? $mercadoPagoService->testConnection($secretKey)
            : $xenditService->testConnection($secretKey);

        return ApiResponseType::sendJsonResponse(
            success: (bool) $result['success'],
            message: (string) $result['message'],
            data: [
                'gateway_status' => $result['gateway_status'] ?? null,
                'gateway_body' => $result['gateway_body'] ?? null,
                'gateway_error' => $result['gateway_error'] ?? null,
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => ['required', new Enum(SettingTypeEnum::class)],
            ]);

            $type = $request->input('type');

            $method = match ($type) {
                SettingTypeEnum::SYSTEM() => SystemSettingType::class,
                SettingTypeEnum::STORAGE() => StorageSettingType::class,
                SettingTypeEnum::EMAIL() => EmailSettingType::class,
                SettingTypeEnum::PAYMENT() => PaymentSettingType::class,
                SettingTypeEnum::AUTHENTICATION() => AuthenticationSettingType::class,
                SettingTypeEnum::NOTIFICATION() => NotificationSettingType::class,
                SettingTypeEnum::WEB() => WebSettingType::class,
                SettingTypeEnum::APP() => AppSettingType::class,
                SettingTypeEnum::DELIVERY_BOY() => DeliveryBoySettingType::class,
                SettingTypeEnum::SELLER() => SellerSettingType::class,
                SettingTypeEnum::HOME_GENERAL_SETTINGS() => HomeGeneralSettingType::class,
                SettingTypeEnum::SUBSCRIPTION() => SubscriptionSettingType::class,
                SettingTypeEnum::SYSTEM_UPDATE_SETTINGS() => SystemUpdateSettingType::class,
                SettingTypeEnum::ADVERTISEMENT() => AdvertisementSettingType::class,
                SettingTypeEnum::POS_SETTINGS() => Pos_settingsSettingType::class,
                SettingTypeEnum::SHIPPING() => ShippingSettingType::class,
                SettingTypeEnum::WATCH_AND_BUY() => WatchAndBuySettingType::class,
                SettingTypeEnum::BROADCAST_DRIVER() => BroadcastDriverSettingType::class,
                SettingTypeEnum::WHATSAPP() => WhatsAppSettingType::class,
                default => null,
            };

            if (! $method) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.invalid_type'),
                    data: []
                );
            }

            $this->authorize('updateSetting', [Setting::class, $type]);

            $demoMode = DemoMode::isDemoUser($request->user());
            if ($demoMode && $request->hasFile('serviceAccountFile')) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_mode_message_placeholder'),
                    data: [],
                    status: 403,
                );
            }

            $settingsInput = $request->all();
            $existingValues = $this->settingService->getSettingValues($type);

            if ($type === SettingTypeEnum::WHATSAPP() && ! array_key_exists('templateMappings', $settingsInput)) {
                $settingsInput['templateMappings'] = $existingValues['templateMappings'] ?? [];
            }
            if ($type === SettingTypeEnum::WHATSAPP() && ! array_key_exists('enabled', $settingsInput)) {
                $settingsInput['enabled'] = $existingValues['enabled'] ?? false;
            }

            if ($type === SettingTypeEnum::WEB()) {
                $targetCountries = $settingsInput['merchantTargetCountries'] ?? null;
                if (is_array($targetCountries)) {
                    $targetCountries = array_values(array_unique(array_filter(array_map(
                        fn ($country) => strtoupper(trim((string) $country)),
                        $targetCountries
                    ), fn ($country) => preg_match('/^[A-Z]{2}$/', $country) === 1)));
                    $settingsInput['merchantTargetCountries'] = $targetCountries;
                    $settingsInput['merchantTargetCountry'] = $targetCountries[0] ?? '';
                } elseif (! empty($settingsInput['merchantTargetCountry'])) {
                    $country = strtoupper(trim((string) $settingsInput['merchantTargetCountry']));
                    $settingsInput['merchantTargetCountry'] = $country;
                    $settingsInput['merchantTargetCountries'] = [$country];
                } else {
                    $existingCountries = $existingValues['merchantTargetCountries'] ?? [];
                    $legacyCountry = $existingValues['merchantTargetCountry'] ?? '';
                    $settingsInput['merchantTargetCountries'] = is_array($existingCountries) && $existingCountries !== []
                        ? $existingCountries
                        : (filled($legacyCountry) ? [strtoupper((string) $legacyCountry)] : []);
                    $settingsInput['merchantTargetCountry'] = $settingsInput['merchantTargetCountries'][0] ?? '';
                }
            }

            if ($type === SettingTypeEnum::AUTHENTICATION()) {
                $settingsInput = $this->authenticationSettingsInput($request, $existingValues);
                $this->validateFirebaseServiceAccount($request, $settingsInput);
                $this->validateFirebaseDependencies($settingsInput);
            }

            if ($type === SettingTypeEnum::NOTIFICATION()) {
                $this->validatePushDependencies($settingsInput, $existingValues);

                if ($request->exists('whatsappEnabled')) {
                    $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::WHATSAPP()]);
                    $request->validate(['whatsappEnabled' => ['required', 'boolean']]);
                }
            }

            if ($demoMode || in_array($type, [SettingTypeEnum::PAYMENT(), SettingTypeEnum::BROADCAST_DRIVER(), SettingTypeEnum::WHATSAPP()], true)) {
                foreach (SettingSecrets::keysFor($type) as $secretKey) {
                    $incoming = $settingsInput[$secretKey] ?? null;
                    if ($demoMode || $incoming === null || $incoming === '' || $incoming === SettingSecrets::MASK) {
                        $settingsInput[$secretKey] = $existingValues[$secretKey] ?? '';
                    }
                }
            }

            $settings = $method::fromArray($settingsInput);

            $this->handleMediaUploads($request, $settings);

            if ($type === SettingTypeEnum::AUTHENTICATION() && $request->hasFile('serviceAccountFile')) {
                $settings->serviceAccountFile = 'settings/service-account-file.json';
            }

            $values = json_decode($settings->toJson(), true);

            switch ($type) {
                case 'payment':
                    $customErrors = PaymentSettingType::validateXenditSettings($request->all());
                    if (! empty($customErrors)) {
                        return ApiResponseType::sendJsonResponse(
                            success: false,
                            message: __('labels.validation_failed').': '.collect($customErrors)->flatten()->first(),
                            data: $customErrors,
                            status: 422,
                        );
                    }
                    break;
                case 'authentication':
                    $customErrors = AuthenticationSettingType::validateMutuallyExclusiveSettings($request->all());
                    if (! empty($customErrors)) {
                        return ApiResponseType::sendJsonResponse(
                            success: false,
                            message: __('labels.validation_failed').': '.collect($customErrors)->flatten()->first(),
                            data: $customErrors
                        );
                    }
                    if (empty($values['smsGateway'])) {
                        $values['smsGateway'] = AuthenticationSettingType::getSmsGateway($request->all());
                    }
                    break;
                case 'system':
                    $meta = $this->settingService->resolveCurrencyMeta(
                        $values['currency'] ?? null,
                        $values['defaultISO2'] ?? null
                    );
                    $values['currencySymbol'] = $meta['currencySymbol'];
                    $values['countryCode'] = $meta['countryCode'];
                    $values['defaultISO2'] = $meta['defaultISO2'];
                    break;
                case 'subscription':
                    break;
                case 'web':
                    $existingWebSettings = $this->settingService->getSettingValues($type);

                    foreach ([
                        'defaultSeoImage',
                        'returnPolicyCountry',
                        'returnPolicyCategory',
                        'returnPolicyDays',
                        'returnPolicyMethod',
                        'returnFees',
                        'returnShippingFee',
                    ] as $field) {
                        if (! $request->exists($field)) {
                            $values[$field] = $existingWebSettings[$field] ?? $values[$field];
                        }
                    }
                    break;
                case 'home_general_settings':
                    $existingHomeSettings = $this->settingService->getSettingValues($type);

                    // The legacy settings page does not expose the builder's
                    // platform-specific appearance fields. Keep them (and any
                    // existing media) unless the current request explicitly
                    // replaces them.
                    foreach (['homeAppearance', 'desktopIcon', 'desktopActiveIcon', 'desktopBackgroundImage'] as $field) {
                        if (! $request->exists($field)) {
                            $values[$field] = $existingHomeSettings[$field] ?? $values[$field];
                        }
                    }

                    foreach (['icon', 'activeIcon', 'backgroundImage'] as $field) {
                        if (! $request->hasFile($field)) {
                            $values[$field] = $existingHomeSettings[$field] ?? $values[$field];
                        }
                    }
                    break;
                case 'shipping':
                    $existingShipping = Setting::where('variable', 'shipping')->first()?->value ?? [];
                    $values = array_merge($values, $existingShipping, array_intersect_key($values, $request->all()));
                    // Keep the saved (encrypted) password when the field is left
                    // blank on edit; otherwise encrypt the new one at rest.
                    if ($demoMode || empty($request->input('shiprocketPassword'))) {
                        $values['shiprocketPassword'] = $existingShipping['shiprocketPassword'] ?? '';
                    } else {
                        $values['shiprocketPassword'] = Crypt::encryptString($request->input('shiprocketPassword'));
                    }
                    if ($demoMode) {
                        $values['shiprocketWebhookSecret'] = $existingShipping['shiprocketWebhookSecret'] ?? '';
                    }
                    if (! empty($values['shiprocketEnabled']) && empty($values['shiprocketPassword'])) {
                        return ApiResponseType::sendJsonResponse(
                            success: false,
                            message: __('labels.validation_failed').': '.__('labels.shiprocket_password_required'),
                            data: []
                        );
                    }
                    break;
            }

            try {
                $this->authorize('updateSetting', [Setting::class, $type]);
            } catch (AuthorizationException $e) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.unauthorized_access'),
                    data: []
                );
            }

            $existing = Setting::find($type);
            $credentialPath = 'settings/service-account-file.json';
            $credentialBackup = null;
            $credentialExisted = false;

            if ($type === SettingTypeEnum::AUTHENTICATION() && $request->hasFile('serviceAccountFile')) {
                $credentialDisk = Storage::disk('local');
                $credentialExisted = $credentialDisk->exists($credentialPath);
                $credentialBackup = $credentialExisted ? $credentialDisk->get($credentialPath) : null;

                if ($credentialExisted && ! is_string($credentialBackup)) {
                    throw ValidationException::withMessages([
                        'serviceAccountFile' => __('labels.firebase_service_account_store_failed'),
                    ]);
                }

                $storedCredential = $request->file('serviceAccountFile')->storeAs('settings', 'service-account-file.json', 'local');

                if ($storedCredential === false) {
                    if ($credentialExisted) {
                        $credentialDisk->put($credentialPath, $credentialBackup);
                    }

                    throw ValidationException::withMessages([
                        'serviceAccountFile' => __('labels.firebase_service_account_store_failed'),
                    ]);
                }
            }

            try {
                $saved = $this->settingService->saveSetting($type, $values ?? []);

                if ($type === SettingTypeEnum::NOTIFICATION() && $request->exists('whatsappEnabled')) {
                    $whatsappSettings = $this->settingService->getSettingValues(SettingTypeEnum::WHATSAPP());
                    $whatsappSettings['enabled'] = $request->boolean('whatsappEnabled');
                    $this->settingService->saveSetting(SettingTypeEnum::WHATSAPP(), $whatsappSettings);
                }
            } catch (\Throwable $e) {
                if ($type === SettingTypeEnum::AUTHENTICATION() && $request->hasFile('serviceAccountFile')) {
                    if ($credentialExisted) {
                        Storage::disk('local')->put($credentialPath, $credentialBackup);
                    } else {
                        Storage::disk('local')->delete($credentialPath);
                    }
                }

                throw $e;
            }
            if ($type === SettingTypeEnum::BROADCAST_DRIVER()) {
                app(BroadcastSettingsService::class)->apply();
            }
            $saved['redirect_url'] = $request->input('redirect_url') ?? '';

            if ($existing) {
                return ApiResponseType::sendJsonResponse(
                    success: true,
                    message: __('labels.setting_updated_successfully', ['type' => ucfirst(Str::replace('_', ' ', $type))]),
                    data: $saved
                );
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.setting_created_successfully', ['type' => $type]),
                data: $saved
            );
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed').': '.$firstError,
                data: $e->errors(),
                status: 422,
            );
        }
    }

    /**
     * Handle media file uploads and assign paths to the settings object.
     *
     * @param  mixed  $settings
     */
    private function handleMediaUploads(Request $request, $settings): void
    {
        $mediaFields = [
            'logo' => [
                'name' => fn ($file) => 'logo-'.time().'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'darkLogo' => [
                'name' => fn ($file) => 'dark-logo-'.time().'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'favicon' => [
                'name' => fn ($file) => 'favicon-'.time().'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'siteHeaderDarkLogo' => [
                'name' => fn ($file) => 'site-header-dark-logo-'.time().'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'siteHeaderLogo' => [
                'name' => fn ($file) => 'site-header-logo-'.time().'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'siteFooterLogo' => [
                'name' => fn ($file) => 'site-footer-logo-'.time().'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'siteFavicon' => [
                'name' => fn ($file) => 'site-favicon-'.time().'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'backgroundImage' => [
                'name' => fn ($file) => uniqid().'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'icon' => [
                'name' => fn ($file) => uniqid().'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'activeIcon' => [
                'name' => fn ($file) => uniqid().'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'pwaLogo192x192' => [
                'name' => fn ($file) => 'pwa-logo-192x192-'.time().'.png',
                'path' => 'pwa_logos',
            ],

            'pwaLogo512x512' => [
                'name' => fn ($file) => 'pwa-logo-512x512-'.time().'.png',
                'path' => 'pwa_logos',
            ],

            'pwaLogo144x144' => [
                'name' => fn ($file) => 'pwa-logo-144x144-'.time().'.png',
                'path' => 'pwa_logos',
            ],

            'adminSignature' => [
                'name' => fn ($file) => 'admin-signature-'.time().'.'.$file->getClientOriginalExtension(),
                'path' => 'settings',
            ],
        ];

        $imageRules = ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'];
        $fieldRules = [];
        foreach (array_keys($mediaFields) as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $fieldRules[$field] = match ($field) {
                'favicon', 'siteFavicon' => ['image', 'mimes:jpeg,png,jpg,webp,ico', 'max:1024'],
                default => $imageRules,
            };
        }

        if (! empty($fieldRules)) {
            $request->validate($fieldRules);
        }

        foreach ($mediaFields as $field => $config) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $fileName = is_callable($config['name']) ? $config['name']($file) : $config['name'];
                $disk = $config['disk'] ?? 'public';
                $path = $file->storeAs($config['path'], $fileName, $disk);
                $settings->$field = $path;
            }
        }
    }

    private function authenticationSettingsInput(Request $request, array $existing): array
    {
        $sections = [
            'account' => ['identifierMode'],
            'otp' => [
                'smsGateway', 'customSms', 'firebase', 'customSmsUrl', 'customSmsMethod',
                'googleRecaptchaSiteKey', 'customSmsMobileFormat', 'customSmsTextFormatData',
                'customSmsHeaderKey', 'customSmsHeaderValue', 'customSmsParamsKey',
                'customSmsParamsValue', 'customSmsBodyKey', 'customSmsBodyValue',
            ],
            'firebase' => [
                'fireBaseApiKey', 'fireBaseAuthDomain', 'fireBaseDatabaseURL',
                'fireBaseProjectId', 'fireBaseStorageBucket', 'fireBaseMessagingSenderId',
                'fireBaseAppId', 'fireBaseMeasurementId',
            ],
            'social' => ['googleLogin', 'appleLogin'],
        ];
        $section = $request->input('section');

        if ($section === null || $section === '') {
            $input = array_merge($existing, $request->except(['serviceAccountFile']));
        } else {
            if (! isset($sections[$section])) {
                throw ValidationException::withMessages([
                    'section' => __('labels.invalid_authentication_section'),
                ]);
            }

            $input = array_merge($existing, $request->only($sections[$section]));
        }

        if ($section === 'otp' || $section === null || $section === '') {
            $gateway = (string) ($input['smsGateway'] ?? '');
            if ($gateway === 'disabled') {
                $gateway = '';
                $input['smsGateway'] = '';
            }
            $input['customSms'] = $gateway === 'custom';
            $input['firebase'] = $gateway === 'firebase';
        }

        $input['serviceAccountFile'] = $request->hasFile('serviceAccountFile')
            ? 'settings/service-account-file.json'
            : (string) ($existing['serviceAccountFile'] ?? '');

        return $input;
    }

    private function validateFirebaseDependencies(array $settings): void
    {
        $enabled = filter_var($settings['firebase'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($settings['googleLogin'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($settings['appleLogin'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            return;
        }

        $labels = [
            'fireBaseApiKey' => __('labels.firebase_api_key'),
            'fireBaseAuthDomain' => __('labels.firebase_auth_domain'),
            'fireBaseProjectId' => __('labels.firebase_project_id'),
            'fireBaseMessagingSenderId' => __('labels.firebase_messaging_sender_id'),
            'fireBaseAppId' => __('labels.firebase_app_id'),
        ];
        $errors = [];

        foreach ($labels as $field => $label) {
            if (trim((string) ($settings[$field] ?? '')) === '') {
                $errors[$field] = __('labels.firebase_required_for_enabled_feature', ['field' => $label]);
            }
        }

        if (! file_exists(FirebaseService::serviceAccountPath()) && ! request()->hasFile('serviceAccountFile')) {
            $errors['serviceAccountFile'] = __('labels.firebase_service_account_required');
        }

        if (! request()->hasFile('serviceAccountFile') && file_exists(FirebaseService::serviceAccountPath())) {
            $status = app(FirebaseService::class)->configurationStatus($settings);
            if ($status['project_mismatch']) {
                $errors['fireBaseProjectId'] = __('labels.firebase_project_mismatch');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validatePushDependencies(array $settings, array $existingSettings): void
    {
        if (! filter_var($settings['enablePush'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (filter_var($existingSettings['enablePush'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $authentication = $this->settingService->getSettingValues(SettingTypeEnum::AUTHENTICATION());
        $status = app(FirebaseService::class)->configurationStatus($authentication, $settings);

        if (! $status['push_ready']) {
            throw ValidationException::withMessages([
                'enablePush' => __('labels.firebase_push_configuration_required'),
            ]);
        }
    }

    private function validateFirebaseServiceAccount(Request $request, array $settings): void
    {
        $request->validate([
            'serviceAccountFile' => ['nullable', 'file', 'mimes:json,txt', 'max:1024'],
        ]);

        if (! $request->hasFile('serviceAccountFile')) {
            return;
        }

        try {
            $credentials = FirebaseService::parseServiceAccount($request->file('serviceAccountFile')->get());
            $projectId = trim((string) ($settings['fireBaseProjectId'] ?? ''));
            if ($projectId !== '' && $credentials['project_id'] !== $projectId) {
                throw new \InvalidArgumentException(__('labels.firebase_project_mismatch'));
            }
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'serviceAccountFile' => $e->getMessage(),
            ]);
        }
    }

    public function show($variable, ClientLanguageService $clientLanguages): View
    {
        try {

            $setting_variable = SettingTypeEnum::values();
            if (! in_array($variable, $setting_variable)) {
                abort(404, __('labels.invalid_type'));
            }

            $transformedSetting = $this->settingService->getSettingByVariable($variable);

            if (! $transformedSetting) {
                abort(404, __('labels.setting_not_found'));
            }
            $this->authorize('viewSetting', [Setting::class, $variable]);
            $settings = $transformedSetting->toArray(request())['value'] ?? [];
            $viewData = [
                'settings' => $settings,
                'variable' => $variable,
                'isDemoUser' => DemoMode::isDemoUser(request()->user()),
            ];

            if ($variable === 'authentication') {
                if (! isset($settings['smsGateway'])) {
                    $customSmsEnabled = isset($settings['customSms']) && $settings['customSms'];
                    $firebaseEnabled = isset($settings['firebase']) && $settings['firebase'];

                    if ($customSmsEnabled) {
                        $settings['smsGateway'] = 'custom';
                    } elseif ($firebaseEnabled) {
                        $settings['smsGateway'] = 'firebase';
                    } else {
                        $settings['smsGateway'] = '';
                    }
                }

                $notificationSettings = $this->settingService->getSettingValues(SettingTypeEnum::NOTIFICATION());
                $viewData['firebaseStatus'] = app(FirebaseService::class)->configurationStatus($settings, $notificationSettings);
                $viewData['notificationSettings'] = $notificationSettings;
            }

            if ($variable === SettingTypeEnum::NOTIFICATION()) {
                $authenticationSettings = $this->settingService->getSettingValues(SettingTypeEnum::AUTHENTICATION());
                $viewData['firebaseStatus'] = app(FirebaseService::class)->configurationStatus($authenticationSettings, $settings);
                $whatsappSettings = $this->settingService->getSettingValues(SettingTypeEnum::WHATSAPP());
                $viewData['whatsappEnabled'] = (bool) ($whatsappSettings['enabled'] ?? false);
            }

            $viewData['settings'] = $settings;

            if ($variable === SettingTypeEnum::WEB()) {
                $viewData['webLanguages'] = $clientLanguages->languages(LanguageSurfaceEnum::WEB);
            }

            if ($variable === SettingTypeEnum::BROADCAST_DRIVER()) {
                $broadcastSettings = app(BroadcastSettingsService::class);
                $viewData['broadcastConfigured'] = $broadcastSettings->isConfigured();
                $viewData['realtime'] = config('support.public_realtime');
            }

            if ($variable === SettingTypeEnum::SYSTEM()) {
                $systemSettings = $this->settingService->getSettingValues(SettingTypeEnum::SYSTEM());
                $viewData['settings']['productApprovalCriticalFields'] = array_key_exists('productApprovalCriticalFields', $systemSettings)
                    ? $systemSettings['productApprovalCriticalFields']
                    : SystemSettingType::DEFAULT_PRODUCT_APPROVAL_CRITICAL_FIELDS;
                $viewData['productApprovalFieldOptions'] = app(ProductApprovalService::class)->availableCriticalFieldLabels();
            }

            return view('admin.settings.'.$variable, $viewData);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            abort(500, __('labels.something_went_wrong'));
        }
    }

    /**
     * GET settings/shipping — list the available shipping providers.
     */
    public function shippingIndex(): View
    {
        try {
            $this->authorize('viewSetting', [Setting::class, 'shipping']);

            return view('admin.settings.shipping.index', [
                'providers' => $this->shippingProviders(),
            ]);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        }
    }

    /**
     * GET settings/shipping/{provider} — one provider's detail settings.
     */
    public function shippingProvider(string $provider): View
    {
        try {
            $this->authorize('viewSetting', [Setting::class, 'shipping']);

            $providers = $this->shippingProviders();
            if (! isset($providers[$provider]) || ! empty($providers[$provider]['coming_soon'])) {
                abort(404, __('labels.invalid_type'));
            }

            $transformedSetting = $this->settingService->getSettingByVariable('shipping');
            $settings = $transformedSetting ? ($transformedSetting->toArray(request())['value'] ?? []) : [];

            return view('admin.settings.shipping.'.$provider, [
                'settings' => $settings,
                'variable' => 'shipping',
                'provider' => $providers[$provider],
                'isDemoUser' => DemoMode::isDemoUser(request()->user()),
            ]);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        }
    }

    /**
     * Registry of shipping providers shown on the shipping-settings list.
     * Add future carriers here (each gets its own detail view).
     *
     * @return array<string, array<string, mixed>>
     */
    private function shippingProviders(): array
    {
        $shipping = $this->settingService->shiprocketConfig();

        return [
            'manual' => [
                'key' => 'manual',
                'name' => __('labels.manual_shipping'),
                'description' => __('labels.manual_provider_desc'),
                'icon' => 'ti-truck',
                'enabled' => (bool) ($shipping['manualEnabled'] ?? true),
                'url' => route('admin.settings.shipping.provider', 'manual'),
            ],
            'shiprocket' => [
                'key' => 'shiprocket',
                'name' => 'Shiprocket',
                'description' => __('labels.shiprocket_provider_desc'),
                'logo' => 'assets/images/shiprocket.png',
                'enabled' => (bool) ($shipping['shiprocketEnabled'] ?? false),
                'url' => route('admin.settings.shipping.provider', 'shiprocket'),
            ],
            'local_delivery' => [
                'key' => 'local_delivery',
                'name' => __('labels.shipping_method_local_delivery'),
                'description' => __('labels.local_delivery_provider_desc'),
                'icon' => 'ti-moped',
                'enabled' => false,
                'coming_soon' => true,
                'url' => null,
            ],
            'local_pickup' => [
                'key' => 'local_pickup',
                'name' => __('labels.shipping_method_local_pickup'),
                'description' => __('labels.local_pickup_provider_desc'),
                'icon' => 'ti-building-store',
                'enabled' => false,
                'coming_soon' => true,
                'url' => null,
            ],
        ];
    }

    /**
     * Fire a one-off SMS using the values currently sitting in the admin form,
     * without touching the saved authentication setting. Lets the customer
     * verify a gateway config before committing it.
     */
    public function testSms(TestSmsRequest $request, SmsService $smsService): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::AUTHENTICATION()]);
        if (DemoMode::isDemoUser($request->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder'), [], 403);
        }

        try {
            $parsed = PhoneNumberUtil::getInstance()->parse((string) $request->input('test_mobile'));
        } catch (NumberParseException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.invalid_mobile_number'),
                data: ['error' => $e->getMessage()],
            );
        }

        $config = $request->toSmsConfig();

        if (! empty($config['customSmsUrl'])) {
            try {
                OutboundHost::assertPublicHttpUrl((string) $config['customSmsUrl'], __('labels.sms_gateway_url'));
            } catch (\InvalidArgumentException $e) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: $e->getMessage(),
                    data: [],
                );
            }
        }

        $template = $config['customSmsTextFormatData']
            ?: __('labels.test_sms_default_message');

        $message = strtr($template, [
            '{otp}' => '123456',
            '{minutes}' => '10',
            '{brand}' => $this->settingService->getBrandName(),
        ]);

        $result = $smsService->sendSmsWithConfig($parsed, $message, $config);

        return ApiResponseType::sendJsonResponse(
            success: (bool) $result['success'],
            message: (string) $result['message'],
            data: [
                'gateway_status' => $result['gateway_status'] ?? null,
                'gateway_body' => $result['gateway_body'] ?? null,
                'gateway_error' => $result['gateway_error'] ?? null,
            ],
        );
    }

    public function testFirebaseConnection(Request $request, FirebaseService $firebase): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::AUTHENTICATION()]);
        if (DemoMode::isDemoUser($request->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder'), [], 403);
        }
        $validated = $request->validate([
            'fireBaseProjectId' => ['required', 'string', 'max:255'],
            'serviceAccountFile' => ['nullable', 'file', 'mimes:json,txt', 'max:1024'],
        ]);

        try {
            $contents = $request->hasFile('serviceAccountFile')
                ? $request->file('serviceAccountFile')->get()
                : null;
            $result = $firebase->diagnose($contents, $validated['fireBaseProjectId']);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.firebase_connection_successful'),
                data: $result,
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: [],
                status: 422,
            );
        }
    }

    public function testFirebaseIdentity(Request $request, FirebaseService $firebase): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::AUTHENTICATION()]);
        if (DemoMode::isDemoUser($request->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder'), [], 403);
        }
        $validated = $request->validate([
            'provider' => ['required', Rule::in(['phone', 'google', 'apple'])],
            'id_token' => ['required', 'string'],
            'serviceAccountFile' => ['nullable', 'file', 'mimes:json,txt', 'max:1024'],
        ]);

        try {
            $contents = $request->hasFile('serviceAccountFile')
                ? $request->file('serviceAccountFile')->get()
                : null;
            $result = $firebase->verifyIdentityToken($validated['id_token'], $validated['provider'], $contents);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.firebase_auth_test_successful'),
                data: $result,
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.firebase_auth_test_failed'),
                data: ['error' => $e->getMessage()],
                status: 422,
            );
        }
    }

    public function testNotificationSelf(
        Request $request,
        NotificationService $notifications,
        FirebaseService $firebase,
    ): JsonResponse {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::NOTIFICATION()]);
        if (DemoMode::isDemoUser($request->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder'), [], 403);
        }
        $validated = $request->validate([
            'fcm_token' => ['nullable', 'string', 'max:512'],
            'push_only' => ['sometimes', 'boolean'],
        ]);
        $admin = $request->user();
        $pushOnly = (bool) ($validated['push_only'] ?? false);
        $notification = $pushOnly ? null : $notifications->createNotification([
            'user_id' => $admin->id,
            'type' => NotificationTypeEnum::GENERAL,
            'sent_to' => 'admin',
            'role_type' => NotificationRoleTypeEnum::ADMIN,
            'title' => __('labels.firebase_test_notification_title'),
            'message' => __('labels.firebase_test_notification_message'),
            'metadata' => ['test' => true],
        ]);
        $push = [
            'status' => 'skipped',
            'message' => __('labels.firebase_test_browser_token_missing'),
        ];
        $token = trim((string) ($validated['fcm_token'] ?? ''));

        if ($token !== '') {
            $owned = UserFcmToken::query()
                ->where('user_id', $admin->id)
                ->where('fcm_token', $token)
                ->where('device_type', DeviceTypeEnum::WEB->value)
                ->where(function ($query) {
                    $query->where('role_type', NotificationRoleTypeEnum::ADMIN->value)
                        ->orWhereNull('role_type');
                })
                ->exists();

            if (! $owned) {
                $push = [
                    'status' => 'failed',
                    'message' => __('labels.firebase_test_browser_token_invalid'),
                ];
            } else {
                $result = $firebase->sendNotification(
                    $token,
                    __('labels.firebase_test_notification_title'),
                    __('labels.firebase_test_notification_message'),
                    '',
                    ['type' => 'settings_test', 'role_type' => 'admin'],
                );
                $error = (string) ($result['error'] ?? '');
                $staleToken = ! $result['success'] && preg_match('/NotRegistered|registration-token-not-registered|requested entity was not found/i', $error);

                if ($staleToken) {
                    UserFcmToken::query()->where('fcm_token', $token)->delete();
                }

                $push = [
                    'status' => $result['success'] ? 'sent' : ($staleToken ? 'stale' : 'failed'),
                    'message' => $result['success']
                        ? __('labels.firebase_test_push_sent')
                        : ($staleToken
                            ? __('labels.firebase_test_browser_token_stale')
                            : ($result['error'] ?? __('labels.firebase_auth_test_failed'))),
                ];
            }
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: $pushOnly ? __('labels.firebase_test_push_retried') : __('labels.firebase_test_notification_created'),
            data: [
                'inbox' => $pushOnly
                    ? ['status' => 'preserved', 'id' => null]
                    : ['status' => 'created', 'id' => $notification->id],
                'push' => $push,
            ],
        );
    }

    public function toggleShiprocketTestMode(Request $request): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::SHIPPING()]);

        if (app()->environment('production')) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.shiprocket_test_mode_prod_blocked'),
            );
        }

        $enable = $request->boolean('enable');
        if ($enable) {
            touch(FakeShiprocketClient::markerFile());
        } else {
            @unlink(FakeShiprocketClient::markerFile());
        }
        if ($request->boolean('reset')) {
            FakeShiprocketClient::resetState();
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: $enable
                ? __('labels.shiprocket_test_mode_enabled')
                : __('labels.shiprocket_test_mode_disabled'),
            data: ['test_mode' => FakeShiprocketClient::enabled()],
        );
    }

    /**
     * Verify Shiprocket credentials from the admin form (or the saved config)
     * without saving — Shiprocket's auth endpoint is the credential check.
     */
    public function testShiprocket(Request $request, ShiprocketClient $client): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::SHIPPING()]);

        $validated = $request->validate([
            'shiprocketEmail' => ['required', 'email'],
            'shiprocketPassword' => ['nullable', 'string'],
        ]);

        // Blank password on the form → fall back to the saved (encrypted) one.
        $result = $client->testConnection(
            $validated['shiprocketEmail'],
            $validated['shiprocketPassword'] ?? null,
        );

        return ApiResponseType::sendJsonResponse(
            success: (bool) $result['success'],
            message: (string) $result['message'],
            data: [],
        );
    }

    /**
     * Send a one-off test email using the SMTP values currently in the admin
     * form, without saving them. Lets the admin verify an SMTP config before
     * committing it. The values are applied to the mailer config at runtime and
     * the cached smtp mailer is purged so it rebuilds against them.
     */
    public function testSmtp(Request $request): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::EMAIL()]);

        $validated = $request->validate([
            'test_email' => ['required', 'email'],
            'smtpHost' => ['required', 'string', 'max:255'],
            'smtpPort' => ['required', 'integer', Rule::in([25, 465, 587, 2525])],
            'smtpEmail' => ['required', 'email'],
            'smtpPassword' => ['nullable', 'string'],
            'smtpEncryption' => ['required', 'in:tls,ssl'],
        ]);

        try {
            OutboundHost::assertPublicHost($validated['smtpHost'], __('labels.smtp_host'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: [],
            );
        }

        $password = $validated['smtpPassword'] ?? null;
        if (empty($password)) {
            $saved = Setting::find(SettingTypeEnum::EMAIL())?->value;
            $password = $saved['smtpPassword'] ?? null;
        }

        $brand = $this->settingService->getBrandName();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $validated['smtpEncryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $validated['smtpHost'],
            'mail.mailers.smtp.port' => (int) $validated['smtpPort'],
            'mail.mailers.smtp.username' => $validated['smtpEmail'],
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.timeout' => 10,
            'mail.from.address' => $validated['smtpEmail'],
            'mail.from.name' => $brand,
        ]);

        Mail::purge('smtp');

        $mailer = Mail::mailer('smtp');

        try {
            $sentMessage = $mailer->raw(
                __('labels.test_smtp_email_body', ['brand' => $brand]),
                function ($message) use ($validated, $brand) {
                    $message->to($validated['test_email'])
                        ->subject(__('labels.test_smtp_email_subject', ['brand' => $brand]));
                }
            );
        } catch (\Throwable $e) {
            $error = $e instanceof TransportExceptionInterface
                ? $this->redactSmtpSecret($e->getMessage(), $password)
                : __('labels.test_smtp_error');
            $transcript = $this->sanitizeSmtpTranscript(
                $e instanceof TransportExceptionInterface ? $e->getDebug() : null,
            );

            Log::error('Test SMTP failed', [
                'host' => $validated['smtpHost'],
                'port' => $validated['smtpPort'],
                'error' => $error,
                'transcript' => $transcript,
            ]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.test_smtp_error'),
                data: [
                    'gateway_error' => $error,
                    'gateway_body' => $transcript,
                ],
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.test_smtp_sent', ['email' => $validated['test_email']]),
            data: [
                'gateway_body' => $this->sanitizeSmtpTranscript(
                    $sentMessage?->getSymfonySentMessage()->getDebug(),
                ),
            ],
        );
    }

    private function sanitizeSmtpTranscript(?string $transcript): ?string
    {
        if ($transcript === null || trim($transcript) === '') {
            return null;
        }

        $lines = preg_split('/\R/', trim($transcript)) ?: [];
        $credentialsRemaining = 0;

        foreach ($lines as &$line) {
            if (preg_match('/^(.*>\s*AUTH\s+)(LOGIN|PLAIN|XOAUTH2|CRAM-MD5)\b.*$/i', $line, $matches)) {
                $method = strtoupper($matches[2]);
                $line = $matches[1].$method.' [credentials redacted]';
                $credentialsRemaining = match ($method) {
                    'LOGIN' => 2,
                    'CRAM-MD5' => 1,
                    default => 0,
                };

                continue;
            }

            if ($credentialsRemaining > 0 && preg_match('/^(.*>\s*).+$/', $line, $matches)) {
                $line = $matches[1].'[credentials redacted]';
                $credentialsRemaining--;
            }
        }
        unset($line);

        $sanitized = trim(implode(PHP_EOL, $lines));

        return $sanitized !== '' ? $sanitized : null;
    }

    private function redactSmtpSecret(string $message, ?string $password): string
    {
        if ($password === null || $password === '') {
            return $message;
        }

        return str_replace(
            array_unique([$password, urlencode($password), rawurlencode($password), base64_encode($password)]),
            '[redacted]',
            $message,
        );
    }
}
