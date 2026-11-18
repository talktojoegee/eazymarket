import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';
import 'package:hyper_commerce_seller/service/json_parser.dart';

class CurrencyConversionResponse {
  final bool success;
  final String message;
  final CurrencyConversionData? data;

  const CurrencyConversionResponse({
    required this.success,
    this.message = '',
    this.data,
  });

  factory CurrencyConversionResponse.fromJson(Map<String, dynamic> json) {
    return CurrencyConversionResponse(
      success: JsonParser.boolValue(json['success'] ?? false),
      message: JsonParser.string(json['message'] ?? ''),
      data: json['data'] != null
          ? CurrencyConversionData.fromJson(json['data'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'success': success,
        'message': message,
        if (data != null) 'data': data!.toJson(),
      };
}

class CurrencyConversionData {
  final BaseCurrency? baseCurrency;
  final double amountBase;
  final List<MarketConversion> markets;

  const CurrencyConversionData({
    this.baseCurrency,
    this.amountBase = 1.0,
    this.markets = const [],
  });

  factory CurrencyConversionData.fromJson(Map<String, dynamic> json) {
    return CurrencyConversionData(
      baseCurrency: json['base_currency'] is Map<String, dynamic>
          ? BaseCurrency.fromJson(json['base_currency'] as Map<String, dynamic>)
          : null,
      amountBase: JsonParser.doubleValue(json['amount_base'] ?? 1.0),
      markets: (json['markets'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(MarketConversion.fromJson)
              .toList() ??
          const [],
    );
  }

  Map<String, dynamic> toJson() => {
        if (baseCurrency != null) 'base_currency': baseCurrency!.toJson(),
        'amount_base': amountBase,
        'markets': markets.map((m) => m.toJson()).toList(),
      };
}

class BaseCurrency {
  final String code;
  final String symbol;
  final int decimalPlaces;

  const BaseCurrency({
    this.code = 'USD',
    this.symbol = '\$',
    this.decimalPlaces = 2,
  });

  factory BaseCurrency.fromJson(Map<String, dynamic> json) {
    return BaseCurrency(
      code: JsonParser.string(json['code'] ?? 'USD'),
      symbol: JsonParser.string(json['symbol'] ?? '\$'),
      decimalPlaces: JsonParser.intValue(json['decimal_places'] ?? 2),
    );
  }

  Map<String, dynamic> toJson() => {
        'code': code,
        'symbol': symbol,
        'decimal_places': decimalPlaces,
      };
}

class MarketConversion {
  final int id;
  final String code;
  final String name;
  final String targetCurrencyCode;
  final String currencyCode;
  final String currencySymbol;
  final double conversionRate;
  final bool fxFallback;
  final double convertedAmount;
  final String formattedAmount;
  final MoneyFormat format;

  const MarketConversion({
    required this.id,
    required this.code,
    required this.name,
    this.targetCurrencyCode = '',
    this.currencyCode = '',
    this.currencySymbol = '',
    this.conversionRate = 1.0,
    this.fxFallback = false,
    this.convertedAmount = 0.0,
    this.formattedAmount = '',
    required this.format,
  });

  factory MarketConversion.fromJson(Map<String, dynamic> json) {
    return MarketConversion(
      id: JsonParser.intValue(json['id']),
      code: JsonParser.string(json['code'] ?? ''),
      name: JsonParser.string(json['name'] ?? ''),
      targetCurrencyCode: JsonParser.string(json['target_currency_code'] ?? ''),
      currencyCode: JsonParser.string(json['currency_code'] ?? ''),
      currencySymbol: JsonParser.string(json['currency_symbol'] ?? ''),
      conversionRate: JsonParser.doubleValue(json['conversion_rate'] ?? 1.0),
      fxFallback: JsonParser.boolValue(json['fx_fallback'] ?? false),
      convertedAmount: JsonParser.doubleValue(json['converted_amount'] ?? 0.0),
      formattedAmount: JsonParser.string(json['formatted_amount'] ?? ''),
      format: json['format'] is Map<String, dynamic>
          ? MoneyFormat.fromJson(json['format'] as Map<String, dynamic>)
          : MoneyFormat.fallback(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'code': code,
        'name': name,
        'target_currency_code': targetCurrencyCode,
        'currency_code': currencyCode,
        'currency_symbol': currencySymbol,
        'conversion_rate': conversionRate,
        'fx_fallback': fxFallback,
        'converted_amount': convertedAmount,
        'formatted_amount': formattedAmount,
        'format': {
          'symbol_position': format.symbolPosition,
          'space_between_symbol': format.spaceBetweenSymbol,
          'thousand_separator': format.thousandSeparator,
          'decimal_separator': format.decimalSeparator,
          'grouping_style': format.groupingStyle,
          'decimal_places': format.decimalPlaces,
          'negative_format': format.negativeFormat,
        },
      };
}
