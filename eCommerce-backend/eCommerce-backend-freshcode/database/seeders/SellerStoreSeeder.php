<?php

namespace Database\Seeders;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Models\Country;
use App\Models\Market;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SellerStoreSeeder extends Seeder
{
    private ?Market $market = null;

    public function run(): void
    {
        DB::transaction(function () {
            // Ensure the seller role exists
            Role::firstOrCreate([
                'name' => DefaultSystemRolesEnum::SELLER(),
                'guard_name' => GuardNameEnum::SELLER(),
            ]);

            // Ensure the default US market exists, then attach every seeded store to it.
            $this->callOnce(MarketSeeder::class);
            $this->market = Market::where('code', 'US')->first();

            // Load JSON file
            $jsonPath = database_path('seeders/Data/sellers_data.json');
            if (!file_exists($jsonPath)) {
                $this->command?->error("JSON file not found at: $jsonPath");
                return;
            }

            $sellersData = json_decode(file_get_contents($jsonPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->command?->error("JSON parse error: " . json_last_error_msg());
                return;
            }

            foreach ($sellersData as $data) {
                $this->createSellerAndStore($data);
            }
        });

        $this->command?->info('Sellers and stores seeded successfully.');
    }

    private function bankFields(array $sellerData): array
    {
        $iso2 = strtolower((string) (Country::query()->where('name', $sellerData['country'] ?? '')->value('iso2') ?: 'us'));
        $fields = [];

        foreach ([
            'bank_name',
            'bank_branch_code',
            'account_holder_name',
            'account_number',
            'routing_number',
            'bank_account_type',
        ] as $key) {
            if (! array_key_exists($key, $sellerData) || $sellerData[$key] === null || $sellerData[$key] === '') {
                continue;
            }

            $fields[$iso2.'_'.$key] = $sellerData[$key];
        }

        return $fields;
    }

    private function createSellerAndStore(array $data): void
    {
        $userData = $data['user'];
        $sellerData = $data['seller'];
        $storeData = $data['store'];

        // 1. Create or update user
        $user = User::withTrashed()->firstOrNew(['email' => $userData['email']]);
        $user->fill([
            'name' => $userData['name'],
            'mobile' => $userData['mobile'],
            'country_code' => $userData['country_code'],
            'password' => Hash::make($userData['password']),
            'status' => true,
            'access_panel' => GuardNameEnum::SELLER(),
            'email_verified_at' => now(),
        ]);
        $user->deleted_at = null;
        $user->save();

        if (!$user->hasRole(DefaultSystemRolesEnum::SELLER())) {
            $user->assignRole(DefaultSystemRolesEnum::SELLER());
        }

        // 2. Create or update seller profile
        $seller = Seller::firstOrNew(['user_id' => $user->id]);
        $seller->fill([
            'address' => $sellerData['address'],
            'city' => $sellerData['city'],
            'landmark' => $sellerData['landmark'] ?? null,
            'state' => $sellerData['state'],
            'zipcode' => $sellerData['zipcode'],
            'country' => $sellerData['country'],
            'country_code' => $sellerData['country_code'],
            'latitude' => $sellerData['latitude'] ?? null,
            'longitude' => $sellerData['longitude'] ?? null,
            'verification_status' => SellerVerificationStatusEnum::Approved(),
            'visibility_status' => SellerVisibilityStatusEnum::Visible(),
            'metadata' => [],
            'dynamic_fields' => $this->bankFields($sellerData),
        ]);
        $seller->save();

        // 3. Create or update store
        $store = Store::firstOrCreate(
            ['seller_id' => $seller->id, 'name' => $storeData['name']],
            [
                'contact_email' => $storeData['contact_email'],
                'contact_number' => $storeData['contact_number'],
                'description' => $storeData['description'],
                'tax_name' => $storeData['tax_name'] ?? null,
                'tax_number' => $storeData['tax_number'] ?? null,
                'currency_code' => $storeData['currency_code'] ?? 'USD',
                'promotional_text' => $storeData['promotional_text'] ?? null,
                'about_us' => $storeData['about_us'] ?? null,
                'metadata' => [],
                'verification_status' => 'approved',
                'visibility_status' => 'visible',
            ]
        );

        // 4. Enable the store in the default US market (store_market pivot).
        if ($this->market) {
            $store->markets()->syncWithoutDetaching([$this->market->id]);
        }

        $this->command?->info("Seeded seller: {$userData['email']} | Store: {$storeData['name']}");
    }
}
