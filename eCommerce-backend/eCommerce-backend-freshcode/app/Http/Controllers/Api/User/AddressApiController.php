<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\AddressTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\AddressResource;
use App\Models\Address;
use App\Models\Country;
use App\Models\GeoCity;
use App\Models\GeoPincode;
use App\Models\GeoState;
use App\Services\GeoImportService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

#[Group('Addresses')]
class AddressApiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     */
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'int', default: 15, example: 20)]
    #[QueryParameter('query', description: 'Search term to filter addresses across multiple fields (address_line1, address_line2, city, landmark, state, zipcode, mobile).', type: 'string', example: 'main street')]
    #[QueryParameter('address_type', description: 'Filter by address type.', type: 'string', example: 'home')]
    #[QueryParameter('city', description: 'Filter by city name (partial match).', type: 'string', example: 'New York')]
    #[QueryParameter('state', description: 'Filter by state name (partial match).', type: 'string', example: 'California')]
    #[QueryParameter('country', description: 'Filter by country name (partial match).', type: 'string', example: 'United States')]
    #[QueryParameter('is_default_billing', description: 'Filter to only the default billing address.', type: 'bool', example: true)]
    #[QueryParameter('is_default_shipping', description: 'Filter to only the default shipping address.', type: 'bool', example: true)]
    #[QueryParameter('sort', description: 'Field to sort by.', type: 'string', example: 'city')]
    #[QueryParameter('order', description: 'Sort order.', type: 'string', example: 'asc')]
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Address::where('user_id', Auth::id());
            $perPage = $request->input('per_page', 15);

            if ($request->filled('query')) {
                $searchQuery = $request->input('query');
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('address_line1', 'LIKE', '%' . $searchQuery . '%')
                        ->orWhere('address_line2', 'LIKE', '%' . $searchQuery . '%')
                        ->orWhere('city', 'LIKE', '%' . $searchQuery . '%')
                        ->orWhere('landmark', 'LIKE', '%' . $searchQuery . '%')
                        ->orWhere('state', 'LIKE', '%' . $searchQuery . '%')
                        ->orWhere('zipcode', 'LIKE', '%' . $searchQuery . '%')
                        ->orWhere('mobile', 'LIKE', '%' . $searchQuery . '%');
                });
            }

            if ($request->filled('address_type')) {
                $query->where('address_type', $request->input('address_type'));
            }

            if ($request->filled('city')) {
                $query->where('city', 'LIKE', '%' . $request->input('city') . '%');
            }

            if ($request->filled('state')) {
                $query->where('state', 'LIKE', '%' . $request->input('state') . '%');
            }

            if ($request->filled('country')) {
                $query->where('country', 'LIKE', '%' . $request->input('country') . '%');
            }

            if ($request->has('is_default_billing')) {
                $query->where('is_default_billing', $request->boolean('is_default_billing'));
            }

            if ($request->has('is_default_shipping')) {
                $query->where('is_default_shipping', $request->boolean('is_default_shipping'));
            }

            if ($request->filled('sort') && $request->filled('order')) {
                $sortField = $request->input('sort');
                $sortOrder = $request->input('order');

                $allowedSortFields = [
                    'id', 'address_line1', 'city', 'state', 'zipcode',
                    'address_type', 'country', 'is_default_billing',
                    'is_default_shipping', 'created_at', 'updated_at'
                ];

                if (in_array($sortField, $allowedSortFields)) {
                    $query->orderBy($sortField, $sortOrder);
                }
            } else {
                $query->orderByDesc('is_default_shipping')
                    ->orderByDesc('is_default_billing')
                    ->orderByDesc('created_at');
            }

            $addresses = $query->paginate($perPage);

            $addresses->getCollection()->transform(function ($address) {
                return new AddressResource($address);
            });

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Addresses retrieved successfully',
                data: [
                    'current_page' => $addresses->currentPage(),
                    'last_page' => $addresses->lastPage(),
                    'per_page' => $addresses->perPage(),
                    'total' => $addresses->total(),
                    'data' => $addresses->items(),
                ]);
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Failed to retrieve addresses',
                status: 500
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'address_line1' => 'required|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'required|string|max:100',
                'landmark' => 'nullable|string|max:255',
                'state' => 'required|string|max:100',
                'zipcode' => 'nullable|string|max:10',
                'mobile' => 'required|string|max:15',
                'address_type' => ['sometimes', 'required', new Enum(AddressTypeEnum::class)],
                'country' => 'required|string|max:100',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'is_default_billing' => 'sometimes|boolean',
                'is_default_shipping' => 'sometimes|boolean',
            ]);

            $validatedData['country_code'] = $this->resolveCountryCode($validatedData['country']);

            $this->validateAddressGeo(
                $validatedData['country_code'],
                $validatedData['state'] ?? null,
                $validatedData['city'] ?? null,
                $validatedData['zipcode'] ?? null,
            );

            $this->validateZipcode($validatedData['country_code'], $validatedData['zipcode'] ?? null);

            $validatedData['user_id'] = Auth::id();

            $address = Address::create($validatedData);

            $this->syncDefaultFlags($address);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Address created successfully',
                data: new AddressResource($address),
                status: 201
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: $e->errors(),
            );
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Failed to create address ' . $e->getMessage(),
                data: null,
                status: 500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $address = Address::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$address) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'Address not found',
                    data: null,
                    status: 404
                );
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Address retrieved successfully',
                data: new AddressResource($address)
            );
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Failed to retrieve address',
                data: null,
                status: 500
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $address = Address::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$address) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'Address not found',
                    data: null,
                    status: 404
                );
            }

            $validatedData = $request->validate([
                'address_line1' => 'sometimes|required|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'sometimes|required|string|max:100',
                'landmark' => 'nullable|string|max:255',
                'state' => 'sometimes|required|string|max:100',
                'zipcode' => 'nullable|string|max:10',
                'mobile' => 'sometimes|required|string|max:15',
                'address_type' => ['required', new Enum(AddressTypeEnum::class)],
                'country' => 'sometimes|required|string|max:100',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'is_default_billing' => 'sometimes|boolean',
                'is_default_shipping' => 'sometimes|boolean',
            ]);

            if (array_key_exists('country', $validatedData)) {
                $validatedData['country_code'] = $this->resolveCountryCode($validatedData['country']);
                $iso2 = $validatedData['country_code'];
            } else {
                unset($validatedData['country_code']);
                $iso2 = $address->country_code;
            }

            $effectiveZip = array_key_exists('zipcode', $validatedData) ? $validatedData['zipcode'] : $address->zipcode;

            $this->validateAddressGeo(
                $iso2,
                array_key_exists('state', $validatedData) ? $validatedData['state'] : $address->state,
                array_key_exists('city', $validatedData) ? $validatedData['city'] : $address->city,
                $effectiveZip,
            );

            $this->validateZipcode($iso2, $effectiveZip);

            $address->update($validatedData);

            $this->syncDefaultFlags($address->fresh());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Address updated successfully',
                data: new AddressResource($address->fresh())
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: $e->errors(),
            );
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Failed to update address',
                data: null,
                status: 500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $address = Address::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$address) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'Address not found',
                    data: null,
                    status: 404
                );
            }

            $address->delete();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Address deleted successfully',
                data: null
            );
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Failed to delete address',
                data: null,
                status: 500
            );
        }
    }

    /**
     * Mark the specified address as the user's default for billing, shipping, or both.
     */
    public function setDefault(Request $request, string $id): JsonResponse
    {
        try {
            $address = Address::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$address) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'Address not found',
                    data: null,
                    status: 404
                );
            }

            $validated = $request->validate([
                'type' => 'sometimes|in:billing,shipping,both',
            ]);

            $type = $validated['type'] ?? 'both';

            $address->update([
                'is_default_billing' => in_array($type, ['billing', 'both']) ? true : $address->is_default_billing,
                'is_default_shipping' => in_array($type, ['shipping', 'both']) ? true : $address->is_default_shipping,
            ]);

            $this->syncDefaultFlags($address->fresh());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Default address updated successfully',
                data: new AddressResource($address->fresh())
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: $e->errors(),
            );
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Failed to update default address',
                data: null,
                status: 500
            );
        }
    }

    /**
     * Resolve the ISO2 country code from the submitted country name. The app
     * sends only the country name (chosen from the geo picker); the canonical
     * code is derived here so a mismatched or missing client code can't slip in.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function resolveCountryCode(string $country): string
    {
        $iso2 = Country::where('name', trim($country))->value('iso2');
        if (!$iso2) {
            throw ValidationException::withMessages([
                'country' => [__('messages.address_country_not_serviceable')],
            ]);
        }

        return strtoupper($iso2);
    }

    /**
     * Require a zipcode only for countries that use postal codes. Countries in
     * config('geo.no_postcode_countries') (UAE, Hong Kong, …) are city/state
     * driven and may save an address without one.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateZipcode(?string $countryCode, ?string $zipcode): void
    {
        $iso2 = strtoupper(trim((string) $countryCode));
        $noPostcode = array_map('strtoupper', (array) config('geo.no_postcode_countries', []));

        if (in_array($iso2, $noPostcode, true)) {
            return;
        }

        if (trim((string) $zipcode) === '') {
            throw ValidationException::withMessages([
                'zipcode' => [__('messages.address_zipcode_required')],
            ]);
        }
    }

    /**
     * Validate that (country, state, city) form a real, consistent triple.
     *
     * Strict for covered countries only: the country must be one the platform
     * sells to (a market country); if that country has geo data, the state must
     * be a real state of it and the city a real city of that state. Countries
     * with no geo rows yet fall through and accept free-text (no lock-out).
     * Values are expected to arrive canonical from the geo pickers.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateAddressGeo(?string $countryCode, ?string $state, ?string $city, ?string $pincode = null): void
    {
        $iso2 = strtoupper(trim((string) $countryCode));
        if ($iso2 === '') {
            return;
        }

        $marketCountries = app(GeoImportService::class)->marketCountryIso2s();
        if (!in_array($iso2, $marketCountries, true)) {
            throw ValidationException::withMessages([
                'country_code' => [__('messages.address_country_not_serviceable')],
            ]);
        }

        if ($this->usesPincode($iso2)) {
            $this->validateAgainstPincode($iso2, (string) $state, (string) $city, (string) $pincode);

            return;
        }

        $this->validateAgainstCityState($iso2, (string) $state, (string) $city);
    }

    /**
     * Zipcode-driven countries: the (country, pincode) row is the source of
     * truth. When the pincode is in the dataset, the state must match it and the
     * city must be one of the pincode's cities. Pincodes absent from the dataset
     * (uncovered) are accepted as-is.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateAgainstPincode(string $iso2, string $state, string $city, string $pincode): void
    {
        $pincode = trim($pincode);
        if ($pincode === '') {
            return;
        }

        $rows = GeoPincode::where('country_iso2', $iso2)->where('postcode', $pincode)->get(['city', 'state_name']);
        if ($rows->isEmpty()) {
            return;
        }

        $errors = [];

        $state = trim($state);
        if ($state !== '') {
            $stateOk = $rows->contains(fn ($r) => \Illuminate\Support\Str::slug((string) $r->state_name) === \Illuminate\Support\Str::slug($state));
            if (!$stateOk) {
                $errors['state'] = [__('messages.address_state_invalid')];
            }
        }

        $city = trim($city);
        if ($city !== '') {
            $cityOk = $rows->contains(fn ($r) => \Illuminate\Support\Str::slug((string) $r->city) === \Illuminate\Support\Str::slug($city));
            if (!$cityOk) {
                $errors['city'] = [__('messages.address_city_invalid')];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * City-driven countries (no postal codes): validate the state + city against
     * the geo_states / geo_cities tables. Countries with no geo rows fall
     * through and accept free-text.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateAgainstCityState(string $iso2, string $state, string $city): void
    {
        if (!GeoState::where('country_iso2', $iso2)->exists()) {
            return;
        }

        $state = trim($state);
        $city  = trim($city);
        $errors = [];

        $stateCode = null;
        if ($state !== '') {
            $stateCode = GeoState::where('country_iso2', $iso2)->where('name', $state)->value('state_code');
            if ($stateCode === null && !GeoState::where('country_iso2', $iso2)->where('name', $state)->exists()) {
                $errors['state'] = [__('messages.address_state_invalid')];
            }
        }

        if ($city !== '') {
            $cityQuery = GeoCity::where('country_iso2', $iso2)->where('name', $city);
            if ($state !== '' && !isset($errors['state']) && $stateCode !== null) {
                $cityQuery->where('state_code', $stateCode);
            }
            if (!$cityQuery->exists()) {
                $errors['city'] = [__('messages.address_city_invalid')];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function usesPincode(string $iso2): bool
    {
        $noPostcode = array_map('strtoupper', (array) config('geo.no_postcode_countries', []));

        return !in_array(strtoupper($iso2), $noPostcode, true);
    }

    private function syncDefaultFlags(Address $address): void
    {
        foreach (['is_default_billing', 'is_default_shipping'] as $flag) {
            if (! $address->{$flag}) {
                continue;
            }

            Address::where('user_id', $address->user_id)
                ->where('id', '!=', $address->id)
                ->where($flag, true)
                ->update([$flag => false]);
        }
    }
}
