import 'package:hyper_commerce_seller/service/json_parser.dart';

/// Payload from settings API under `variable: 'shipping'`.
///
/// The seller admin can enable/disable each shipping mode and rebrand its
/// title. `manualValue` / `shiprocketValue` are the wire values the orders
/// API expects when filtering by fulfillment method (defaults are `manual`
/// and `shiprocket` — the admin can override).
class ShippingSettings {
  final bool manualEnabled;
  final String manualTitle;
  final String manualValue;
  final int manualDispatchSlaDays;

  final bool shiprocketEnabled;
  final String shiprocketTitle;
  final String shiprocketValue;
  final String shiprocketRateMode; // e.g. carrier_calculated | flat
  final double shiprocketFlatRate;
  final double shiprocketFreeAbove;

  const ShippingSettings({
    this.manualEnabled = true,
    this.manualTitle = 'Manual',
    this.manualValue = 'manual',
    this.manualDispatchSlaDays = 0,
    this.shiprocketEnabled = true,
    this.shiprocketTitle = 'Shiprocket',
    this.shiprocketValue = 'shiprocket',
    this.shiprocketRateMode = '',
    this.shiprocketFlatRate = 0,
    this.shiprocketFreeAbove = 0,
  });

  factory ShippingSettings.fromJson(Map<String, dynamic> json) {
    return ShippingSettings(
      manualEnabled: JsonParser.boolValue(json['manualEnabled'] ?? true),
      manualTitle: JsonParser.string(json['manualTitle'] ?? 'Manual'),
      manualValue: JsonParser.string(json['manual_value'] ?? 'manual'),
      manualDispatchSlaDays:
          JsonParser.intValue(json['manualDispatchSlaDays'] ?? 0),
      shiprocketEnabled:
          JsonParser.boolValue(json['shiprocketEnabled'] ?? true),
      shiprocketTitle:
          JsonParser.string(json['shiprocketTitle'] ?? 'Shiprocket'),
      shiprocketValue:
          JsonParser.string(json['shiprocket_value'] ?? 'shiprocket'),
      shiprocketRateMode:
          JsonParser.string(json['shiprocketRateMode'] ?? ''),
      shiprocketFlatRate:
          JsonParser.doubleValue(json['shiprocketFlatRate'] ?? 0),
      shiprocketFreeAbove:
          JsonParser.doubleValue(json['shiprocketFreeAbove'] ?? 0),
    );
  }

  Map<String, dynamic> toJson() => {
        'manualEnabled': manualEnabled,
        'manualTitle': manualTitle,
        'manual_value': manualValue,
        'manualDispatchSlaDays': manualDispatchSlaDays,
        'shiprocketEnabled': shiprocketEnabled,
        'shiprocketTitle': shiprocketTitle,
        'shiprocket_value': shiprocketValue,
        'shiprocketRateMode': shiprocketRateMode,
        'shiprocketFlatRate': shiprocketFlatRate,
        'shiprocketFreeAbove': shiprocketFreeAbove,
      };

  static const ShippingSettings defaults = ShippingSettings();
}
