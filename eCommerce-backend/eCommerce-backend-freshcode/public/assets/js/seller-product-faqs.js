document.addEventListener('DOMContentLoaded', function () {
    const config = window.sellerProductFaqs || {};
    const rowsContainer = document.getElementById('product-faq-rows');
    const rowTemplate = document.getElementById('product-faq-row-template');
    if (!rowsContainer || !rowTemplate) return;

    const addButton = document.getElementById('add-product-faq-row');
    const countBadge = document.getElementById('product-faq-row-count');

    const reindexRows = function () {
        const rows = Array.from(rowsContainer.querySelectorAll('[data-faq-row]'));
        rows.forEach(function (row, index) {
            row.querySelector('[data-faq-row-title]').textContent = `${config.faqNumber || 'FAQ'} ${index + 1}`;
            row.querySelectorAll('[data-field]').forEach(function (field) {
                field.name = `faqs[${index}][${field.dataset.field}]`;
            });
            row.querySelector('[data-remove-faq-row]').disabled = rows.length === 1;
        });
        countBadge.textContent = `${rows.length} / 25`;
        addButton.disabled = rows.length >= 25;
    };

    const addRow = function () {
        if (rowsContainer.querySelectorAll('[data-faq-row]').length >= 25) {
            Toast.fire({icon: 'warning', title: config.maximumRows});
            return;
        }

        const fragment = rowTemplate.content.cloneNode(true);
        const row = fragment.querySelector('[data-faq-row]');
        row.querySelector('[data-remove-faq-row]').addEventListener('click', function () {
            if (rowsContainer.querySelectorAll('[data-faq-row]').length === 1) {
                Toast.fire({icon: 'warning', title: config.minimumRows});
                return;
            }
            row.remove();
            reindexRows();
        });
        row.querySelector('[data-field="question"]').addEventListener('input', function (event) {
            row.querySelector('[data-question-count]').textContent = event.target.value.length;
        });
        row.querySelector('[data-field="answer"]').addEventListener('input', function (event) {
            row.querySelector('[data-answer-count]').textContent = event.target.value.length;
        });
        rowsContainer.appendChild(fragment);
        reindexRows();
        row.querySelector('[data-field="question"]').focus();
    };

    addButton.addEventListener('click', addRow);
    addRow();
});
