import 'package:hyper_commerce_seller/service/json_parser.dart';

/// Specific model for 'app' settings from settings API under `variable: 'app'`.
class AppSettings {
  final String appstoreLink;
  final String playstoreLink;
  final String appScheme;
  final String sellerMapKey;
  final String fontFamily;

  const AppSettings({
    this.appstoreLink = '',
    this.playstoreLink = '',
    this.appScheme = '',
    this.sellerMapKey = '',
    this.fontFamily = '',
  });

  factory AppSettings.fromJson(Map<String, dynamic> json) {
    return AppSettings(
      appstoreLink: JsonParser.string(json['sellerAppstoreLink']),
      playstoreLink: JsonParser.string(json['sellerPlaystoreLink']),
      appScheme: JsonParser.string(json['sellerAppScheme']),
      sellerMapKey: JsonParser.string(json['sellerMapKey']),
      fontFamily: JsonParser.string(json['sellerAppFontFamily']),
    );
  }

  Map<String, dynamic> toJson() => {
        'sellerAppstoreLink': appstoreLink,
        'sellerPlaystoreLink': playstoreLink,
        'sellerAppScheme': appScheme,
        'sellerMapKey': sellerMapKey,
        'sellerAppFontFamily': fontFamily,
      };

  static const AppSettings defaults = AppSettings();
}
