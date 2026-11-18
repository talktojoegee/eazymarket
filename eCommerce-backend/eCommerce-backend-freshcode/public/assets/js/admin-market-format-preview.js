/**
 * Live preview for the Number Format card on the Markets form.
 *
 * Mirrors PricePresenter::format() server-side so the admin sees exactly
 * what a customer would see for a sample value. Both render the same
 * deterministic algorithm — no API call.
 */
(function () {
    'use strict';

    const SAMPLE_POSITIVE = 1234567.89;
    const SAMPLE_NEGATIVE = -1234.5;

    const card = document.getElementById('market-number-format-card');
    if (!card) return;

    const previewEl = document.getElementById('market-number-format-preview');
    if (!previewEl) return;

    const currencyInput = document.querySelector('[name="currency_code"]');

    /**
     * Read the current form values into a single rules object.
     */
    function readRules() {
        return {
            symbol_position:      val('symbol_position', 'before'),
            space_between_symbol: card.querySelector('[data-mnf="space_between_symbol"]').checked,
            thousand_separator:   val('thousand_separator', ','),
            decimal_separator:    val('decimal_separator', '.'),
            grouping_style:       val('grouping_style', 'western'),
            decimal_places:       parseInt(val('decimal_places', '2'), 10) || 0,
            negative_format:      val('negative_format', '-{n}'),
        };
    }

    function val(name, fallback) {
        const el = card.querySelector('[data-mnf="' + name + '"]');
        if (!el) return fallback;
        const v = el.value;
        return (v === null || v === undefined || v === '') ? fallback : v;
    }

    /**
     * Currency symbol comes from the currency picker's selected option's
     * data attribute when the picker is a TomSelect; otherwise we fall back
     * to the symbol baked onto the preview element at server-render time.
     */
    function currentSymbol() {
        if (currencyInput && currencyInput.tomselect) {
            const item = currencyInput.tomselect.options[currencyInput.value];
            if (item && item.symbol) return item.symbol;
        }
        return previewEl.dataset.currencySymbol || '$';
    }

    /**
     * Group an integer-string per the chosen grouping style.
     *   western : 1,234,567
     *   indian  : 12,34,567
     */
    function groupInteger(digits, sep, style) {
        if (!sep || digits.length <= 3) return digits;
        if (style === 'indian') {
            const reversed = digits.split('').reverse().join('');
            const first    = reversed.slice(0, 3);
            const rest     = reversed.slice(3);
            const chunks   = rest.length === 0 ? [] : rest.match(/.{1,2}/g);
            return [first].concat(chunks).join(sep).split('').reverse().join('');
        }
        // Western — straight 3-digit grouping.
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, sep);
    }

    /**
     * Format a single value per the rules. Mirrors PricePresenter::format
     * step-for-step so the preview can never diverge from the server.
     */
    function format(amount, symbol, rules) {
        const negative = amount < 0;
        const abs      = Math.abs(amount);
        const rounded  = abs.toFixed(rules.decimal_places);
        const [intPart, fracPart] = rules.decimal_places > 0
            ? rounded.split('.')
            : [rounded, ''];

        const intGrouped = groupInteger(intPart, rules.thousand_separator, rules.grouping_style);
        const number = rules.decimal_places > 0
            ? intGrouped + rules.decimal_separator + fracPart
            : intGrouped;

        const space = rules.space_between_symbol ? ' ' : '';
        const withSym = rules.symbol_position === 'after'
            ? number + space + symbol
            : symbol + space + number;

        if (!negative) return withSym;
        const tpl = rules.negative_format && rules.negative_format.indexOf('{n}') !== -1
            ? rules.negative_format
            : '-{n}';
        return tpl.replace('{n}', withSym);
    }

    function render() {
        const rules  = readRules();
        const symbol = currentSymbol();
        const pos    = format(SAMPLE_POSITIVE, symbol, rules);
        const neg    = format(SAMPLE_NEGATIVE, symbol, rules);
        previewEl.textContent = pos + '   /   ' + neg;
    }

    // Wire change listeners on every field carrying data-mnf.
    card.querySelectorAll('[data-mnf]').forEach(function (el) {
        el.addEventListener('input',  render);
        el.addEventListener('change', render);
    });
    if (currencyInput) {
        currencyInput.addEventListener('change', render);
    }

    render();
})();
