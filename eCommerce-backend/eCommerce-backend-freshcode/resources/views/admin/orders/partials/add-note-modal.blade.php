
<div class="modal modal-blur fade" id="adminAddNoteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <form id="adminAddNoteForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-purple"></div>
                <div class="modal-header py-2">
                    <h4 class="modal-title">
                        <i class="ti ti-note me-1"></i>{{ __('labels.add_note') }}
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-secondary small mb-3">
                        {{ __('labels.add_note_help_text') }}
                    </p>
                    <div class="mb-1">
                        <label class="form-label required" for="adminNoteBody">{{ __('labels.note') }}</label>
                        <textarea class="form-control"
                                  name="note"
                                  id="adminNoteBody"
                                  rows="3"
                                  maxlength="2000"
                                  required
                                  placeholder="{{ __('labels.add_note_placeholder') }}"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" id="adminAddNoteSubmit">
                        <i class="ti ti-plus me-1"></i>{{ __('labels.add_note') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
