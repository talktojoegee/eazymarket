document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-order-cancel-form]');
    if (!form) return;

    const symbol = form.dataset.symbol || '';
    const decimals = Number.parseInt(form.dataset.decimals || '2', 10);
    const symbolPosition = form.dataset.symbolPosition || 'before';
    const symbolSpace = form.dataset.spaceSymbol === '1' ? ' ' : '';
    const paid = Number.parseFloat(form.dataset.paid || '0');
    const alreadyRefunded = Number.parseFloat(form.dataset.alreadyRefunded || '0');
    const originalTotal = Number.parseFloat(form.dataset.originalTotal || '0');
    const originalSubtotal = Number.parseFloat(form.dataset.subtotal || '0');
    const originalPromo = Number.parseFloat(form.dataset.promo || '0');
    const originalTax = Number.parseFloat(form.dataset.tax || '0');
    const inputs = Array.from(form.querySelectorAll('.js-cancel-qty'));
    const subtotalElement = form.querySelector('#sumSubtotal');
    const taxElement = form.querySelector('#sumTax');
    const promoElement = form.querySelector('#sumPromo');
    const updatedElement = form.querySelector('#updatedTotal');
    const refundElement = form.querySelector('#refundOwed');
    const selectedElement = form.querySelector('[data-selected-count]');
    const submitButton = form.querySelector('[data-cancel-submit]');

    const format = (amount) => {
        const number = Math.max(0, Number(amount || 0)).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
        return symbolPosition === 'after'
            ? `${number}${symbolSpace}${symbol}`
            : `${symbol}${symbolSpace}${number}`;
    };

    const recompute = () => {
        let cancelledSubtotal = 0;
        let cancelledPromo = 0;
        let cancelledTax = 0;
        let selectedQuantity = 0;

        inputs.forEach((input) => {
            const maximum = Number.parseInt(input.dataset.max || '0', 10);
            let quantity = Number.parseInt(input.value || '0', 10);
            if (!Number.isFinite(quantity) || quantity < 0) quantity = 0;
            if (quantity > maximum) quantity = maximum;
            input.value = quantity;

            selectedQuantity += quantity;
            cancelledSubtotal += Number.parseFloat(input.dataset.unitSubtotal || '0') * quantity;
            cancelledPromo += Number.parseFloat(input.dataset.unitPromo || '0') * quantity;
            cancelledTax += Number.parseFloat(input.dataset.unitTax || '0') * quantity;
            input.closest('[data-cancel-item-row]')?.classList.toggle('bg-red-lt', quantity > 0);
        });

        const updatedSubtotal = Math.max(0, originalSubtotal - cancelledSubtotal);
        const updatedPromo = Math.max(0, originalPromo - cancelledPromo);
        const updatedTax = Math.max(0, originalTax - cancelledTax);
        const cancelledTotal = Math.max(0, cancelledSubtotal - cancelledPromo + cancelledTax);
        const updatedTotal = Math.max(0, originalTotal - cancelledTotal);
        const refundOwed = Math.max(0, paid - alreadyRefunded - updatedTotal);

        if (subtotalElement) subtotalElement.textContent = format(updatedSubtotal);
        if (taxElement) taxElement.textContent = format(updatedTax);
        if (promoElement) promoElement.textContent = `−${format(updatedPromo)}`;
        if (updatedElement) updatedElement.textContent = format(updatedTotal);
        if (refundElement) refundElement.textContent = format(refundOwed);
        if (selectedElement) selectedElement.textContent = String(selectedQuantity);
        if (submitButton) submitButton.disabled = selectedQuantity === 0;
    };

    inputs.forEach((input) => input.addEventListener('input', recompute));
    recompute();
});
