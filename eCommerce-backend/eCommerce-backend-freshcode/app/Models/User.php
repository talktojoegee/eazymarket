<?php

namespace App\Models;

use App\Enums\GuardNameEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\UserLoginTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Notifications\AdminPasswordResetNotification;
use App\Notifications\SellerPasswordResetNotification;
use App\Support\DemoMode;
use App\Types\Settings\AuthenticationSettingType;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

/**
 * @method static create(array $array)
 * @method static where(string $string, mixed $email)
 * @method static find(mixed $user_id)
 */
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens, SoftDeletes, InteractsWithMedia, MustVerifyEmailTrait {
        HasRoles::hasPermissionTo as private hasStoredPermissionTo;
    }

    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        if (DemoMode::isDemoUser($this)) {
            return true;
        }

        return $this->hasStoredPermissionTo($permission, $guardName);
    }

    protected $appends = ['profile_image'];

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'country_code',
        'referral_code',
        'friends_code',
        'referral_prompt_dismissed_at',
        'reward_points',
        'remember_token',
        'status',
        'password',
        'access_panel',
        'iso_2',
        'country',
        'firebase_uid',
        'email_verified_at',
        'mobile_verified_at',
        'logged_in_type',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'mobile' => 'string',
            'country_code' => 'string',
            'referral_code' => 'string',
            'friends_code' => 'string',
            'referral_prompt_dismissed_at' => 'datetime',
            'reward_points' => 'integer',
            'remember_token' => 'string',
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'status' => 'boolean',
            'access_panel' => GuardNameEnum::class,
            'logged_in_type' => UserLoginTypeEnum::class,
            'iso_2' => 'string',
            'country' => 'string',
        ];
    }

    /**
     * Override the default guard name from HasRoles trait.
     *
     * @return string
     */
    public function getDefaultGuardName(): string
    {
        return GuardNameEnum::fromString($this->access_panel->value)->value;
    }

    /**
     * Number of days a soft-deleted account can still be restored.
     */
    public static function deletionGraceDays(): int
    {
        return (int)config('account.deletion_grace_days', 30);
    }

    /**
     * True when the account is soft-deleted but still inside the restore
     * window (i.e. recoverable). Anonymised/expired records return false.
     */
    public function isPendingDeletion(): bool
    {
        return $this->trashed()
            && $this->deleted_at !== null
            && $this->deleted_at->greaterThan(now()->subDays(self::deletionGraceDays()));
    }

    /**
     * The moment after which a soft-deleted account can no longer be restored
     * and becomes eligible for anonymisation. Null when not deleted.
     */
    public function deletionGraceEndsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->trashed() && $this->deleted_at !== null
            ? $this->deleted_at->copy()->addDays(self::deletionGraceDays())
            : null;
    }

    /**
     * Permanently release the account's unique identifiers and strip personal
     * data, while keeping the row so FK-linked orders / wallet / settlement
     * history stay intact. Revokes any lingering API tokens. The record stays
     * soft-deleted; only its PII and unique slots are cleared.
     */
    public function anonymizeAndRelease(): void
    {
        $this->forceFill([
            'name' => 'Deleted User',
            'email' => 'deleted+' . $this->id . '@deleted.invalid',
            'mobile' => null,
            'firebase_uid' => null,
            'password' => null,
            'remember_token' => null,
            'mobile_verified_at' => null,
            'email_verified_at' => null,
        ])->saveQuietly();

        try {
            $this->tokens()->delete();
        } catch (\Throwable $e) {
        }
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Request-level cache for seller data.
     */
    protected $cachedSeller = null;
    protected $sellerCacheChecked = false;

    public function seller()
    {
        if ($this->sellerCacheChecked) {
            return $this->cachedSeller;
        }

        $seller = Seller::where('user_id', $this->id)->first();
        if ($seller) {
            $this->cachedSeller = $seller;
            $this->sellerCacheChecked = true;
            return $seller;
        }
        $sellerUser = SellerUser::where('user_id', $this->id)->first();
        if ($sellerUser) {
            $this->cachedSeller = Seller::find($sellerUser->seller_id);
            $this->sellerCacheChecked = true;
            return $this->cachedSeller;
        }

        $this->cachedSeller = null;
        $this->sellerCacheChecked = true;
        return null;
    }

    public function belongsToSeller(int $sellerId): bool
    {
        if (Seller::where('user_id', $this->id)->where('id', $sellerId)->exists()) {
            return true;
        }

        return SellerUser::where('user_id', $this->id)->where('seller_id', $sellerId)->exists();
    }

    /**
     * The user's customer wallet — used for marketplace checkout and the
     * customer-facing app. Sellers and delivery boys each have a separate
     * wallet of their own type; use sellerWallet() / deliveryBoyWallet()
     * to access those.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id', 'id')
            ->where('type', WalletTypeEnum::CUSTOMER->value);
    }

    /**
     * The user's seller wallet (earnings/settlements). Nullable: only exists
     * when this user has a matching seller row.
     */
    public function sellerWallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id', 'id')
            ->where('type', WalletTypeEnum::SELLER->value);
    }

    /**
     * The user's delivery-boy wallet (earnings/settlements). Nullable: only
     * exists when this user has a matching delivery_boys row.
     */
    public function deliveryBoyWallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id', 'id')
            ->where('type', WalletTypeEnum::DELIVERY_BOY->value);
    }

    public function deliveryBoy(): HasOne
    {
        return $this->hasOne(DeliveryBoy::class, 'user_id', 'id');
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(UserFcmToken::class, 'user_id', 'id');
    }

    public function routeNotificationForFirebase()
    {
        return $this->fcmTokens()->pluck('fcm_token')->filter()->toArray();
    }

    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Scope users to one or more markets by checking:
     * 1. Explicit user_market preference
     * 2. Orders placed in the markets
     * 3. Delivery addresses in countries belonging to the markets
     * 4. User profile country belonging to the markets
     * 5. Fallback for unassigned customers if the default market is selected
     */
    public function scopeInMarkets(Builder $query, array $marketIds): Builder
    {
        $marketIds = array_values(array_filter(array_map('intval', $marketIds)));
        if (empty($marketIds)) {
            return $query;
        }

        $defaultMarketId = (int) Setting::where('variable', 'default_market_id')->value('value');
        if (! $defaultMarketId) {
            $defaultMarketId = (int) Market::where('is_default', true)->value('id');
        }
        $includesDefault = $defaultMarketId && in_array($defaultMarketId, $marketIds, true);

        return $query->where(function (Builder $q) use ($marketIds, $includesDefault) {
            $q->whereHas('markets', function ($mq) use ($marketIds) {
                $mq->whereIn('markets.id', $marketIds);
            })
            ->orWhereHas('orders', function ($oq) use ($marketIds) {
                $oq->whereIn('orders.market_id', $marketIds);
            })
            ->orWhereExists(function ($aq) use ($marketIds) {
                $aq->select(DB::raw(1))
                    ->from('addresses')
                    ->join('market_country', function ($join) {
                        $join->whereRaw('LOWER(market_country.country_iso2) = LOWER(addresses.country_code)');
                    })
                    ->whereColumn('addresses.user_id', 'users.id')
                    ->whereIn('market_country.market_id', $marketIds);
            })
            ->orWhereExists(function ($uq) use ($marketIds) {
                $uq->select(DB::raw(1))
                    ->from('market_country')
                    ->whereRaw('LOWER(market_country.country_iso2) = LOWER(users.iso_2)')
                    ->whereIn('market_country.market_id', $marketIds);
            });

            if ($includesDefault) {
                $q->orWhere(function (Builder $fallbackQ) {
                    $fallbackQ->whereDoesntHave('markets')
                        ->whereDoesntHave('orders')
                        ->whereNotExists(function ($aq) {
                            $aq->select(DB::raw(1))
                                ->from('addresses')
                                ->join('market_country', function ($join) {
                                    $join->whereRaw('LOWER(market_country.country_iso2) = LOWER(addresses.country_code)');
                                })
                                ->whereColumn('addresses.user_id', 'users.id');
                        })
                        ->whereNotExists(function ($uq) {
                            $uq->select(DB::raw(1))
                                ->from('market_country')
                                ->whereRaw('LOWER(market_country.country_iso2) = LOWER(users.iso_2)');
                        });
                });
            }
        });
    }

    public function getProfileImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::PROFILE_IMAGE());
    }

    public function OrderPaymentTransactions(): HasMany
    {
        return $this->hasMany(OrderPaymentTransaction::class, 'user_id', 'id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::PROFILE_IMAGE())->singleFile();
    }

    /**
     * Whether this customer account has both channels verified — required
     * before they can place an order so delivery ops can reach them on
     * either channel.
     */
    public function isFullyVerified(): bool
    {
        return $this->email_verified_at !== null && $this->mobile_verified_at !== null;
    }

    /**
     * Whether the customer is verified enough to place an order, honouring the
     * admin's account identifier mode: only the required (primary) channel must
     * be verified when the other is optional.
     */
    public function isVerifiedForCheckout(): bool
    {
        $mode = AuthenticationSettingType::currentMode();

        if ($mode === 'mobile_primary') {
            return $this->mobile_verified_at !== null;
        }

        if ($mode === 'email_primary') {
            return $this->email_verified_at !== null;
        }

        return $this->isFullyVerified();
    }

    /**
     * Key used by the password-reset broker. Falls back to the mobile number so
     * mobile-only accounts (no email) can still receive and validate a reset
     * token through the standard broker.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->email ?? $this->mobile ?? (string)$this->id;
    }

    public function revokeTokens(bool $keepCurrent = false): void
    {
        $query = $this->tokens();
        $current = $this->currentAccessToken();

        if ($keepCurrent && $current instanceof PersonalAccessToken) {
            $query->whereKeyNot($current->getKey());
        }

        $query->delete();
    }

    /**
     * Delivery zones associated with the user.
     *
     * @deprecated Phase 2 — replaced by ->market() and the market_country pivot.
     *             Kept temporarily so existing call sites (including the rider app)
     *             keep compiling; the underlying user_zone pivot is dropped in
     *             P2-100005, after which calls to this relationship return empty.
     */
    public function deliveryZones(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryZone::class, 'user_zone', 'user_id', 'zone_id')
            ->withTimestamps();
    }

    /**
     * Markets this user is enrolled in. The `user_market` pivot enforces
     * UNIQUE(user_id), so today this is effectively a 1:1 relationship —
     * use ->market() for the singular shortcut. The many-to-many shape is
     * kept so the UNIQUE constraint can be relaxed later without a model
     * rewrite.
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'user_market')->withTimestamps();
    }

    /**
     * Stores a seller staff user is scoped to (the `store_user` pivot). An empty
     * set means "no restriction" — the staff member sees every store their
     * seller owns. The seller OWNER (sellers.user_id) is never scoped here.
     */
    public function assignedStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')->withTimestamps();
    }

    /**
     * The user's home market (the one row in `user_market`). May be null for
     * legacy users not yet backfilled; MarketService::resolveForUser() falls
     * through to IP detection / Setting `default_market_id` in that case.
     */
    public function market(): object
    {
        return $this->markets()->first();
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->access_panel && $this->access_panel->value === 'admin') {
            $this->notify(new AdminPasswordResetNotification($token));
        } elseif ($this->access_panel && $this->access_panel->value === 'seller') {
            $this->notify(new SellerPasswordResetNotification($token));
        } else {
            $this->notify(new ResetPassword($token));
        }
    }

    protected static function booted(): void
    {
        static::deleting(function ($user) {
            $user->clearMediaCollection(SpatieMediaCollectionName::PROFILE_IMAGE());
        });
    }
}
