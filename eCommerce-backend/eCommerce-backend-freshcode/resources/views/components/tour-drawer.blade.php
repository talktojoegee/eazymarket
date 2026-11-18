<div class="offcanvas offcanvas-end m-0 end-0 rounded-0" tabindex="-1" id="offcanvasTourDrawer"
     aria-labelledby="offcanvasTourDrawerLabel" data-tour-drawer data-panel="{{ $panel }}">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="offcanvasTourDrawerLabel">
            <i class="ti ti-info-circle text-primary fs-2"></i>
            User Guide
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"
                aria-label="Close user guide"></button>
    </div>
    <div class="offcanvas-body p-3">
        <p class="text-secondary small mb-3">Interactive step-by-step guides for the available panel features.</p>
        @if($groups !== [])
            <div class="input-icon mb-3">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input type="search" class="form-control" placeholder="Search guides" autocomplete="off"
                       aria-label="Search guides" data-tour-search>
            </div>
            <div class="accordion accordion-flush" id="tourGuideAccordion">
                @foreach($groups as $groupIndex => $group)
                    @php($collapseId = 'tourGuideGroup'.($groupIndex + 1))
                    <div class="accordion-item border rounded-3 mb-3 overflow-hidden" data-tour-group
                         data-tour-search-text="{{ strtolower($group['title']) }}">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed py-3 shadow-none" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                                    aria-expanded="false" aria-controls="{{ $collapseId }}">
                                <span class="badge bg-primary-lt me-2">{{ $groupIndex + 1 }}</span>
                                <span>{{ $group['title'] }}</span>
                                <span class="badge bg-secondary-lt ms-2">{{ count($group['guides']) }}</span>
                            </button>
                        </h3>
                        <div id="{{ $collapseId }}" class="accordion-collapse collapse">
                            <div class="accordion-body p-2">
                                @foreach($group['guides'] as $guide)
                                    <article class="card shadow-none mb-2 tour-guide-card" data-tour-guide
                                             data-tour-search-text="{{ strtolower($guide['title'].' '.$guide['description']) }}">
                                        <div class="card-body p-3">
                                            <div class="d-flex gap-3 align-items-start">
                                                <span class="avatar avatar-sm bg-primary-lt text-primary flex-shrink-0">
                                                    <i class="{{ $guide['icon'] }}"></i>
                                                </span>
                                                <div class="flex-fill min-w-0">
                                                    <h4 class="mb-1 fs-4">{{ $guide['title'] }}</h4>
                                                    <p class="text-secondary small mb-2">{{ $guide['description'] }}</p>
                                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                                        <span class="text-secondary small">
                                                            {{ $guide['segment_count'] }} {{ Illuminate\Support\Str::plural('page', $guide['segment_count']) }}
                                                        </span>
                                                        <button type="button" class="btn btn-primary btn-sm"
                                                                data-tour-start="{{ $guide['action'] }}">
                                                            <i class="ti ti-player-play me-1"></i>
                                                            Start guide
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="text-center text-secondary py-5 d-none" data-tour-no-results>
                <i class="ti ti-search-off fs-1 d-block mb-2"></i>
                No guides match your search.
            </div>
        @else
            <div class="text-center text-secondary py-5 flex-fill">
                <i class="ti ti-compass-off fs-1 d-block mb-2"></i>
                No guides are available for your current permissions.
            </div>
        @endif
        <div class="border-top pt-3 mt-3">
            <a href="{{ $knowledgeBaseUrl }}" target="_blank" rel="noopener"
               class="btn btn-outline-primary w-100">
                <i class="ti ti-book-2 me-2"></i>
                Full Knowledge Base
                <i class="ti ti-external-link ms-2"></i>
            </a>
        </div>
    </div>
</div>
<script>
    window.HypercommerceTourCatalog = {{ Illuminate\Support\Js::from($tours) }};
</script>
