import 'package:hyper_commerce_seller/service/json_parser.dart';

/// Payload from settings API under `variable: 'watch_and_buy'`.
class WatchAndBuySettings {
  final bool enabled;
  final String title;
  final int maxDurationSeconds;
  final int maxUploadMb;
  final int maxProductsPerReel;
  final bool statusesEnabled;
  final int maxStatusDurationSeconds;
  final int maxProductsPerStatus;

  const WatchAndBuySettings({
    this.enabled = true,
    this.title = 'Watch & Buy',
    this.maxDurationSeconds = 60,
    this.maxUploadMb = 10,
    this.maxProductsPerReel = 10,
    this.statusesEnabled = true,
    this.maxStatusDurationSeconds = 15,
    this.maxProductsPerStatus = 5,
  });

  factory WatchAndBuySettings.fromJson(Map<String, dynamic> json) {
    return WatchAndBuySettings(
      enabled: JsonParser.boolValue(json['enabled'] ?? true),
      title: JsonParser.string(json['title'] ?? 'Watch & Buy'),
      maxDurationSeconds: JsonParser.intValue(json['maxDurationSeconds'] ?? 60),
      maxUploadMb: JsonParser.intValue(json['maxUploadMb'] ?? 10),
      maxProductsPerReel: JsonParser.intValue(json['maxProductsPerReel'] ?? 10),
      statusesEnabled: JsonParser.boolValue(json['statusesEnabled'] ?? true),
      maxStatusDurationSeconds: JsonParser.intValue(json['maxStatusDurationSeconds'] ?? 15),
      maxProductsPerStatus: JsonParser.intValue(json['maxProductsPerStatus'] ?? 5),
    );
  }

  Map<String, dynamic> toJson() => {
        'enabled': enabled,
        'title': title,
        'maxDurationSeconds': maxDurationSeconds,
        'maxUploadMb': maxUploadMb,
        'maxProductsPerReel': maxProductsPerReel,
        'statusesEnabled': statusesEnabled,
        'maxStatusDurationSeconds': maxStatusDurationSeconds,
        'maxProductsPerStatus': maxProductsPerStatus,
      };

  static const WatchAndBuySettings defaults = WatchAndBuySettings();
}
