<?php

namespace App\Http\Controllers;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Requests\ShippingZone\StoreShippingZoneRequest;
use App\Http\Requests\ShippingZone\UpdateShippingZoneRequest;
use App\Models\ShippingProfile;
use App\Models\ShippingZone;
use App\Services\ShippingZoneService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * Zones nested under a ShippingProfile. AJAX-driven — the seller never
 * leaves the profile-edit page; create/update/delete go through this
 * controller via the zone modal.
 *
 * `show()` returns the full zone payload (including coverage) so the
 * edit-mode modal can repopulate state.
 */
class ShippingZoneController extends Controller
{
    use ChecksPermissions;

    public function __construct(protected ShippingZoneService $service)
    {
    }

    private function ensureShippingPermission(string $permission): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission),
            403,
            trans('labels.permission_denied')
        );
    }

    /**
     * GET /seller/shipping/{profile}/zones/{zone}
     *
     * Returns the zone with countries / states / cities / rates so the edit
     * modal can rehydrate every section without an extra round-trip.
     */
    public function show($profileId, $zoneId): JsonResponse
    {
        $this->ensureShippingPermission(SellerPermissionEnum::SHIPPING_PROFILE_VIEW());
        try {
            $profile = $this->resolveProfile($profileId);
            $zone = $profile->zones()
                ->with(['countries:id,iso2,name', 'serviceabilityRules', 'rates'])
                ->findOrFail($zoneId);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'ok',
                data: ['zone' => $this->presentZone($zone)],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * POST /seller/shipping/{profile}/zones
     */
    public function store(StoreShippingZoneRequest $request, $profileId): JsonResponse
    {
        $this->ensureShippingPermission(SellerPermissionEnum::SHIPPING_PROFILE_CREATE());
        try {
            $profile = $this->resolveProfile($profileId);
            $zone = $this->service->create($profile, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.shipping_zone_created'),
                data: ['zone' => $this->presentZone($zone)],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * POST /seller/shipping/{profile}/zones/{zone}
     */
    public function update(UpdateShippingZoneRequest $request, $profileId, $zoneId): JsonResponse
    {
        $this->ensureShippingPermission(SellerPermissionEnum::SHIPPING_PROFILE_EDIT());
        try {
            $profile = $this->resolveProfile($profileId);
            $zone = $profile->zones()->findOrFail($zoneId);
            $zone = $this->service->update($zone, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.shipping_zone_updated'),
                data: ['zone' => $this->presentZone($zone)],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * DELETE /seller/shipping/{profile}/zones/{zone}
     */
    public function delete($profileId, $zoneId): JsonResponse
    {
        $this->ensureShippingPermission(SellerPermissionEnum::SHIPPING_PROFILE_DELETE());
        try {
            $profile = $this->resolveProfile($profileId);
            $zone = $profile->zones()->findOrFail($zoneId);
            $this->service->delete($zone);

            return ApiResponseType::sendJsonResponse(true, __('labels.shipping_zone_deleted'), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * Resolve a profile that the current seller owns. 404 if not theirs.
     */
    protected function resolveProfile($profileId): ShippingProfile
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            throw new ModelNotFoundException('Seller not found');
        }

        return ShippingProfile::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
            ->findOrFail($profileId);
    }

    /**
     * Flatten a zone for the modal/list JSON payload.
     *
     * Rate/ETA data now lives on the rates child rows. `rate` (singular)
     * is the default rate the modal hydrates from while we're single-rate;
     * `rates` (plural) ships the full list for forward compatibility with
     * the multi-rate UI.
     */
    protected function presentZone(ShippingZone $zone): array
    {
        $zone->loadMissing(['countries:id,iso2,name', 'serviceabilityRules', 'rates']);
        $primary = $zone->rates->first();

        return [
            'id'              => $zone->id,
            'name'            => $zone->name,
            'method'          => $zone->method,
            'priority'        => $zone->priority,
            'is_active'       => (bool) $zone->is_active,
            'serviceability_mode' => $zone->serviceability_mode ?? 'none',
            'operating_hours' => $zone->operating_hours ?? [],
            'rate'            => $primary ? $this->presentRate($primary) : null,
            'rates'           => $zone->rates->map(fn ($r) => $this->presentRate($r))->values()->all(),
            'coverage' => [
                'countries' => $zone->countries->map(fn ($c) => [
                    'iso2' => $c->iso2,
                    'name' => $c->name,
                ])->values()->all(),
            ],
            'serviceability' => $zone->serviceabilityRules->map(fn ($r) => [
                'country_iso2' => $r->country_iso2,
                'match_type'   => $r->match_type,
                'value'        => $r->value,
                'value_to'     => $r->value_to,
            ])->values()->all(),
            'summary' => [
                'countries'      => $zone->countries->count(),
                'serviceability' => $zone->serviceabilityRules->count(),
            ],
        ];
    }

    protected function presentRate(\App\Models\ShippingZoneRate $rate): array
    {
        return [
            'id'           => $rate->id,
            'name'         => $rate->name,
            'rate_type'    => $rate->rate_type,
            'flat_rate'    => $rate->flat_rate,
            'free_above'   => $rate->free_above,
            'weight_rules' => $rate->weight_rules ?? [],
            'price_rules'  => $rate->price_rules ?? [],
            'eta_min'      => $rate->eta_min,
            'eta_max'      => $rate->eta_max,
            'eta_unit'     => $rate->eta_unit,
            'priority'     => $rate->priority,
            'is_active'    => (bool) $rate->is_active,
        ];
    }
}
