<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DynamicSellerField;
use App\Models\Country;
use App\Models\Seller;
use App\Enums\AdminPermissionEnum;
use App\Traits\ChecksPermissions;
use App\Services\DynamicSellerFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class DynamicSellerFieldController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request, DynamicSellerFieldService $dynamicFields): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403);
        $section = $request->query('section', 'bank_details');
        abort_unless(in_array($section, ['bank_details', 'business_documents'], true), 404);
        $selectedCountries = collect((array) $request->query('country_codes', []))
            ->map(fn ($iso2) => strtoupper(trim((string) $iso2)))
            ->filter()
            ->unique()
            ->values();
        $editingField = null;
        if ($request->filled('edit')) {
            $editingField = DynamicSellerField::with('countries')->where('section', $section)->find((int) $request->query('edit'));
            abort_unless($editingField, 404);
            if ($selectedCountries->isEmpty()) {
                $selectedCountries = $editingField->countries->pluck('iso2');
            }
        }
        $canManageFields = $this->hasPermission(AdminPermissionEnum::SELLER_EDIT());
        $fieldOptionsText = implode(PHP_EOL, $editingField?->options ?? []);
        $fieldCountryCodes = $editingField?->countries->pluck('iso2')->all() ?? $selectedCountries->all();
        $columns = [
            ['data' => 'label', 'name' => 'label', 'label' => __('Field')],
            ['data' => 'countries', 'name' => 'countries', 'label' => __('Countries'), 'orderable' => false, 'searchable' => false],
            ['data' => 'type', 'name' => 'type', 'label' => __('Type')],
            ['data' => 'placeholder', 'name' => 'placeholder', 'label' => __('Placeholder')],
            ['data' => 'default_value', 'name' => 'default_value', 'label' => __('Default value')],
            ['data' => 'required', 'name' => 'required', 'label' => __('Required')],
            ['data' => 'status', 'name' => 'status', 'label' => __('Status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'action', 'name' => 'action', 'label' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.sellers.dynamic-fields', compact('canManageFields', 'section', 'editingField', 'fieldOptionsText', 'selectedCountries', 'fieldCountryCodes', 'columns'));
    }

    public function datatable(Request $request, DynamicSellerFieldService $dynamicFields): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403);
        $draw = (int) $request->input('draw', 0);
        $start = max((int) $request->input('start', 0), 0);
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $section = (string) $request->query('section', 'bank_details');
        abort_unless(in_array($section, ['bank_details', 'business_documents'], true), 404);
        $countryCodes = collect((array) $request->query('country_codes', []))
            ->map(fn ($iso2) => strtoupper(trim((string) $iso2)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($countryCodes === []) {
            return response()->json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }

        $query = $dynamicFields->queryForCountries($countryCodes)->where('section', $section);
        $total = (clone $query)->count();
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('label', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%')
                    ->orWhere('placeholder', 'like', '%'.$search.'%');
            });
        }
        $filtered = (clone $query)->count();
        $orderIndex = (int) data_get($request->input('order'), '0.column', 0);
        $orderName = data_get($request->input('columns'), $orderIndex.'.name', 'label');
        $orderColumn = in_array($orderName, ['label', 'type', 'placeholder', 'default_value', 'required'], true) ? $orderName : 'label';
        $direction = data_get($request->input('order'), '0.dir') === 'desc' ? 'desc' : 'asc';
        $canManageFields = $this->hasPermission(AdminPermissionEnum::SELLER_EDIT());
        $rows = $query->with('countries:id,name,iso2')
            ->orderBy($orderColumn, $direction)
            ->orderBy('id')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (DynamicSellerField $field) use ($canManageFields, $section, $countryCodes) {
                $actions = '';
                $selectedFieldCountries = $field->countries->filter(fn ($country) => in_array(strtoupper($country->iso2), $countryCodes, true));
                $archivedCount = $selectedFieldCountries->filter(fn ($country) => filled($country->pivot->archived_at))->count();
                $activeCount = $selectedFieldCountries->count() - $archivedCount;
                $status = $archivedCount === 0
                    ? __('Active')
                    : ($activeCount === 0 ? __('Archived') : __('Partially archived'));

                if ($canManageFields) {
                    $editUrl = route('admin.sellers.dynamic-fields.index', [
                        'section' => $section,
                        'country_codes' => $field->countries->pluck('iso2')->all(),
                        'edit' => $field->id,
                    ]);
                    $actions .= '<a class="btn btn-outline-primary btn-sm" href="'.$editUrl.'">'.e(__('Edit')).'</a> ';
                    $actionUrl = route('admin.sellers.dynamic-fields.destroy', ['field' => $field, 'country_codes' => $countryCodes]);
                    if ($archivedCount > 0) {
                        $actions .= '<button type="button" class="btn btn-outline-success btn-sm" data-restore-url="'.$actionUrl.'" data-field-label="'.e($field->label).'">'.e(__('Restore')).'</button> ';
                    }
                    $actions .= '<button type="button" class="btn btn-outline-danger btn-sm" data-remove-field-url="'.$actionUrl.'" data-field-label="'.e($field->label).'">'.e(__('Remove')).'</button>';
                }

                return [
                    'label' => e($field->label),
                    'countries' => e($field->countries->pluck('name')->join(', ')),
                    'type' => e(ucfirst($field->type)),
                    'placeholder' => e($field->placeholder ?: '—'),
                    'default_value' => e($field->default_value ?: '—'),
                    'required' => $field->required ? e(__('Yes')) : e(__('No')),
                    'status' => e($status),
                    'action' => $actions,
                ];
            });

        return response()->json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows]);
    }

    public function forCountry(string $countryIso2, DynamicSellerFieldService $dynamicFields): JsonResponse
    {
        abort_unless(
            $this->hasPermission(AdminPermissionEnum::SELLER_VIEW())
            || $this->hasPermission(AdminPermissionEnum::SELLER_CREATE())
            || $this->hasPermission(AdminPermissionEnum::SELLER_EDIT()),
            403
        );
        $fields = $dynamicFields->forCountry($countryIso2)->groupBy('section');
        $html = [];

        foreach (['bank_details', 'business_documents'] as $section) {
            $html[$section] = view('admin.sellers.partials.dynamic-fields-group', [
                'fields' => $fields->get($section, collect()),
                'section' => $section,
                'countrySelected' => true,
            ])->render();
        }

        return response()->json(['sections' => $html]);
    }

    public function store(Request $request, DynamicSellerFieldService $dynamicFields): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_EDIT()), 403);
        $validated = $this->validatedFieldData($request);
        $dynamicFields->forCountries($validated['country_codes']);

        $key = str($validated['label'])->snake()->limit(60, '')->value();
        $key = $key ?: 'field';
        $baseKey = $key;
        $suffix = 2;
        while (DynamicSellerField::where('key', $key)->exists()) {
            $key = $baseKey.'_'.$suffix++;
        }

        $options = $this->validatedOptions($validated);
        if ($options instanceof JsonResponse) {
            return $options;
        }

        DynamicSellerField::create([
            'section' => $validated['section'],
            'key' => $key,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'placeholder' => $validated['placeholder'] ?? null,
            'default_value' => $validated['type'] === 'image' ? null : ($validated['default_value'] ?? null),
            'options' => $options,
            'required' => $request->boolean('required'),
            'sort_order' => DynamicSellerField::where('section', $validated['section'])->max('sort_order') + 1,
        ])->countries()->sync(Country::whereIn('iso2', $validated['country_codes'])->pluck('id'));

        return response()->json([
            'success' => true,
            'message' => __('Dynamic field added successfully.'),
            'data' => ['redirect_url' => route('admin.sellers.dynamic-fields.index', ['section' => $validated['section'], 'country_codes' => $validated['country_codes']])],
        ]);
    }

    public function update(Request $request, DynamicSellerField $field, DynamicSellerFieldService $dynamicFields): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_EDIT()), 403);
        $validated = $this->validatedFieldData($request, $field->section);
        $validated['type'] = $field->type;
        $dynamicFields->forCountries($validated['country_codes']);
        $options = $this->validatedOptions($validated);
        if ($options instanceof JsonResponse) {
            return $options;
        }

        $field->update([
            'label' => $validated['label'],
            'type' => $validated['type'],
            'placeholder' => $validated['placeholder'] ?? null,
            'default_value' => $validated['type'] === 'image' ? null : ($validated['default_value'] ?? null),
            'options' => $options,
            'required' => $request->boolean('required'),
        ]);
        $field->countries()->sync(Country::whereIn('iso2', $validated['country_codes'])->pluck('id'));

        return response()->json([
            'success' => true,
            'message' => __('Dynamic field updated successfully.'),
            'data' => ['redirect_url' => route('admin.sellers.dynamic-fields.index', ['section' => $field->section, 'country_codes' => $validated['country_codes']])],
        ]);
    }

    public function destroy(Request $request, DynamicSellerField $field): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_EDIT()), 403);
        $validated = $request->validate([
            'operation' => ['required', Rule::in(['archive', 'restore', 'permanent'])],
            'country_codes' => ['required', 'array', 'min:1'],
            'country_codes.*' => ['required', 'string', 'distinct', 'size:2', Rule::exists('countries', 'iso2')],
        ]);
        $countryIds = Country::query()
            ->whereIn('iso2', collect($validated['country_codes'])->map(fn ($iso2) => strtoupper($iso2))->all())
            ->pluck('id');

        $countryFieldLinks = DB::table('dynamic_seller_field_country')
            ->where('dynamic_seller_field_id', $field->id)
            ->whereIn('country_id', $countryIds);

        if ($validated['operation'] === 'archive') {
            $countryFieldLinks->update(['archived_at' => now()]);
            $message = __('Field archived for selected countries. Seller values were retained.');
        } elseif ($validated['operation'] === 'restore') {
            $countryFieldLinks->update(['archived_at' => null]);
            $message = __('Field restored for selected countries.');
        } else {
            $countryNames = Country::query()->whereIn('id', $countryIds)->pluck('name');
            $legacyKey = '__legacy_'.preg_replace('/^(?:m\d+|[a-z]{2})_/', '', $field->key);
            $keysToRemove = array_values(array_unique([$field->key, $legacyKey]));

            DB::transaction(function () use ($field, $countryIds, $countryNames, $keysToRemove) {
                Seller::withTrashed()
                    ->whereIn('country', $countryNames)
                    ->orderBy('id')
                    ->chunkById(100, function ($sellers) use ($keysToRemove, $field) {
                        foreach ($sellers as $seller) {
                            $values = $seller->dynamic_fields ?? [];
                            foreach ($keysToRemove as $key) {
                                unset($values[$key]);
                            }
                            $seller->dynamic_fields = $values;
                            $seller->save();

                            if ($field->type === 'image') {
                                $seller->clearMediaCollection('dynamic_field_'.$field->key);
                                $legacyCollection = preg_replace('/^(?:m\d+|[a-z]{2})_/', '', $field->key);
                                if ($legacyCollection !== $field->key) {
                                    $seller->clearMediaCollection($legacyCollection);
                                }
                            }
                        }
                    });

                DB::table('dynamic_seller_field_country')
                    ->where('dynamic_seller_field_id', $field->id)
                    ->whereIn('country_id', $countryIds)
                    ->delete();

                $initializedColumn = $field->section === 'bank_details'
                    ? 'seller_bank_fields_initialized'
                    : 'seller_business_documents_initialized';
                Country::query()->whereIn('id', $countryIds)->update([$initializedColumn => true]);

                if (!$field->countries()->exists()) {
                    $field->delete();
                }
            });
            $message = __('Field permanently removed for selected countries and seller values deleted.');
        }

        return response()->json(['message' => $message]);
    }

    private function validatedFieldData(Request $request, ?string $section = null): array
    {
        $validated = $request->validate([
            'section' => ['required', Rule::in($section ? [$section] : ['bank_details', 'business_documents'])],
            'country_codes' => ['required', 'array', 'min:1'],
            'country_codes.*' => ['required', 'string', 'distinct', 'size:2', Rule::exists('countries', 'iso2')],
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['select', 'number', 'text', 'image'])],
            'placeholder' => ['nullable', 'string', 'max:160'],
            'default_value' => ['nullable', 'string', 'max:1000'],
            'options' => ['nullable', 'string', 'max:2000'],
            'required' => ['nullable', 'boolean'],
        ]);

        $validated['country_codes'] = collect($validated['country_codes'])
            ->map(fn ($iso2) => strtoupper($iso2))
            ->unique()
            ->values()
            ->all();

        return $validated;
    }

    private function validatedOptions(array $validated): array|JsonResponse
    {
        $options = collect(preg_split('/\r\n|\r|\n/', $validated['options'] ?? ''))
            ->map(fn ($option) => trim($option))
            ->filter()
            ->values()
            ->all();

        if ($validated['type'] === 'select' && $options === []) {
            return response()->json(['message' => __('Add at least one option for a dropdown field.')], 422);
        }

        if ($validated['type'] === 'select' && filled($validated['default_value'] ?? null) && !in_array($validated['default_value'], $options, true)) {
            return response()->json(['message' => __('The default value must match one of the dropdown options.')], 422);
        }

        if ($validated['type'] === 'number' && filled($validated['default_value'] ?? null) && !is_numeric($validated['default_value'])) {
            return response()->json(['message' => __('The default value must be numeric for a number field.')], 422);
        }

        return $validated['type'] === 'select' ? $options : [];
    }
}
