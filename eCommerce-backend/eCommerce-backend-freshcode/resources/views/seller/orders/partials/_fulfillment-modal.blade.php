<div class="modal modal-blur fade" id="fulfillShipmentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form id="fulfillShipmentForm" data-fulfill-url=""
                  data-error-no-items="{{ __('labels.select_at_least_one_item') ?? 'Select a quantity for at least one item to fulfill.' }}">
                <div class="modal-header py-2">
                    <h4 class="modal-title"><i class="ti ti-package-export me-1"></i>{{ __('labels.mark_as_fulfilled') ?? 'Mark as fulfilled' }}</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div id="fulfillItemsSection" class="mb-3 d-none">
                        <label class="form-label">{{ __('labels.items_to_ship') ?? 'Items to fulfill' }}</label>
                        <div id="fulfillItemsList" class="vstack gap-2"></div>
                        <div class="text-secondary small mt-1">
                            {{ __('labels.partial_fulfil_hint') ?? 'Fulfill some now and the rest later — adjust the quantities below.' }}
                        </div>
                        <hr>
                    </div>
                    <div id="fulfillShiprocketNote" class="alert alert-info d-flex d-none" role="alert">
                        <i class="ti ti-info-circle me-2 mt-1"></i>
                        <div id="fulfillShiprocketNoteText"></div>
                    </div>

                    <div id="fulfillManualBlock">
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.carrier') ?? 'Carrier' }}</label>
                            <input type="text" class="form-control" name="carrier_name" maxlength="80"
                                   placeholder="{{ __('labels.carrier_name_placeholder') ?? 'e.g. India Post, DTDC' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.tracking_number') ?? 'Tracking number' }}</label>
                            <input type="text" class="form-control" name="tracking_number" maxlength="80">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">{{ __('labels.tracking_url') ?? 'Tracking URL' }}</label>
                            <input type="url" class="form-control" name="tracking_url" maxlength="512">
                        </div>
                        <div class="text-secondary small mt-2">
                            {{ __('labels.fulfill_tracking_optional_hint') ?? 'Tracking is optional — you can add it now or after dispatch.' }}
                        </div>
                    </div>

                    <div id="fulfillShiprocketBlock" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.package') ?? 'Package' }}</label>
                            <select class="form-select" id="fulfillPackage"></select>
                            <div class="text-secondary small mt-1">{{ __('labels.fulfill_package_hint') ?? 'Prefills the dimensions below. Edit any field to override.' }}</div>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label">{{ __('labels.length_cm') ?? 'Length (cm)' }}</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="length_cm">
                            </div>
                            <div class="col-4">
                                <label class="form-label">{{ __('labels.breadth_cm') ?? 'Breadth (cm)' }}</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="breadth_cm">
                            </div>
                            <div class="col-4">
                                <label class="form-label">{{ __('labels.height_cm') ?? 'Height (cm)' }}</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="height_cm">
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label class="form-label">{{ __('labels.weight_kg') ?? 'Weight (kg)' }}</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="weight_kg">
                            </div>
                        </div>
                        <div id="fulfillWeightBreakdown" class="text-secondary small mt-2">
                            {{ __('labels.items_weight') ?? 'Items' }}: <span id="fulfillItemsWeightVal">–</span> kg
                            + {{ __('labels.package_weight') ?? 'Package' }}: <span id="fulfillPkgWeightVal">–</span> kg
                        </div>
                        <div id="fulfillBilledLine" class="text-secondary small mt-2"
                             data-divisor="{{ \App\Models\StorePackage::VOLUMETRIC_DIVISOR }}">
                            {{ __('labels.dead_weight') ?? 'Dead weight' }}: <span id="fulfillDeadWeight">–</span> kg
                            · {{ __('labels.volumetric_weight') ?? 'Volumetric' }}: <span id="fulfillVolWeight">–</span> kg
                            · <strong>{{ __('labels.billed_weight') ?? 'Billed' }}: <span id="fulfillBilledWeight">–</span> kg</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-link link-secondary" data-bs-dismiss="modal">
                        {{ __('labels.cancel') ?? 'Cancel' }}
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="ti ti-package-export me-1"></i>{{ __('labels.fulfill_items') ?? 'Fulfill items' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal modal-blur fade" id="editFulfillmentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="editFulfillmentForm" data-edit-url="">
                <div class="modal-header py-2">
                    <h4 class="modal-title"><i class="ti ti-edit me-1"></i>{{ __('labels.edit_fulfillment') ?? 'Edit fulfillment' }}</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.carrier') ?? 'Carrier' }}</label>
                        <input type="text" class="form-control" name="carrier_name" maxlength="80"
                               placeholder="{{ __('labels.carrier_name_placeholder') ?? 'e.g. India Post, DTDC' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.tracking_number') ?? 'Tracking number' }}</label>
                        <input type="text" class="form-control" name="tracking_number" maxlength="80">
                    </div>
                    <div class="mb-1">
                        <label class="form-label">{{ __('labels.tracking_url') ?? 'Tracking URL' }}</label>
                        <input type="url" class="form-control" name="tracking_url" maxlength="512">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-link link-secondary" data-bs-dismiss="modal">
                        {{ __('labels.cancel') ?? 'Cancel' }}
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>{{ __('labels.save_changes') ?? 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-confirm-modal
    id="cancelFulfillmentModal"
    status="danger"
    icon="ti ti-x"
    :title="__('labels.cancel_fulfillment') ?? 'Cancel fulfillment'"
    :message="__('labels.cancel_fulfillment_confirm') ?? 'Cancel this fulfillment? The items will become available to fulfill again.'"
    :confirmText="__('labels.cancel_fulfillment') ?? 'Cancel fulfillment'"
    :cancelText="__('labels.no_keep_it') ?? 'No, keep it'"
/>
