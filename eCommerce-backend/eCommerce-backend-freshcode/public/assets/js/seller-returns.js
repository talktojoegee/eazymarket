/* global $, hyperDataTable */
$(document).ready(function () {
    const table = $('#returns-table').DataTable();
    let currentOrderId = null;

    // Scope the table to a return status when linked from the dashboard (?return_status=)
    const returnStatusParam = new URLSearchParams(window.location.search).get('return_status');
    if (returnStatusParam) {
        $('#returns-table').on('preXhr.dt', function (e, settings, data) {
            data.return_status = returnStatusParam;
        });
        table.ajax.reload(null, false);
    }

    // Capture order ID when accept/reject/preparing buttons are clicked
    $('#acceptModel, #rejectModel').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        currentOrderId = button.data('id');
    }).on('hidden.bs.modal', function () {
        $('#seller_comment, #seller_comment_reject').val('');
    });


    // Handle order decision actions
    $('#confirmAccept, #confirmReject').on('click', function () {
        if (currentOrderId) {
            const $button = $(this);
            if ($button.prop('disabled')) {
                return;
            }
            $button.prop('disabled', true);

            const decisionOrderId = currentOrderId;
            currentOrderId = null;

            const action = this.id === 'confirmAccept' ? 'approve' : 'reject';
            axios.post('/seller/order-returns/' + decisionOrderId + '/decision', {
                action: action,
                seller_comment: action === 'approve' ? $('#seller_comment').val() : $('#seller_comment_reject').val()
            })
                .then(function (response) {
                    // Handle success
                    table.ajax.reload();
                    let data = response.data;
                    if (data.success === false) {
                        return Toast.fire({
                            icon: "error",
                            title: data.message
                        });
                    }
                    return Toast.fire({
                        icon: "success",
                        title: data.message
                    });
                })
                .catch(function (error) {
                    // Handle error
                    console.error('Error processing order decision:', error);
                    Toast.fire({
                        icon: 'error',
                        title: error?.response?.data?.message
                            || error?.response?.data?.errors?.action?.[0]
                            || 'Something went wrong'
                    });
                })
                .finally(function () {
                    $button.prop('disabled', false);
                });
        }
    });
});
