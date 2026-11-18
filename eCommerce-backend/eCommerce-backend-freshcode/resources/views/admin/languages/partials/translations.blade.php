<div class="card">
    <div class="card-header d-block">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <h3 class="card-title">{{ __('labels.translations') }}</h3>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge bg-success-lt">
                    {{ __('labels.translated') }}: <span data-translated-count>{{ $translatedCount }}</span>
                </span>
                <span class="badge bg-warning-lt">
                    {{ __('labels.missing') }}: <span data-missing-count>{{ $missingCount }}</span>
                </span>
                <span class="badge bg-secondary-lt">
                    {{ __('labels.total') }}: <span data-source-count>{{ $sourceCount }}</span>
                </span>
            </div>
        </div>
    </div>
    <form
        action="{{ $translationsUpdateRoute ?? route('admin.languages.translations.update', $language) }}"
        method="POST"
        class="form-submit"
        data-language-translation-form
    >
        @csrf
        <div class="card-table">
            <div class="card-actions mt-3 ms-3">
                <div class="row g-2">
                    <div class="col-auto">
                        <label class="visually-hidden" for="translation-key-search">{{ __('labels.search_key') }}</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <i class="ti ti-search"></i>
                            </span>
                            <input
                                id="translation-key-search"
                                type="search"
                                class="form-control"
                                placeholder="{{ __('labels.search_key') }}"
                                autocomplete="off"
                            >
                        </div>
                    </div>
                    <div class="col-auto">
                        <label class="visually-hidden" for="translation-status">{{ __('labels.status_filter') }}</label>
                        <select id="translation-status" class="form-select">
                            <option value="">{{ __('labels.all_statuses') }}</option>
                            <option value="missing">{{ __('labels.missing') }}</option>
                            <option value="translated">{{ __('labels.translated') }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row w-full p-3">
                <x-datatable
                    id="language-translations-table"
                    :columns="$translationColumns"
                    route="{{ $translationsDatatableRoute ?? route('admin.languages.translations.datatable', $language) }}"
                    class="table table-striped table-bordered table-vcenter w-100"
                    :options="['order' => [[0, 'asc']], 'pageLength' => 25, 'lengthMenu' => [10, 25, 50, 100]]"
                />
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary" type="submit">
                <i class="ti ti-device-floppy"></i>
                {{ __('labels.save_current_translation_page') }}
            </button>
        </div>
    </form>
</div>
