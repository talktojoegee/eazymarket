<?php

namespace App\Console\Commands;

use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use App\Models\SellerUser;
use Illuminate\Console\Command;
use App\Models\Seller;
use App\Models\SellerSubscriptionUsage;
use App\Models\Store;
use App\Models\Product;
use App\Models\Role;

class SyncSellerSubscriptionUsage extends Command
{
    protected $signature = 'subscription:sync-usage';

    protected $description = 'Sync existing seller usage into seller_subscription_usages table';

    public function handle()
    {
        $sellers = Seller::all();

        foreach ($sellers as $seller) {


            $storeCount = Store::where('seller_id', $seller->id)->count();

            SellerSubscriptionUsage::updateOrCreate(
                [
                    'seller_id' => $seller->id,
                    'key' => SubscriptionPlanKeyEnum::STORE_LIMIT()
                ],
                [
                    'used' => $storeCount
                ]
            );



            $productCount = Product::where('seller_id', $seller->id)->count();

            SellerSubscriptionUsage::updateOrCreate(
                [
                    'seller_id' => $seller->id,
                    'key' => SubscriptionPlanKeyEnum::PRODUCT_LIMIT()
                ],
                [
                    'used' => $productCount
                ]
            );



            $userCount = SellerUser::where('seller_id', $seller->id)->count();

            SellerSubscriptionUsage::updateOrCreate(
                [
                    'seller_id' => $seller->id,
                    'key' => SubscriptionPlanKeyEnum::SYSTEM_USER_LIMIT()
                ],
                [
                    'used' => $userCount
                ]
            );



            $roleCount = Role::where('guard_name', 'seller')->where('team_id', $seller->id)->count();

            SellerSubscriptionUsage::updateOrCreate(
                [
                    'seller_id' => $seller->id,
                    'key' => SubscriptionPlanKeyEnum::ROLE_LIMIT()
                ],
                [
                    'used' => $roleCount
                ]
            );

        }

        $this->info('Seller usage synced successfully.');
    }
}
