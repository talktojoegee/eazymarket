(function () {
    'use strict';

    const labels = window.landingPageBuilderLabels || {};

    document.addEventListener('click', async function (event) {
        const shareButton = event.target.closest('[data-share-landing-page]');
        if (shareButton) {
            const url = shareButton.dataset.shareLandingPage;
            try {
                if (navigator.share) {
                    await navigator.share({title: shareButton.dataset.shareTitle, url: url});
                    window.Toast?.fire({icon: 'success', title: labels.shareOpened || 'Share dialog opened.'});
                } else {
                    await navigator.clipboard.writeText(url);
                    window.Toast?.fire({icon: 'success', title: labels.linkCopied || 'Link copied.'});
                }
            } catch (error) {
                if (error.name === 'AbortError') return;
                window.Toast?.fire({icon: 'error', title: labels.copyFailed || 'Could not copy the link.'});
            }
            return;
        }

        const deleteButton = event.target.closest('.delete-landing-page');
        if (deleteButton) {
            if (!window.Swal?.fire) {
                window.Toast?.fire({icon: 'error', title: labels.confirmationUnavailable || 'Confirmation is unavailable.'});
                return;
            }

            const result = await window.Swal.fire({
                title: labels.deleteConfirmation || 'Delete this landing page?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: labels.delete || 'Delete',
                cancelButtonText: labels.cancel || 'Cancel',
                confirmButtonColor: '#d63939'
            });

            if (!result.isConfirmed) return;

            deleteButton.disabled = true;
            try {
                const response = await fetch(deleteButton.dataset.deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Could not delete the landing page.');

                window.Toast?.fire({icon: 'success', title: data.message});
                window.jQuery?.('#landing-pages-table').DataTable().ajax.reload(null, false);
            } catch (error) {
                window.Toast?.fire({icon: 'error', title: error.message || 'Could not delete the landing page.'});
                deleteButton.disabled = false;
            }
        }
    });

    document.getElementById('refresh-landing-pages')?.addEventListener('click', function () {
        const table = window.jQuery?.('#landing-pages-table');
        if (table?.length && window.jQuery.fn.DataTable.isDataTable(table)) {
            table.DataTable().ajax.reload(null, false);
        }
    });
})();
