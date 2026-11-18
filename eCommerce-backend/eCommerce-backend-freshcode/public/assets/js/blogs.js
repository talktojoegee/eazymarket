document.addEventListener('DOMContentLoaded', () => {
    const tableId = 'blogs-table';
    const table = document.getElementById(tableId);
    const deleteUrl = document.getElementById('blogs-delete-url')?.value;
    const deleteTitle = document.getElementById('blogs-delete-title')?.value;
    const deleteText = document.getElementById('blogs-delete-text')?.value;
    const confirmText = document.getElementById('blogs-delete-confirm')?.value;
    const cancelText = document.getElementById('blogs-cancel')?.value;
    const errorText = document.getElementById('blogs-error')?.value;

    document.getElementById('blogs-refresh')?.addEventListener('click', () => {
        window.DatatableUtils?.refreshDatatable(tableId);
    });

    table?.addEventListener('click', async (event) => {
        const button = event.target.closest('.blog-delete');
        if (!button) return;

        if (typeof Swal === 'undefined') {
            Toast.fire({icon: 'error', title: errorText});
            return;
        }

        const result = await Swal.fire({
            title: deleteTitle,
            text: deleteText.replace(':title', button.dataset.title),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            reverseButtons: true,
        });
        if (!result.isConfirmed) return;

        try {
            const response = await axios.delete(deleteUrl.replace('__ID__', button.dataset.id), {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            });
            if (response.data?.success) {
                Toast.fire({icon: 'success', title: response.data.message});
                window.DatatableUtils?.refreshDatatable(tableId);
            } else {
                Toast.fire({icon: 'error', title: response.data?.message || errorText});
            }
        } catch (error) {
            Toast.fire({icon: 'error', title: error.response?.data?.message || errorText});
        }
    });
});
