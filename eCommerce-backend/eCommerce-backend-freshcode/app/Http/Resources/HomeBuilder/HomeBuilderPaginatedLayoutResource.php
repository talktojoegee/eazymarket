<?php

namespace App\Http\Resources\HomeBuilder;

use App\Models\HomeLayout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class HomeBuilderPaginatedLayoutResource extends JsonResource
{
    public function __construct(
        HomeLayout $resource,
        private readonly Collection $manifest,
        private readonly LengthAwarePaginator $sections,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'is_active' => (bool) $this->is_active,
            'section_manifest' => HomeBuilderSectionManifestResource::collection($this->manifest)->resolve($request),
            'sections' => HomeBuilderSectionResource::collection($this->sections->getCollection())->resolve($request),
            'sections_pagination' => [
                'current_page' => $this->sections->currentPage(),
                'last_page' => $this->sections->lastPage(),
                'per_page' => $this->sections->perPage(),
                'total' => $this->sections->total(),
                'has_more' => $this->sections->hasMorePages(),
            ],
        ];
    }
}
