<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Enums\AdminPermissionEnum;
use App\Http\Requests\TaxClass\TaxClassRequest;
use App\Models\Market;
use App\Models\TaxClass;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaxClassController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $editPermission = false;
    protected bool $deletePermission = false;
    protected bool $createPermission = false;

    public function __construct()
    {
        if ($this->getPanel() == 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::TAX_CLASS_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::TAX_CLASS_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::TAX_CLASS_CREATE());
        }
    }

    public function getTaxClasses(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaxClass::class);
        $draw          = $request->get('draw');
        $start         = (int) $request->get('start', 0);
        $length        = (int) $request->get('length', 10);
        $searchValue   = $request->get('search')['value'] ?? '';
        $marketFilter  = $this->getPanel() === 'admin'
            ? app(\App\Services\MarketService::class)->adminSelectedId()
            : $request->input('market_id');

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection   = $request->get('order')[0]['dir'] ?? 'asc';

        $columns     = ['id', 'title', 'market', 'rates', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';
        if (in_array($orderColumn, ['rates', 'market'], true)) {
            $orderColumn = 'id';
        }

        $query = TaxClass::with(['taxRates', 'market:id,code,name']);

        if ($marketFilter) {
            $query->where('market_id', (int) $marketFilter);
        }

        $totalRecords    = TaxClass::query()
            ->when($marketFilter, fn ($q) => $q->where('market_id', (int) $marketFilter))
            ->count();
        $filteredRecords = $totalRecords;

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('title', 'like', "%$searchValue%")
                    ->orWhereHas('taxRates', function ($qr) use ($searchValue) {
                        $qr->where('title', 'like', "%$searchValue%");
                    });
            });
            $filteredRecords = (clone $query)->count();
        }

        $editPermission   = $this->editPermission;
        $deletePermission = $this->deletePermission;

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($taxClass) use ($editPermission, $deletePermission) {
                $marketCode = $taxClass->market?->code ?? '—';
                $marketName = $taxClass->market?->name ?? '';

                return [
                    'id'         => $taxClass->id,
                    'title'      => e($taxClass->title),
                    'market'     => '<span class="badge bg-blue-lt" title="' . e($marketName) . '">'
                                    . e($marketCode) . '</span>',
                    'rates'      => $taxClass->taxRates
                        ->map(fn ($rate) => e($rate->title) . ' (' . e($rate->rate) . '%)')
                        ->implode(', '),
                    'created_at' => $taxClass->created_at?->format('Y-m-d'),
                    'action'     => view('partials.actions', [
                        'modelName'        => 'tax-class',
                        'id'               => $taxClass->id,
                        'title'            => $taxClass->title,
                        'mode'             => 'model_view',
                        'editPermission'   => $editPermission,
                        'deletePermission' => $deletePermission,
                    ])->render(),
                ];
            })
            ->toArray();

        return response()->json([
            'draw'            => intval($draw),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    public function store(TaxClassRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TaxClass::class);

            $data = $request->validated();

            $taxClass = TaxClass::create([
                'title'     => $data['title'],
                'market_id' => $data['market_id'],
            ]);
            $taxClass->taxRates()->sync($data['tax_rate_ids']);

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.tax_class_created_successfully',
                $taxClass->load('taxRates', 'market'),
                201
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', []);
        }
    }

    public function show($id): JsonResponse
    {
        $taxClass = TaxClass::with(['taxRates', 'market:id,code,name'])->find($id);

        if (!$taxClass) {
            return ApiResponseType::sendJsonResponse(false, 'labels.tax_class_not_found', [], 404);
        }

        try {
            $this->authorize('view', $taxClass);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'labels.tax_class_fetched_successfully',
            $taxClass
        );
    }

    public function update(TaxClassRequest $request, $id): JsonResponse
    {
        try {
            $taxClass = TaxClass::find($id);

            if (!$taxClass) {
                return ApiResponseType::sendJsonResponse(false, 'labels.tax_class_not_found', [], 404);
            }

            $this->authorize('update', $taxClass);

            $data = $request->validated();

            if ((int) $data['market_id'] !== (int) $taxClass->market_id
                && DB::table('product_taxes')->where('tax_class_id', $taxClass->id)->exists()) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.tax_class_market_locked'),
                    [],
                    422
                );
            }

            $taxClass->title     = $data['title'];
            $taxClass->market_id = $data['market_id'];
            $taxClass->save();

            $taxClass->taxRates()->sync($data['tax_rate_ids']);

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.tax_class_updated_successfully',
                $taxClass->load('taxRates', 'market'),
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', []);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $taxClass = TaxClass::find($id);
            if (!$taxClass) {
                return ApiResponseType::sendJsonResponse(false, 'labels.tax_class_not_found', [], 404);
            }

            $this->authorize('delete', $taxClass);

            $productCount = DB::table('product_taxes')->where('tax_class_id', $taxClass->id)->count();

            if ($productCount > 0) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.tax_class_in_use', ['count' => $productCount]),
                    [],
                    422
                );
            }

            $taxClass->taxRates()->detach();
            $taxClass->delete();

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.tax_class_deleted_successfully',
                []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', []);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', TaxClass::class);
        } catch (AuthorizationException $e) {
            return response()->json([], 403);
        }

        $query = $request->input('q');
        $exceptId = $request->input('exceptId');
        $findId = $request->input('find_id');

        if ($findId) {
            $taxClasses = TaxClass::where('id', $findId)
                ->select('id', 'title')
                ->get();
        } else {
            $taxClasses = TaxClass::where('title', 'LIKE', '%' . $query . '%')
                ->select('id', 'title')
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->take(10)
                ->get();
        }
        $results = $taxClasses->map(function ($taxClass) {
            return [
                'id' => $taxClass->id,
                'value' => $taxClass->id,
                'text' => $taxClass->title,
            ];
        });
        return response()->json($results);
    }
}
