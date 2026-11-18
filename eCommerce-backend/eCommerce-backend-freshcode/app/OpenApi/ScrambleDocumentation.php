<?php

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

class ScrambleDocumentation implements OperationTransformer
{
    private const GENERIC_SUMMARIES = [
        'Display a listing of the resource',
        'Store a newly created resource in storage',
        'Display the specified resource',
        'Update the specified resource in storage',
        'Remove the specified resource from storage',
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $uri = $routeInfo->route->uri();
        $key = strtoupper($routeInfo->method).' '.$uri;

        if ($group = $this->groupFor($uri)) {
            $operation->setTags([$group]);
        }

        if ($this->summaryNeedsReplacement($operation->summary)) {
            $operation->summary($this->summaryFor($key, $operation->method, $uri));
        }

        if (trim($operation->description) === '') {
            $operation->description(rtrim($operation->summary, '.').'.');
        }

        $this->documentOptionalAuthentication($operation, $routeInfo);

        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && trim($parameter->description) === '') {
                $parameter->description($this->parameterDescription($parameter->name, $parameter->in, $parameter->required));
            }
        }

        foreach ($this->additionalQueryParameters()[$key] ?? [] as $name => $metadata) {
            $this->addQueryParameter($operation, $name, $metadata);
        }

        foreach ($this->additionalHeaderParameters()[$key] ?? [] as $name => $metadata) {
            $this->addHeaderParameter($operation, $name, $metadata);
        }

        if (in_array($key, $this->webhookOperations(), true) && ! $operation->requestBodyObject) {
            $operation->addRequestBodyObject(
                RequestBodyObject::make()
                    ->required()
                    ->description('Webhook event payload supplied by the payment or shipping provider.')
                    ->setContent(
                        'application/json',
                        Schema::fromType((new ObjectType)->additionalProperties(new MixedType)),
                    ),
            );
        }

        if ($operation->requestBodyObject) {
            $visited = [];
            foreach ($operation->requestBodyObject->content as $content) {
                $this->documentSchema($content, $visited);
            }
        }
    }

    public static function document(OpenApi $openApi): void
    {
        $documenter = new self;
        $visited = [];

        if (trim($openApi->info->description) === '') {
            $openApi->info->setDescription('Customer, seller, catalog, checkout, payment, shipping, and marketplace endpoints for Hypercommerce.');
        }

        foreach ($openApi->components->schemas as $schema) {
            $documenter->documentSchema($schema, $visited);
        }

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->responses ?? [] as $response) {
                    $documenter->documentResponse($response, $visited);
                }
            }
        }
    }

    private function documentOptionalAuthentication(Operation $operation, RouteInfo $routeInfo): void
    {
        $uri = $routeInfo->route->uri();

        if ($this->routeRequiresAuthentication($routeInfo) || ! $this->supportsOptionalAuthentication($uri)) {
            return;
        }

        $operation->security = [
            new SecurityRequirement([]),
            new SecurityRequirement(['http' => []]),
        ];

        $detail = str_starts_with($uri, 'api/watch-and-buy')
            ? 'Bearer authentication is optional and adds customer-specific state such as likes and seen status.'
            : 'Bearer authentication is optional and may add customer-specific values such as the unread notification count.';

        if (! str_contains($operation->description, $detail)) {
            $operation->description(trim($operation->description).' '.$detail);
        }
    }

    private function routeRequiresAuthentication(RouteInfo $routeInfo): bool
    {
        return collect($routeInfo->route->gatherMiddleware())
            ->contains(fn (string $middleware) => Str::is(['auth', 'auth:*'], $middleware));
    }

    private function supportsOptionalAuthentication(string $uri): bool
    {
        return Str::is([
            'api/settings',
            'api/settings/*',
            'api/watch-and-buy/*',
        ], $uri);
    }

    private function groupFor(string $uri): ?string
    {
        foreach ($this->groupRules() as $prefix => $group) {
            if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
                return $group;
            }
        }

        return null;
    }

    private function groupRules(): array
    {
        return [
            'api/user/support' => 'Customer Support',
            'api/broadcasting/auth' => 'Customer Support',
            'api/user/notifications' => 'Notifications',
            'api/seller/notifications' => 'Seller Notifications',
            'api/seller/orders' => 'Seller Orders',
            'api/seller/shipping' => 'Seller Shipping',
            'api/seller/store-addon-items' => 'Seller Addon Inventory',
            'api/seller/product-conditions' => 'Seller Catalog',
            'api/seller/product-images' => 'Seller Products',
            'api/seller/products' => 'Seller Products',
            'api/seller/categories' => 'Seller Catalog',
            'api/seller/attributes' => 'Seller Attributes',
            'api/seller/brands' => 'Seller Brands',
            'api/seller/geo' => 'Seller Geography',
            'api/seller/markets' => 'Seller Markets',
            'api/seller/commissions' => 'Seller Earnings',
            'api/seller/stores' => 'Seller Stores',
            'api/seller/tax-classes' => 'Seller Tax Classes',
            'api/seller/tax-rates' => 'Seller Tax Rates',
            'api/seller/devices' => 'Seller Devices',
            'api/webhooks' => 'Shipping Webhooks',
            'api/flutterwave' => 'Payment Webhooks',
            'api/mercado-pago' => 'Payment Webhooks',
            'api/paystack' => 'Payment Webhooks',
            'api/webhook/razorpay' => 'Payment Webhooks',
            'api/stripe' => 'Payment Webhooks',
            'api/xendit' => 'Payment Webhooks',
            'api/devices' => 'Devices',
            'api/test-fcm' => 'Notifications',
            'api/test-fcms' => 'Notifications',
            'api/geo' => 'Geography',
            'api/languages' => 'Languages',
            'api/language-labels' => 'Languages',
            'api/seller-landing' => 'Seller Landing Page',
        ];
    }

    private function summaryNeedsReplacement(string $summary): bool
    {
        $summary = trim($summary);

        return $summary === ''
            || in_array($summary, self::GENERIC_SUMMARIES, true)
            || preg_match('/^(GET|POST|PUT|PATCH|DELETE) \/(?:api|seller)\//', $summary) === 1
            || $summary === 'Get Order Tracking';
    }

    private function summaryFor(string $key, string $method, string $uri): string
    {
        $summaries = [
            'POST api/register' => 'Register a customer account',
            'GET api/home-tab' => 'Get homepage navigation tabs',
            'GET api/geo-detect' => 'Detect the customer market and location',
            'GET api/currencies' => 'List supported currencies',
            'GET api/markets' => 'List active markets',
            'GET api/markets/current' => 'Get the current market',
            'POST api/markets/switch' => 'Switch the current market',
            'POST api/user/cart/buy-now' => 'Create a buy-now cart',
            'POST api/user/orders/{orderSlug}/pay' => 'Retry payment for an order',
            'POST api/user/orders/{orderSlug}/cancel-payment' => 'Cancel an order payment',
            'GET api/user/orders/{orderSlug}/tracking' => 'Get order tracking details',
            'DELETE api/user/delete-account' => 'Delete the customer account',
            'GET api/settings' => 'Get public application settings',
            'GET api/settings/firebase-config' => 'Get Firebase configuration',
            'GET api/settings/auth-config' => 'Get authentication configuration',
            'GET api/settings/check-version' => 'Check application version compatibility',
            'GET api/settings/variables' => 'List public setting variables',
            'GET api/settings/{variable}' => 'Get a public setting variable',
            'GET api/languages' => 'List available client languages',
            'GET api/language-labels' => 'Get translated client labels',
            'GET api/seller-landing' => 'Get the public seller landing page',
            'POST api/seller/forget-password' => 'Start seller password recovery',
            'POST api/seller/login/verify-otp' => 'Verify the seller login OTP',
            'POST api/seller/forget-password/send-otp' => 'Send a seller password-reset OTP',
            'POST api/seller/forget-password/verify-otp' => 'Verify a seller password-reset OTP',
            'POST api/seller/reset-password' => 'Reset the seller password',
            'GET api/user/addresses' => 'List customer addresses',
            'POST api/user/addresses' => 'Create a customer address',
            'GET api/user/addresses/{id}' => 'Get a customer address',
            'PUT api/user/addresses/{id}' => 'Update a customer address',
            'DELETE api/user/addresses/{id}' => 'Delete a customer address',
            'GET api/seller/categories/{id}' => 'Get a seller catalog category',
            'GET api/seller/brands/groups' => 'List seller brand groups',
            'GET api/seller/brands/search' => 'Search seller brands',
            'GET api/seller/categories/tree' => 'Get the seller category tree',
            'GET api/seller/categories/search' => 'Search seller categories',
            'POST api/devices/sync' => 'Register a customer device token',
            'DELETE api/devices' => 'Remove a customer device token',
            'POST api/seller/devices/sync' => 'Register a seller device token',
            'DELETE api/seller/devices' => 'Remove a seller device token',
            'GET api/seller/geo/states' => 'List states for a seller country',
            'GET api/seller/geo/cities' => 'Search cities for a seller country',
            'POST api/mercado-pago/webhook' => 'Handle a Mercado Pago webhook',
            'POST api/paystack/webhook' => 'Handle a Paystack webhook',
            'GET api/paystack/callback' => 'Handle the Paystack payment callback',
            'POST api/stripe/webhook' => 'Handle a Stripe webhook',
            'POST api/xendit/webhook' => 'Handle an Xendit webhook',
            'POST api/webhooks/shippingrocket' => 'Handle a Shiprocket webhook',
            'POST api/test-fcm' => 'Send a test push notification',
            'POST api/test-fcms' => 'Send test push notifications',
            'POST api/seller/orders/shipments/{shipmentId}/retry-booking' => 'Retry shipment booking',
            'POST api/seller/orders/shipments/{shipmentId}/update-lines' => 'Update shipment line quantities',
            'POST api/seller/orders/shipments/{shipmentId}/ndr-action' => 'Submit a shipment NDR action',
            'POST api/seller/orders/shipments/{shipmentId}/sync' => 'Synchronize shipment tracking',
            'POST api/seller/orders/{id}/fulfill' => 'Fulfill seller order items',
            'GET api/seller/stores/search' => 'Search seller stores',
            'GET api/seller/tax-classes/search' => 'Search seller tax classes',
            'GET api/seller/tax-rates/search' => 'Search seller tax rates',
            'GET api/seller/tax-rates/{id}' => 'Get a seller tax rate',
            'GET api/user/support/thread' => 'Get the customer support thread',
            'GET api/user/support/thread/messages' => 'Get older support messages',
            'GET api/user/support/thread/updates' => 'Get support thread updates',
            'GET api/user/support/realtime' => 'Get support realtime configuration',
            'GET api/user/support/topics' => 'List customer support topics',
            'POST api/user/support/sessions' => 'Start a customer support session',
            'POST api/user/support/sessions/{slug}/messages' => 'Send a support message',
            'POST api/user/support/sessions/{slug}/typing' => 'Update the support typing state',
            'POST api/user/support/sessions/{slug}/read' => 'Mark support messages as read',
            'POST api/user/support/sessions/{slug}/callbacks' => 'Request a support callback',
            'POST api/user/support/sessions/{slug}/resolve' => 'Resolve a support session',
            'POST api/user/support/sessions/{slug}/rating' => 'Rate a support session',
            'GET api/user/support/attachments/{media}' => 'Download a support attachment',
            'POST api/broadcasting/auth' => 'Authorize a support broadcast channel',
            'POST api/seller/shipping/packages' => 'Create a seller shipping package',
            'POST api/seller/shipping' => 'Create a seller shipping profile',
            'GET api/seller/shipping/products-search' => 'Search products for a shipping profile',
            'POST api/seller/shipping/{profileId}' => 'Update a seller shipping profile',
            'DELETE api/seller/shipping/{profileId}' => 'Delete a seller shipping profile',
            'POST api/seller/shipping/{profileId}/zones' => 'Create a seller shipping zone',
            'GET api/seller/shipping/{profileId}/zones/{zoneId}' => 'Get a seller shipping zone',
            'POST api/seller/shipping/{profileId}/zones/{zoneId}' => 'Update a seller shipping zone',
            'DELETE api/seller/shipping/{profileId}/zones/{zoneId}' => 'Delete a seller shipping zone',
            'POST api/seller/shipping/packages/{packageId}' => 'Update a seller shipping package',
            'DELETE api/seller/shipping/packages/{packageId}' => 'Delete a seller shipping package',
            'POST api/seller/shipping/packages/{packageId}/promote-default' => 'Set the default seller shipping package',
        ];

        return $summaries[$key] ?? $this->fallbackSummary($method, $uri);
    }

    private function fallbackSummary(string $method, string $uri): string
    {
        $segments = collect(explode('/', $uri))
            ->reject(fn (string $segment) => $segment === 'api' || str_starts_with($segment, '{'))
            ->map(fn (string $segment) => Str::headline($segment))
            ->values();
        $subject = Str::lower($segments->last() ?: 'resource');
        $verb = match (strtolower($method)) {
            'get' => 'Get',
            'post' => 'Create or process',
            'put', 'patch' => 'Update',
            'delete' => 'Delete',
            default => 'Process',
        };

        return $verb.' '.$subject;
    }

    private function additionalQueryParameters(): array
    {
        return [
            'GET api/currencies' => [
                'search' => ['string', false, 'Search by currency code, name, or symbol.'],
                'active_only' => ['boolean', false, 'Return only active currencies.'],
            ],
            'POST api/mercado-pago/webhook' => [
                'data_id' => ['string', false, 'Mercado Pago resource identifier.'],
                'data.id' => ['string', false, 'Alternative Mercado Pago resource identifier.'],
            ],
            'GET api/user/referral/earnings' => [
                'per_page' => ['integer', false, 'Number of referral earnings per page.'],
            ],
            'GET api/seller/dashboard' => [
                'store_id' => ['integer', false, 'Limit dashboard data to an accessible store.'],
            ],
            'GET api/seller/dashboard/summary' => [
                'store_id' => ['integer', false, 'Limit summary data to an accessible store.'],
            ],
            'GET api/seller/dashboard/chart' => [
                'store_id' => ['integer', false, 'Limit chart data to an accessible store.'],
            ],
            'GET api/seller/products/search' => [
                'q' => ['string', false, 'Alias of search for matching a product title.'],
            ],
            'GET api/seller/system-users' => [
                'per_page' => ['integer', false, 'Number of system users per page.'],
            ],
            'GET api/seller/watch-and-buy/reels' => [
                'per_page' => ['integer', false, 'Number of reels per page.'],
            ],
            'GET api/seller/brands/groups' => [
                'page' => ['integer', false, 'Page number for pagination.'],
                'search' => ['string', false, 'Search brands by title.'],
                'type' => ['string', false, 'Requested brand-group result type.'],
                'group' => ['string', false, 'Filter brands by global or category group.'],
                'scope_id' => ['integer', false, 'Category scope identifier.'],
            ],
            'GET api/seller/categories/tree' => [
                'page' => ['integer', false, 'Page number for pagination.'],
                'search' => ['string', false, 'Search categories by title.'],
                'depth' => ['integer', false, 'Tree depth to return.'],
            ],
            'GET api/seller/geo/states' => [
                'country' => ['string', true, 'Two-letter ISO country code.'],
            ],
            'GET api/seller/geo/cities' => [
                'country' => ['string', true, 'Two-letter ISO country code.'],
                'search' => ['string', false, 'City name prefix to search.'],
            ],
            'GET api/seller/markets/search' => [
                'only_active' => ['boolean', false, 'Return only active markets.'],
                'search' => ['string', false, 'Search by market code or name.'],
                'limit' => ['integer', false, 'Maximum number of markets to return.'],
                'find' => ['string', false, 'Return the market with this exact code.'],
            ],
            'GET api/seller/commissions/statement' => [
                'date_range' => ['integer', false, 'Number of recent days to include.'],
                'mode' => ['string', false, 'Statement mode: full or settled.'],
            ],
            'GET api/seller/notifications' => [
                'per_page' => ['integer', false, 'Number of notifications per page.'],
            ],
            'GET api/seller/shipping/products-search' => [
                'find_in[]' => ['array', false, 'Product identifiers to load exactly.'],
                'search' => ['string', false, 'Search products by title.'],
            ],
            'GET api/seller/stores/search' => [
                'search' => ['string', false, 'Search stores by name.'],
                'q' => ['string', false, 'Alias of search for matching a store name.'],
            ],
        ];
    }

    private function additionalHeaderParameters(): array
    {
        return [
            'GET api/geo-detect' => [
                'CF-IPCountry' => [false, 'Country code supplied by Cloudflare.'],
                'X-Country-Code' => [false, 'Country code supplied by the reverse proxy.'],
                'X-AppEngine-Country' => [false, 'Country code supplied by Google App Engine.'],
            ],
            'POST api/flutterwave/webhook' => [
                'verif-hash' => [true, 'Flutterwave webhook verification hash.'],
            ],
            'POST api/mercado-pago/webhook' => [
                'x-request-id' => [true, 'Mercado Pago webhook request identifier.'],
                'x-signature' => [true, 'Mercado Pago webhook signature.'],
            ],
            'POST api/paystack/webhook' => [
                'x-paystack-signature' => [true, 'Paystack webhook signature.'],
            ],
            'POST api/webhook/razorpay' => [
                'X-Razorpay-Signature' => [true, 'Razorpay webhook signature.'],
                'X-Razorpay-Event-Id' => [false, 'Razorpay event identifier used for idempotency.'],
            ],
            'POST api/stripe/webhook' => [
                'Stripe-Signature' => [true, 'Stripe webhook signature.'],
            ],
            'POST api/xendit/webhook' => [
                'x-callback-token' => [true, 'Xendit webhook verification token.'],
            ],
            'POST api/webhooks/shippingrocket' => [
                'x-api-key' => [true, 'Shiprocket webhook API key.'],
            ],
        ];
    }

    private function webhookOperations(): array
    {
        return [
            'POST api/flutterwave/webhook',
            'POST api/mercado-pago/webhook',
            'POST api/paystack/webhook',
            'POST api/webhook/razorpay',
            'POST api/stripe/webhook',
            'POST api/xendit/webhook',
            'POST api/webhooks/shippingrocket',
        ];
    }

    private function addQueryParameter(Operation $operation, string $name, array $metadata): void
    {
        $normalizedName = rtrim($name, '[]');
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && rtrim($parameter->name, '[]') === $normalizedName) {
                if (trim($parameter->description) === '') {
                    $parameter->description($metadata[2]);
                }
                if ($metadata[1]) {
                    $parameter->required(true);
                }

                return;
            }
        }

        $type = match ($metadata[0]) {
            'integer' => new IntegerType,
            'boolean' => new BooleanType,
            'array' => (new ArrayType)->setItems(new IntegerType),
            default => new StringType,
        };
        $operation->addParameters([
            Parameter::make($name, 'query')
                ->required($metadata[1])
                ->description($metadata[2])
                ->setSchema(Schema::fromType($type)),
        ]);
    }

    private function addHeaderParameter(Operation $operation, string $name, array $metadata): void
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && strtolower($parameter->name) === strtolower($name)) {
                return;
            }
        }

        $operation->addParameters([
            Parameter::make($name, 'header')
                ->required($metadata[0])
                ->description($metadata[1])
                ->setSchema(Schema::fromType(new StringType)),
        ]);
    }

    private function parameterDescription(string $name, string $location, bool $required): string
    {
        $label = Str::lower(Str::headline(str_replace(['[]', '.*'], '', $name)));

        return match ($location) {
            'path' => Str::ucfirst($label).' identifying the requested resource.',
            'query' => ($required ? 'Required ' : 'Optional ').$label.' query parameter.',
            'header' => Str::ucfirst($label).' request header.',
            default => Str::ucfirst($label).' request parameter.',
        };
    }

    private function propertyDescription(string $name): string
    {
        $descriptions = [
            'id' => 'Unique resource identifier.',
            'email' => 'Email address.',
            'mobile' => 'Mobile phone number.',
            'password' => 'Account password.',
            'password_confirmation' => 'Password confirmation value.',
            'page' => 'Page number for pagination.',
            'per_page' => 'Number of records per page.',
            'search' => 'Search term used to filter results.',
            'store_id' => 'Store identifier.',
            'product_id' => 'Product identifier.',
            'order_id' => 'Order identifier.',
            'status' => 'Resource status.',
            'locale' => 'Language locale code.',
            'surface' => 'Client surface requesting the translations.',
            'latitude' => 'Geographic latitude.',
            'longitude' => 'Geographic longitude.',
        ];

        return $descriptions[$name]
            ?? Str::ucfirst(Str::lower(Str::headline(str_replace(['.*', '_'], ['', ' '], $name)))).' submitted with the request.';
    }

    private function documentResponse(Response|Reference $response, array &$visited): void
    {
        if ($response instanceof Reference) {
            $resolved = $response->resolve();
            if ($resolved instanceof Response) {
                $this->documentResponse($resolved, $visited);
            }

            return;
        }

        if (trim($response->description) === '') {
            $response->setDescription(match ((string) $response->code) {
                '200' => 'Successful response.',
                '201' => 'Resource created successfully.',
                '202' => 'Request accepted for processing.',
                '204' => 'Request completed successfully with no response body.',
                '400' => 'Invalid request.',
                '401' => 'Authentication is required.',
                '403' => 'The authenticated user is not authorized.',
                '404' => 'The requested resource was not found.',
                '409' => 'The request conflicts with the current resource state.',
                '422' => 'The submitted data failed validation.',
                '429' => 'Too many requests.',
                '500' => 'Unexpected server error.',
                default => 'API response.',
            });
        }

        foreach ($response->content as $content) {
            $this->documentSchema($content, $visited);
        }
    }

    private function documentSchema(Schema|Reference $schema, array &$visited): void
    {
        if ($schema instanceof Reference) {
            $resolved = $schema->resolve();
            if ($resolved instanceof Schema) {
                $this->documentSchema($resolved, $visited);
            }

            return;
        }

        $this->documentType($schema->type, $visited);
    }

    private function documentType(Type $type, array &$visited): void
    {
        $objectId = spl_object_id($type);
        if (isset($visited[$objectId])) {
            return;
        }
        $visited[$objectId] = true;

        if ($type instanceof Reference) {
            $resolved = $type->resolve();
            if ($resolved instanceof Schema) {
                $this->documentSchema($resolved, $visited);
            }

            return;
        }

        if ($type instanceof ObjectType) {
            foreach ($type->properties as $name => $property) {
                if (! $property) {
                    continue;
                }
                if (trim($property->description) === '') {
                    $property->setDescription($this->propertyDescription($name));
                }
                $this->documentType($property, $visited);
            }
            if ($type->additionalProperties) {
                $this->documentType($type->additionalProperties, $visited);
            }
        }

        if ($type instanceof ArrayType) {
            $this->documentType($type->items, $visited);
            foreach ($type->prefixItems as $item) {
                $this->documentType($item, $visited);
            }
        }

        if (property_exists($type, 'items') && is_array($type->items)) {
            foreach ($type->items as $item) {
                if ($item instanceof Type) {
                    $this->documentType($item, $visited);
                }
            }
        }
    }
}
