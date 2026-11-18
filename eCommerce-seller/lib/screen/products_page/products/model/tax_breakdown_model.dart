import 'package:hyper_commerce_seller/service/json_parser.dart';

class TaxBreakdown {
  final TaxBreakdownMarket? market;
  final String baseCurrencyCode;
  final bool isInclusive;
  final double totalRate;
  final TaxBreakdownPrice? regular;
  final TaxBreakdownPrice? special;
  final List<TaxBreakdownTaxClass> taxClasses;

  const TaxBreakdown({
    this.market,
    this.baseCurrencyCode = 'USD',
    this.isInclusive = false,
    this.totalRate = 0.0,
    this.regular,
    this.special,
    this.taxClasses = const [],
  });

  factory TaxBreakdown.fromJson(Map<String, dynamic> json) {
    return TaxBreakdown(
      market: json['market'] is Map<String, dynamic>
          ? TaxBreakdownMarket.fromJson(json['market'] as Map<String, dynamic>)
          : null,
      baseCurrencyCode: JsonParser.string(json['base_currency_code'] ?? 'USD'),
      isInclusive: JsonParser.boolValue(json['is_inclusive'] ?? false),
      totalRate: JsonParser.doubleValue(json['total_rate'] ?? 0.0),
      regular: json['regular'] is Map<String, dynamic>
          ? TaxBreakdownPrice.fromJson(json['regular'] as Map<String, dynamic>)
          : null,
      special: json['special'] is Map<String, dynamic>
          ? TaxBreakdownPrice.fromJson(json['special'] as Map<String, dynamic>)
          : null,
      taxClasses: (json['tax_classes'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(TaxBreakdownTaxClass.fromJson)
              .toList() ??
          const [],
    );
  }

  Map<String, dynamic> toJson() => {
        if (market != null) 'market': market!.toJson(),
        'base_currency_code': baseCurrencyCode,
        'is_inclusive': isInclusive,
        'total_rate': totalRate,
        if (regular != null) 'regular': regular!.toJson(),
        if (special != null) 'special': special!.toJson(),
        'tax_classes': taxClasses.map((t) => t.toJson()).toList(),
      };
}

class TaxBreakdownMarket {
  final int id;
  final String code;
  final String name;
  final String currencyCode;

  const TaxBreakdownMarket({
    required this.id,
    required this.code,
    required this.name,
    this.currencyCode = '',
  });

  factory TaxBreakdownMarket.fromJson(Map<String, dynamic> json) {
    return TaxBreakdownMarket(
      id: JsonParser.intValue(json['id']),
      code: JsonParser.string(json['code'] ?? ''),
      name: JsonParser.string(json['name'] ?? ''),
      currencyCode: JsonParser.string(json['currency_code'] ?? ''),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'code': code,
        'name': name,
        'currency_code': currencyCode,
      };
}

class TaxBreakdownPrice {
  final double priceExcludingTax;
  final double taxAmount;
  final double priceIncludingTax;

  const TaxBreakdownPrice({
    this.priceExcludingTax = 0.0,
    this.taxAmount = 0.0,
    this.priceIncludingTax = 0.0,
  });

  factory TaxBreakdownPrice.fromJson(Map<String, dynamic> json) {
    return TaxBreakdownPrice(
      priceExcludingTax:
          JsonParser.doubleValue(json['price_excluding_tax'] ?? 0.0),
      taxAmount: JsonParser.doubleValue(json['tax_amount'] ?? 0.0),
      priceIncludingTax:
          JsonParser.doubleValue(json['price_including_tax'] ?? 0.0),
    );
  }

  Map<String, dynamic> toJson() => {
        'price_excluding_tax': priceExcludingTax,
        'tax_amount': taxAmount,
        'price_including_tax': priceIncludingTax,
      };
}

class TaxBreakdownTaxClass {
  final int id;
  final String title;
  final double totalRate;
  final double? regularTaxAmount;
  final double? specialTaxAmount;
  final List<TaxBreakdownTaxRate> taxRates;

  const TaxBreakdownTaxClass({
    required this.id,
    required this.title,
    this.totalRate = 0.0,
    this.regularTaxAmount,
    this.specialTaxAmount,
    this.taxRates = const [],
  });

  factory TaxBreakdownTaxClass.fromJson(Map<String, dynamic> json) {
    return TaxBreakdownTaxClass(
      id: JsonParser.intValue(json['id']),
      title: JsonParser.string(json['title'] ?? ''),
      totalRate: JsonParser.doubleValue(json['total_rate'] ?? 0.0),
      regularTaxAmount: json['regular_tax_amount'] != null
          ? JsonParser.doubleValue(json['regular_tax_amount'])
          : null,
      specialTaxAmount: json['special_tax_amount'] != null
          ? JsonParser.doubleValue(json['special_tax_amount'])
          : null,
      taxRates: (json['tax_rates'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(TaxBreakdownTaxRate.fromJson)
              .toList() ??
          const [],
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'total_rate': totalRate,
        if (regularTaxAmount != null) 'regular_tax_amount': regularTaxAmount,
        if (specialTaxAmount != null) 'special_tax_amount': specialTaxAmount,
        'tax_rates': taxRates.map((r) => r.toJson()).toList(),
      };
}

class TaxBreakdownTaxRate {
  final int id;
  final String title;
  final double rate;
  final double? regularTaxAmount;
  final double? specialTaxAmount;

  const TaxBreakdownTaxRate({
    required this.id,
    required this.title,
    this.rate = 0.0,
    this.regularTaxAmount,
    this.specialTaxAmount,
  });

  factory TaxBreakdownTaxRate.fromJson(Map<String, dynamic> json) {
    return TaxBreakdownTaxRate(
      id: JsonParser.intValue(json['id']),
      title: JsonParser.string(json['title'] ?? ''),
      rate: JsonParser.doubleValue(json['rate'] ?? 0.0),
      regularTaxAmount: json['regular_tax_amount'] != null
          ? JsonParser.doubleValue(json['regular_tax_amount'])
          : null,
      specialTaxAmount: json['special_tax_amount'] != null
          ? JsonParser.doubleValue(json['special_tax_amount'])
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'rate': rate,
        if (regularTaxAmount != null) 'regular_tax_amount': regularTaxAmount,
        if (specialTaxAmount != null) 'special_tax_amount': specialTaxAmount,
      };
}
