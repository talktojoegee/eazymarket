<?php

namespace App\Services;

use App\Models\Country;
use App\Models\DynamicSellerField;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicSellerFieldService
{
    private const LEGACY_IMAGE_COLLECTIONS = [
        'business_license',
        'articles_of_incorporation',
        'national_identity_card',
        'authorized_signature',
        'address_proof',
        'voided_check',
    ];

    public function forRequest(Request $request)
    {
        $country = $this->countryFromRequest($request);

        return $country ? $this->forCountry($country->iso2) : DynamicSellerField::query()->whereRaw('1 = 0')->get();
    }

    public function forCountry(string $iso2)
    {
        $country = Country::query()->whereRaw('UPPER(iso2) = ?', [strtoupper(trim($iso2))])->first();
        if (!$country) {
            return collect();
        }

        $this->ensureCountryDefaults($country);

        return DynamicSellerField::query()
            ->whereHas('countries', fn ($query) => $query->where('countries.id', $country->id)->whereNull('dynamic_seller_field_country.archived_at'))
            ->orderBy('section')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function forCountries(array $iso2s)
    {
        return $this->queryForCountries($iso2s)
            ->with('countries:id,name,iso2')
            ->orderBy('section')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function valuesForSeller(Seller $seller, iterable $fields): array
    {
        $values = $seller->dynamic_fields ?? [];

        foreach ($fields as $field) {
            if ($field->type !== 'image') {
                continue;
            }

            $mediaUrl = $seller->getFirstMediaUrl('dynamic_field_'.$field->key);
            if ($mediaUrl === '') {
                $legacyCollection = collect(self::LEGACY_IMAGE_COLLECTIONS)
                    ->first(fn ($collection) => $field->key === $collection || str_ends_with($field->key, '_'.$collection));
                if ($legacyCollection) {
                    $mediaUrl = $seller->getFirstMediaUrl($legacyCollection);
                }
            }

            if ($mediaUrl !== '') {
                $values[$field->key] = $mediaUrl;
            }
        }

        return $values;
    }

    public function queryForCountries(array $iso2s)
    {
        $countries = Country::query()
            ->whereIn(DB::raw('UPPER(iso2)'), collect($iso2s)->map(fn ($iso2) => strtoupper(trim($iso2)))->all())
            ->get();

        foreach ($countries as $country) {
            $this->ensureCountryDefaults($country);
        }

        return DynamicSellerField::query()
            ->whereHas('countries', fn ($query) => $query->whereIn('countries.iso2', $countries->pluck('iso2')));
    }

    public function countryFromRequest(Request $request): ?Country
    {
        $iso2 = $request->input('country_iso2');
        $countryValue = $request->input('country');
        $country = null;

        if (filled($iso2)) {
            $country = Country::query()->whereRaw('UPPER(iso2) = ?', [strtoupper(trim($iso2))])->first();
        }

        if (!$country && filled($countryValue)) {
            $country = Country::query()
                ->where('name', $countryValue)
                ->orWhereRaw('UPPER(iso2) = ?', [strtoupper(trim($countryValue))])
                ->orWhereRaw('UPPER(iso3) = ?', [strtoupper(trim($countryValue))])
                ->first();
        }

        if (!$country && $request->route('id')) {
            $country = Seller::query()->with('countryDetails')->find($request->route('id'))?->countryDetails;
        }

        return $country;
    }

    private function ensureCountryDefaults(Country $country): void
    {
        DB::transaction(function () use ($country) {
            $lockedCountry = Country::query()->lockForUpdate()->findOrFail($country->id);

            $sections = [
                'bank_details' => 'seller_bank_fields_initialized',
                'business_documents' => 'seller_business_documents_initialized',
            ];

            foreach ($sections as $section => $initializedColumn) {
                if ($lockedCountry->{$initializedColumn}) {
                    continue;
                }

                $countryFields = $lockedCountry->dynamicSellerFields()->where('section', $section)->get();
                if ($countryFields->isNotEmpty()) {
                    $lockedCountry->{$initializedColumn} = true;
                    continue;
                }

                foreach ($this->defaultTemplates() as $order => $field) {
                    if ($field['section'] !== $section) {
                        continue;
                    }

                    $key = strtolower($lockedCountry->iso2).'_'.$field['key'];
                    $dynamicField = DynamicSellerField::query()->where('key', $key)->first();

                    if (!$dynamicField) {
                        $dynamicField = DynamicSellerField::create([
                            'section' => $section,
                            'key' => $key,
                            'label' => $field['label'],
                            'type' => $field['type'],
                            'options' => $field['options'] ?? null,
                            'required' => true,
                            'system' => true,
                            'sort_order' => $order,
                        ]);
                    }

                    $lockedCountry->dynamicSellerFields()->syncWithoutDetaching([$dynamicField->id]);
                }

                $lockedCountry->{$initializedColumn} = true;
            }

            $lockedCountry->seller_fields_initialized = true;
            $lockedCountry->save();
        });
    }

    private function defaultTemplates(): array
    {
        $fields = [
            ['section' => 'bank_details', 'key' => 'bank_name', 'label' => 'Bank name', 'type' => 'text'],
            ['section' => 'bank_details', 'key' => 'bank_branch_code', 'label' => 'Bank branch code', 'type' => 'text'],
            ['section' => 'bank_details', 'key' => 'account_holder_name', 'label' => 'Account holder name', 'type' => 'text'],
            ['section' => 'bank_details', 'key' => 'account_number', 'label' => 'Account number', 'type' => 'text'],
            ['section' => 'bank_details', 'key' => 'routing_number', 'label' => 'Routing number', 'type' => 'text'],
            ['section' => 'bank_details', 'key' => 'bank_account_type', 'label' => 'Account type', 'type' => 'select', 'options' => ['checking', 'savings']],
        ];

        foreach ([
            ['business_license', 'Business license'],
            ['articles_of_incorporation', 'Articles of incorporation'],
            ['national_identity_card', 'National identity card'],
            ['authorized_signature', 'Authorized signature'],
            ['address_proof', 'Address proof'],
            ['voided_check', 'Voided check'],
        ] as [$key, $label]) {
            $fields[] = ['section' => 'business_documents', 'key' => $key, 'label' => $label, 'type' => 'image'];
        }

        return $fields;
    }
}
