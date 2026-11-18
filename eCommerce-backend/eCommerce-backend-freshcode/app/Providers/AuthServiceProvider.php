<?php

namespace App\Providers;

use App\Enums\AdminPermissionEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\AddonGroup;
use App\Models\OrderItem;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariantAddon;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyWithdrawalRequest;
use App\Models\Faq;
use App\Models\Promo;
use App\Models\SellerStatement;
use App\Models\SellerWithdrawalRequest;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Product;
use App\Models\ProductCondition;
use App\Models\ProductFaq;
use App\Models\Review;
use App\Models\Seller;
use App\Models\SellerFeedback;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\OrderItemReturn;
use App\Models\Setting;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Notification;
use App\Models\OnboardingPage;
use App\Models\LandingPageBuilderDocument;
use App\Models\SplashScreen;
use App\Policies\AddonGroupPolicy;
use App\Policies\HomeLayoutPolicy;
use App\Policies\StoreAddonItemPolicy;
use App\Policies\StoreProductVariantAddonPolicy;
use App\Policies\BrandPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OnboardingPagePolicy;
use App\Policies\LandingPageBuilderDocumentPolicy;
use App\Policies\SplashScreenPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\DeliveryBoyPolicy;
use App\Policies\DeliveryBoyAssignmentPolicy;
use App\Policies\DeliveryBoyWithdrawalRequestPolicy;
use App\Policies\FaqPolicy;
use App\Policies\SellerStatementPolicy;
use App\Policies\SellerWithdrawalRequestPolicy;
use App\Policies\WalletPolicy;
use App\Policies\GlobalAttributePolicy;
use App\Policies\GlobalAttributeValuePolicy;
use App\Policies\ProductConditionPolicy;
use App\Policies\ProductFaqPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PromoPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\RolePolicy;
use App\Policies\OrderPolicy;
use App\Policies\SellerFeedbackPolicy;
use App\Policies\SellerPolicy;
use App\Policies\SettingPolicy;
use App\Policies\StorePolicy;
use App\Policies\SystemUserPolicy;
use App\Policies\TaxClassPolicy;
use App\Policies\TaxRatePolicy;
use App\Policies\OrderReturnPolicy;
use App\Models\Wallet;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Models\WatchBuyStatus;
use App\Support\DatabaseSchema;
use App\Support\DemoMode;
use App\Policies\WatchBuyProfilePolicy;
use App\Policies\WatchBuyReelPolicy;
use App\Policies\WatchBuyStatusPolicy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;


class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Role::class => RolePolicy::class,
        AddonGroup::class => AddonGroupPolicy::class,
        StoreAddonItem::class => StoreAddonItemPolicy::class,
        StoreProductVariantAddon::class => StoreProductVariantAddonPolicy::class,
        Brand::class => BrandPolicy::class,
        Category::class => CategoryPolicy::class,
        \App\Models\HomeLayout::class => HomeLayoutPolicy::class,
        ProductCondition::class => ProductConditionPolicy::class,
        GlobalProductAttribute::class => GlobalAttributePolicy::class,
        GlobalProductAttributeValue::class => GlobalAttributeValuePolicy::class,
        Seller::class => SellerPolicy::class,
        SellerFeedback::class => SellerFeedbackPolicy::class,
        SellerOrder::class => OrderPolicy::class,
        SellerOrderItem::class => OrderPolicy::class,
        OrderItem::class => OrderPolicy::class,
        OrderItemReturn::class => OrderReturnPolicy::class,
        TaxClass::class => TaxClassPolicy::class,
        TaxRate::class => TaxRatePolicy::class,
        User::class => SystemUserPolicy::class,
        Setting::class => SettingPolicy::class,
        Store::class => StorePolicy::class,
        Product::class => ProductPolicy::class,
        ProductFaq::class => ProductFaqPolicy::class,
        Promo::class => PromoPolicy::class,
        Faq::class => FaqPolicy::class,
        Review::class => ReviewPolicy::class,
        DeliveryBoy::class => DeliveryBoyPolicy::class,
        DeliveryBoyAssignment::class => DeliveryBoyAssignmentPolicy::class,
        DeliveryBoyWithdrawalRequest::class => DeliveryBoyWithdrawalRequestPolicy::class,
        SellerWithdrawalRequest::class => SellerWithdrawalRequestPolicy::class,
        Notification::class => NotificationPolicy::class,
        Wallet::class => WalletPolicy::class,
        SellerStatement::class => SellerStatementPolicy::class,
        WatchBuyProfile::class => WatchBuyProfilePolicy::class,
        WatchBuyReel::class => WatchBuyReelPolicy::class,
        WatchBuyStatus::class => WatchBuyStatusPolicy::class,
        OnboardingPage::class => OnboardingPagePolicy::class,
        LandingPageBuilderDocument::class => LandingPageBuilderDocumentPolicy::class,
        SplashScreen::class => SplashScreenPolicy::class,
    ];
    /**
     * Register services.
     */

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        $this->registerPolicies();
        Gate::before(static fn (User $user): ?bool => DemoMode::isDemoUser($user) ? true : null);
        try {
            if (app(DatabaseSchema::class)->hasTable('permissions')) {
                $this->syncPermissions([
                    'admin' => AdminPermissionEnum::values(),
                    'seller' => SellerPermissionEnum::values(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning($e->getMessage());
        }
    }

    private function syncPermissions(array $permissionsByGuard): void
    {
        $table = config('permission.table_names.permissions', 'permissions');
        $existing = DB::table($table)
            ->whereIn('guard_name', array_keys($permissionsByGuard))
            ->get(['name', 'guard_name'])
            ->mapWithKeys(fn (object $permission): array => [
                $permission->guard_name."\0".$permission->name => true,
            ]);

        $now = now();
        $missing = [];

        foreach ($permissionsByGuard as $guard => $permissions) {
            foreach ($permissions as $permission) {
                if ($existing->has($guard."\0".$permission)) {
                    continue;
                }

                $missing[] = [
                    'name' => $permission,
                    'guard_name' => $guard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($missing !== []) {
            $inserted = DB::table($table)->insertOrIgnore($missing);

            if ($inserted > 0) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }
    }
}
