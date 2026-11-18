import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';

/// Formats money exactly like the panel `PricePresenter::formatWith` so amounts
/// render identically on the app and the web/admin panels. Feed it the symbol +
/// `MoneyFormat` that arrive with each order / wallet payload — never a single
/// hardcoded global symbol.
class MoneyFormatter {
  const MoneyFormatter._();

  /// Format [amount] using [symbol] and [format]. Handles western/indian
  /// grouping, symbol position + spacing, and the negative template.
  static String format(num amount, String symbol, MoneyFormat format) {
    final int decimals = format.decimalPlaces;
    final bool negative = amount < 0;
    final double abs = amount.abs().toDouble();

    final String rounded = abs.toStringAsFixed(decimals);
    String intPart;
    String fracPart;
    if (decimals > 0) {
      final dot = rounded.indexOf('.');
      intPart = rounded.substring(0, dot);
      fracPart = rounded.substring(dot + 1);
    } else {
      intPart = rounded;
      fracPart = '';
    }

    final String intGrouped = _groupInteger(intPart, format.thousandSeparator, format.groupingStyle);

    final String number = decimals > 0 ? '$intGrouped${format.decimalSeparator}$fracPart' : intGrouped;

    final String space = format.spaceBetweenSymbol ? ' ' : '';
    final String withSym = format.symbolPosition == 'after' ? '$number$space$symbol' : '$symbol$space$number';

    if (!negative) {
      return withSym;
    }

    final String template = format.negativeFormat.isEmpty ? '-{n}' : format.negativeFormat;
    return template.contains('{n}') ? template.replaceAll('{n}', withSym) : '-$withSym';
  }

  /// Convenience overload for the per-amount currency payload attached to
  /// orders / wallet responses.
  static String formatPayload(num amount, CurrencyPayload currency) =>
      format(amount, currency.symbol, currency.format);

  static String _groupInteger(String digits, String separator, String style) {
    if (separator.isEmpty || digits.length <= 3) {
      return digits;
    }

    if (style == 'indian') {
      final String reversed = _reverse(digits);
      final String first = reversed.substring(0, 3);
      final String rest = reversed.substring(3);
      final List<String> chunks = rest.isEmpty ? <String>[] : _chunk(rest, 2);
      final List<String> parts = <String>[first, ...chunks];
      return _reverse(parts.join(separator));
    }

    // Western: group by 3 from the right.
    final List<String> groups = _chunk(_reverse(digits), 3).map(_reverse).toList().reversed.toList();
    return groups.join(separator);
  }

  static String _reverse(String s) => String.fromCharCodes(s.runes.toList().reversed);

  static List<String> _chunk(String s, int size) {
    final List<String> out = <String>[];
    for (int i = 0; i < s.length; i += size) {
      out.add(s.substring(i, i + size > s.length ? s.length : i + size));
    }
    return out;
  }
}
