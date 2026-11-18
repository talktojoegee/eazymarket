<div class="card-footer bg-body-tertiary position-sticky bottom-0 z-1">
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary">{{ __('labels.cancel') }}</a>
        @can('updateSetting', [\App\Models\Setting::class, 'authentication'])
            <button type="submit" class="btn btn-primary">{{ __('labels.save_changes') }}</button>
        @endcan
    </div>
</div>
