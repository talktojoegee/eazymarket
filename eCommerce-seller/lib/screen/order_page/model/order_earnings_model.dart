import 'package:hyper_commerce_seller/service/json_parser.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';

/// Earnings & settlement breakdown for one seller order — mirrors the panel's
/// "Earnings & settlement" tab (OrderEarningsCalculator::forSellerOrder).
class OrderEarnings {
  final String currencyCode;
  final String paymentMethod;
  final bool isCod;
  final bool settled;
  final EarningsTotals totals;
  final EarningsArchive? archive;
  final List<EarningsGroup> groups;
  final CurrencyPayload? currency;

  const OrderEarnings({
    this.currencyCode = '',
    this.paymentMethod = '',
    this.isCod = false,
    this.settled = false,
    this.totals = const EarningsTotals(),
    this.archive,
    this.groups = const [],
    this.currency,
  });

  factory OrderEarnings.fromJson(dynamic json) {
    final data = (json as Map<String, dynamic>)['data'] as Map<String, dynamic>? ?? {};
    final earnings = data['earnings'] as Map<String, dynamic>? ?? {};
    final groups = (earnings['seller_orders'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(EarningsGroup.fromJson)
            .toList() ??
        const [];
    return OrderEarnings(
      currencyCode: JsonParser.string(earnings['currency_code'] ?? ''),
      paymentMethod: JsonParser.string(earnings['payment_method'] ?? ''),
      isCod: JsonParser.boolValue(earnings['is_cod'] ?? false),
      settled: JsonParser.boolValue(earnings['settled'] ?? false),
      totals: EarningsTotals.fromJson(earnings['totals'] as Map<String, dynamic>? ?? {}),
      archive: earnings['archive'] is Map<String, dynamic>
          ? EarningsArchive.fromJson(earnings['archive'] as Map<String, dynamic>)
          : null,
      groups: groups,
      currency: data['currency'] is Map<String, dynamic>
          ? CurrencyPayload.fromJson(data['currency'] as Map<String, dynamic>)
          : null,
    );
  }
}

class EarningsTotals {
  final double itemsSubtotal;
  final double taxTotal;
  final double commissionTotal;
  final double shippingTotal;
  final double sellerNet;

  const EarningsTotals({
    this.itemsSubtotal = 0,
    this.taxTotal = 0,
    this.commissionTotal = 0,
    this.shippingTotal = 0,
    this.sellerNet = 0,
  });

  factory EarningsTotals.fromJson(Map<String, dynamic> json) => EarningsTotals(
        itemsSubtotal: JsonParser.doubleValue(json['items_subtotal'] ?? 0),
        taxTotal: JsonParser.doubleValue(json['tax_total'] ?? 0),
        commissionTotal: JsonParser.doubleValue(json['commission_total'] ?? 0),
        shippingTotal: JsonParser.doubleValue(json['shipping_total'] ?? 0),
        sellerNet: JsonParser.doubleValue(json['seller_net'] ?? 0),
      );
}

class EarningsArchive {
  final bool isArchived;
  final bool eligibleNow;
  final String estimatedDate;
  final int graceDays;

  const EarningsArchive({
    this.isArchived = false,
    this.eligibleNow = false,
    this.estimatedDate = '',
    this.graceDays = 0,
  });

  factory EarningsArchive.fromJson(Map<String, dynamic> json) => EarningsArchive(
        isArchived: JsonParser.boolValue(json['is_archived'] ?? false),
        eligibleNow: JsonParser.boolValue(json['eligible_now'] ?? false),
        estimatedDate: JsonParser.string(json['estimated_date'] ?? ''),
        graceDays: JsonParser.intValue(json['grace_days'] ?? 0),
      );
}

class EarningsGroup {
  final int sellerOrderId;
  final String fulfillmentMethod;
  final String fulfillmentStatus;
  final double itemsSubtotal;
  final double taxTotal;
  final double commissionTotal;
  final double shippingCharge;
  final double shippingToSeller;
  final double sellerNet;
  final double platformTake;
  final String direction; // platform_owes_seller | seller_owes_platform
  final double settlementAmount;
  final List<EarningsLine> items;

  const EarningsGroup({
    this.sellerOrderId = 0,
    this.fulfillmentMethod = '',
    this.fulfillmentStatus = '',
    this.itemsSubtotal = 0,
    this.taxTotal = 0,
    this.commissionTotal = 0,
    this.shippingCharge = 0,
    this.shippingToSeller = 0,
    this.sellerNet = 0,
    this.platformTake = 0,
    this.direction = '',
    this.settlementAmount = 0,
    this.items = const [],
  });

  bool get platformOwesSeller => !direction.contains('seller_owes');

  factory EarningsGroup.fromJson(Map<String, dynamic> json) => EarningsGroup(
        sellerOrderId: JsonParser.intValue(json['seller_order_id'] ?? 0),
        fulfillmentMethod: JsonParser.string(json['fulfillment_method'] ?? ''),
        fulfillmentStatus: JsonParser.string(json['fulfillment_status'] ?? ''),
        itemsSubtotal: JsonParser.doubleValue(json['items_subtotal'] ?? 0),
        taxTotal: JsonParser.doubleValue(json['tax_total'] ?? 0),
        commissionTotal: JsonParser.doubleValue(json['commission_total'] ?? 0),
        shippingCharge: JsonParser.doubleValue(json['shipping_charge'] ?? 0),
        shippingToSeller: JsonParser.doubleValue(json['shipping_to_seller'] ?? 0),
        sellerNet: JsonParser.doubleValue(json['seller_net'] ?? 0),
        platformTake: JsonParser.doubleValue(json['platform_take'] ?? 0),
        direction: JsonParser.string(json['direction'] ?? ''),
        settlementAmount: JsonParser.doubleValue(json['settlement_amount'] ?? 0),
        items: (json['items'] as List?)
                ?.whereType<Map<String, dynamic>>()
                .map(EarningsLine.fromJson)
                .toList() ??
            const [],
      );
}

class EarningsLine {
  final String title;
  final String variant;
  final int quantity;
  final String status;
  final bool isCounted;
  final double subtotal;
  final double tax;
  final double commission;
  final double commissionRate;
  final double lineEarning;

  const EarningsLine({
    this.title = '',
    this.variant = '',
    this.quantity = 0,
    this.status = '',
    this.isCounted = false,
    this.subtotal = 0,
    this.tax = 0,
    this.commission = 0,
    this.commissionRate = 0,
    this.lineEarning = 0,
  });

  factory EarningsLine.fromJson(Map<String, dynamic> json) => EarningsLine(
        title: JsonParser.string(json['title'] ?? ''),
        variant: JsonParser.string(json['variant'] ?? ''),
        quantity: JsonParser.intValue(json['quantity'] ?? 0),
        status: JsonParser.string(json['status'] ?? ''),
        isCounted: JsonParser.boolValue(json['is_counted'] ?? false),
        subtotal: JsonParser.doubleValue(json['subtotal'] ?? 0),
        tax: JsonParser.doubleValue(json['tax'] ?? 0),
        commission: JsonParser.doubleValue(json['commission'] ?? 0),
        commissionRate: JsonParser.doubleValue(json['commission_rate'] ?? 0),
        lineEarning: JsonParser.doubleValue(json['line_earning'] ?? 0),
      );
}
