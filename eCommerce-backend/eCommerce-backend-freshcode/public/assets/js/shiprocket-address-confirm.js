(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-shiprocket-confirm="1"]').forEach(function (form) {
            var confirmed = false;

            form.addEventListener('submit', function (e) {
                if (confirmed) {
                    confirmed = false;
                    return;
                }
                if (typeof Swal === 'undefined') {
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();

                Swal.fire({
                    icon: 'warning',
                    title: form.dataset.confirmTitle || 'Confirm',
                    text: form.dataset.confirmBody || '',
                    showCancelButton: true,
                    confirmButtonText: form.dataset.confirmYes || 'Yes',
                    cancelButtonText: form.dataset.confirmCancel || 'Cancel',
                }).then(function (res) {
                    if (res.isConfirmed) {
                        confirmed = true;
                        if (form.requestSubmit) {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                });
            }, true);
        });
    });
})();
