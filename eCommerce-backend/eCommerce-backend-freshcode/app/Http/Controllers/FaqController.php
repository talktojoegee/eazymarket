<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use App\Enums\AdminPermissionEnum;
use App\Http\Requests\Faq\StoreUpdateFaqRequest;
use App\Models\Faq;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class FaqController extends Controller
{

    use PanelAware, AuthorizesRequests, ChecksPermissions;

    protected bool $editPermission = false;
    protected bool $deletePermission = false;
    protected bool $createPermission = false;

    public function __construct()
    {
        $this->editPermission = $this->hasPermission(AdminPermissionEnum::FAQ_EDIT());
        $this->deletePermission = $this->hasPermission(AdminPermissionEnum::FAQ_DELETE());
        $this->createPermission = $this->hasPermission(AdminPermissionEnum::FAQ_CREATE());
    }

    /**
     * Display a listing of the product FAQs.
     */
    public function index(): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::FAQ_VIEW()), 403);
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'question', 'name' => 'question', 'title' => __('labels.question'), 'orderable' => false, 'searchable' => false],
            ['data' => 'answer', 'name' => 'answer', 'title' => __('labels.answer'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        $createPermission = $this->createPermission;
        $editPermission = $this->editPermission;

        return view($this->panelView('faqs.index'), compact('columns', 'createPermission', 'editPermission'));
    }

    /**
     * Store a newly created FAQ in storage.
     */
    public function store(StoreUpdateFaqRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Faq::class);
            $validated = $request->validated();
            $faq = Faq::create($validated);

            return ApiResponseType::sendJsonResponse(
                true,
                'FAQ created successfully.',
                $faq,
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                false,
                'You do not have permission to create FAQs.',
                [],
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create FAQ: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                false,
                'Failed to create FAQ.',
                []
            );
        }
    }

    /**
     * Display the specified FAQ.
     */
    public function show($id): JsonResponse
    {
        try {
            $faq = Faq::findOrFail($id);
            return ApiResponseType::sendJsonResponse(
                true,
                'FAQ fetched successfully.',
                $faq
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                false,
                'FAQ not found.'
            );
        }
    }

    /**
     * Show the form for editing the specified FAQ.
     */
    public function edit($id): JsonResponse
    {
        try {
            $faq = Faq::findOrFail($id);
            return ApiResponseType::sendJsonResponse(
                true,
                'FAQ fetched successfully.',
                $faq
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                false,
                'FAQ not found.'
            );
        }
    }

    /**
     * Update the specified product FAQ in storage.
     */
    public function update(StoreUpdateFaqRequest $request, $id): JsonResponse
    {
        try {
            $faq = Faq::findOrFail($id);
            $this->authorize('update', $faq);
            $validated = $request->validated();

            if (!$request->has('status')) {
                unset($validated['status']);
            }

            $faq->update($validated);

            return ApiResponseType::sendJsonResponse(
                true,
                'FAQ updated successfully.',
                $faq
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                false,
                'FAQ not found.'
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                false,
                'You do not have permission to update this FAQ.',
                [],
            );
        } catch (\Throwable $e) {
            Log::error('Failed to update FAQ: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                false,
                'Failed to update FAQ.',
                []
            );
        }
    }

    /**
     * Remove the specified product FAQ from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $faq = Faq::find($id);

            if (!$faq) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'FAQ not found.'
                );
            }

            $this->authorize('delete', $faq);
            $faq->delete();

            return ApiResponseType::sendJsonResponse(
                true,
                'FAQ deleted successfully.'
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                false,
                'You do not have permission to delete this FAQ.',
                [],
            );
        } catch (\Throwable $e) {
            Log::error('Failed to delete FAQ: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                false,
                'Failed to delete FAQ.',
                []
            );
        }
    }

    public function getFaqs(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::FAQ_VIEW()), 403);
        try {
            $draw = $request->get('draw');
            $start = $request->get('start');
            $length = $request->get('length');
            $searchValue = $request->get('search')['value'] ?? '';
            $status = $request->get('status');

            $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
            $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

            $columns = ['id', 'question', 'answer', 'status', 'created_at'];
            $orderColumn = $columns[$orderColumnIndex] ?? 'id';

            $query = Faq::query();

            $totalRecords = $query->count();
            $filteredRecords = $totalRecords;
            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('question', 'like', "%{$searchValue}%")
                        ->orWhere('answer', 'like', "%{$searchValue}%");
                });
                $filteredRecords = $query->count();
            }
            if (!empty($status)) {
                $query->where(function ($q) use ($status) {
                    $q->where('status', $status);
                });
                $filteredRecords = $query->count();
            }


            $data = $query
                ->orderBy($orderColumn, $orderDirection)
                ->skip($start)
                ->take($length)
                ->get()
                ->map(function ($faq) {
                    return [
                        'id' => $faq->id,
                        'question' => e(Str::limit($faq->question, 30)),
                        'answer' => e(Str::limit($faq->answer, 50)),
                        'status' => view('partials.status', ['status' => $faq->status ?? ""])->render(),
                        'created_at' => $faq->created_at->format('Y-m-d'),
                        'action' => view('partials.actions', [
                            'modelName' => 'faq',
                            'id' => $faq->id,
                            'title' => $faq->question,
                            'mode' => 'model_view',
                            'editPermission' => $this->editPermission,
                            'deletePermission' => $this->deletePermission
                        ])->render(),
                    ];
                })
                ->toArray();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (\Exception $e) {
            Log::error('Failed to fetch FAQs: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.something_went_wrong'), data: []);
        }
    }
}
