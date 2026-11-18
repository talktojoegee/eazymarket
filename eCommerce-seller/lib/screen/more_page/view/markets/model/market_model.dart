import 'package:hyper_commerce_seller/service/json_parser.dart';

/// A market = currency + number formatting + default language + a set of
/// countries. Mirrors the panel `MarketResource`. The `code` is the stable
/// public identifier (persist that, not the numeric id).
///
/// Tolerates two payload shapes: the full `MarketResource`, and the minimal
/// `markets/search` lookup row (`{value, text, code, name}`), where only
/// id/code/name are present.
class Market {
  final int id;
  final String code;
  final String name;
  final String currencyCode;
  final String? defaultLanguage;
  final bool isDefault;
  final int priority;
  final String? status;
  final List<MarketCountry> countries;
  final CurrencyInfo? currency;
  final MoneyFormat format;

  const Market({
    required this.id,
    required this.code,
    required this.name,
    this.currencyCode = '',
    this.defaultLanguage,
    this.isDefault = false,
    this.priority = 0,
    this.status,
    this.countries = const [],
    this.currency,
    required this.format,
  });

  factory Market.fromJson(Map<String, dynamic> json) {
    // markets/search rows carry the id under `value`.
    final dynamic rawId = json['id'] ?? json['value'];

    return Market(
      id: JsonParser.intValue(rawId),
      code: JsonParser.string(json['code'] ?? ''),
      name: JsonParser.string(json['name'] ?? ''),
      currencyCode: JsonParser.string(json['currency_code'] ?? ''),
      defaultLanguage: json['default_language'] != null ? JsonParser.string(json['default_language']) : null,
      isDefault: JsonParser.boolValue(json['is_default'] ?? false),
      priority: JsonParser.intValue(json['priority'] ?? 0),
      status: json['status'] != null ? JsonParser.string(json['status']) : null,
      countries: (json['countries'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(MarketCountry.fromJson)
              .toList() ??
          const [],
      currency: json['currency'] is Map<String, dynamic>
          ? CurrencyInfo.fromJson(json['currency'] as Map<String, dynamic>)
          : null,
      format: json['format'] is Map<String, dynamic>
          ? MoneyFormat.fromJson(json['format'] as Map<String, dynamic>)
          : MoneyFormat.fallback(),
    );
  }
}

class MarketCountry {
  final int id;
  final String iso2;
  final String name;

  const MarketCountry({required this.id, required this.iso2, required this.name});

  factory MarketCountry.fromJson(Map<String, dynamic> json) => MarketCountry(
        id: JsonParser.intValue(json['id']),
        iso2: JsonParser.string(json['iso2'] ?? ''),
        name: JsonParser.string(json['name'] ?? ''),
      );
}

class CurrencyInfo {
  final String code;
  final String name;
  final String symbol;
  final int decimalPlaces;
  final bool isBase;

  const CurrencyInfo({
    this.code = '',
    this.name = '',
    this.symbol = '',
    this.decimalPlaces = 2,
    this.isBase = false,
  });

  factory CurrencyInfo.fromJson(Map<String, dynamic> json) => CurrencyInfo(
        code: JsonParser.string(json['code'] ?? ''),
        name: JsonParser.string(json['name'] ?? ''),
        symbol: JsonParser.string(json['symbol'] ?? ''),
        decimalPlaces: JsonParser.intValue(json['decimal_places'] ?? 2),
        isBase: JsonParser.boolValue(json['is_base'] ?? false),
      );
}

/// Number-formatting rules for a currency. Mirrors the panel `PricePresenter`
/// format block. Drive money display from this, never from a single global
/// symbol.
class MoneyFormat {
  final String symbolPosition; // 'before' | 'after'
  final bool spaceBetweenSymbol;
  final String thousandSeparator;
  final String decimalSeparator;
  final String groupingStyle; // 'western' | 'indian'
  final int decimalPlaces;
  final String negativeFormat; // template containing '{n}', e.g. '-{n}'

  const MoneyFormat({
    this.symbolPosition = 'before',
    this.spaceBetweenSymbol = false,
    this.thousandSeparator = ',',
    this.decimalSeparator = '.',
    this.groupingStyle = 'western',
    this.decimalPlaces = 2,
    this.negativeFormat = '-{n}',
  });

  factory MoneyFormat.fallback() => const MoneyFormat();

  factory MoneyFormat.fromJson(Map<String, dynamic> json) => MoneyFormat(
        symbolPosition: JsonParser.string(json['symbol_position'] ?? 'before'),
        spaceBetweenSymbol: JsonParser.boolValue(json['space_between_symbol'] ?? false),
        thousandSeparator: JsonParser.string(json['thousand_separator'] ?? ','),
        decimalSeparator: JsonParser.string(json['decimal_separator'] ?? '.'),
        groupingStyle: JsonParser.string(json['grouping_style'] ?? 'western'),
        decimalPlaces: JsonParser.intValue(json['decimal_places'] ?? 2),
        negativeFormat: JsonParser.string(json['negative_format'] ?? '-{n}'),
      );
}

/// The per-amount currency object attached to orders / wallet responses
/// (`PricePresenter::payload`): `{code, symbol, format}`.
class CurrencyPayload {
  final String code;
  final String symbol;
  final MoneyFormat format;

  const CurrencyPayload({this.code = '', this.symbol = '', required this.format});

  factory CurrencyPayload.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return CurrencyPayload(format: MoneyFormat.fallback());
    }
    return CurrencyPayload(
      code: JsonParser.string(json['code'] ?? ''),
      symbol: JsonParser.string(json['symbol'] ?? ''),
      format: json['format'] is Map<String, dynamic>
          ? MoneyFormat.fromJson(json['format'] as Map<String, dynamic>)
          : MoneyFormat.fallback(),
    );
  }
}

class MarketsResponse {
  final bool success;
  final String message;
  final List<Market> markets;

  const MarketsResponse({required this.success, this.message = '', this.markets = const []});

  factory MarketsResponse.fromJson(dynamic json) {
    // markets/search returns a bare list; other endpoints wrap in {success,data}.
    if (json is List) {
      return MarketsResponse(
        success: true,
        markets: json.whereType<Map<String, dynamic>>().map(Market.fromJson).toList(),
      );
    }
    final map = json as Map<String, dynamic>;
    final data = map['data'];
    final list = data is List ? data : (data is Map<String, dynamic> ? data['markets'] : null);
    return MarketsResponse(
      success: JsonParser.boolValue(map['success'] ?? true),
      message: JsonParser.string(map['message'] ?? ''),
      markets: (list as List?)?.whereType<Map<String, dynamic>>().map(Market.fromJson).toList() ?? const [],
    );
  }
}
