import 'package:hyper_commerce_seller/service/json_parser.dart';

/// A ship-from location (`seller/locations`). Mirrors the panel
/// SellerStoreLocationApiController payload, including Shiprocket registration
/// state. Local delivery / pickup / POS flags exist in the model but are
/// "coming soon" — only online fulfilment is active.
class StoreLocation {
  final int id;
  final int storeId;
  final String name;
  final String address1;
  final String? address2;
  final String city;
  final String state;
  final String countryIso2;
  final String? countryName;
  final String pinCode;
  final String? latitude;
  final String? longitude;
  final bool fulfillsOnlineOrders;
  final bool isActive;
  final bool isDefault;
  final bool isRegisteredWithShiprocket;
  final String shiprocketSyncStatus;
  final String? shiprocketSyncError;
  final String createdAt;

  const StoreLocation({
    required this.id,
    required this.storeId,
    required this.name,
    this.address1 = '',
    this.address2,
    this.city = '',
    this.state = '',
    this.countryIso2 = '',
    this.countryName,
    this.pinCode = '',
    this.latitude,
    this.longitude,
    this.fulfillsOnlineOrders = true,
    this.isActive = true,
    this.isDefault = false,
    this.isRegisteredWithShiprocket = false,
    this.shiprocketSyncStatus = '',
    this.shiprocketSyncError,
    this.createdAt = '',
  });

  factory StoreLocation.fromJson(Map<String, dynamic> json) => StoreLocation(
        id: JsonParser.intValue(json['id']),
        storeId: JsonParser.intValue(json['store_id']),
        name: JsonParser.string(json['name'] ?? ''),
        address1: JsonParser.string(json['address_1'] ?? ''),
        address2: json['address_2'] != null ? JsonParser.string(json['address_2']) : null,
        city: JsonParser.string(json['city'] ?? ''),
        state: JsonParser.string(json['state'] ?? ''),
        countryIso2: JsonParser.string(json['country_iso2'] ?? ''),
        countryName: json['country_name'] != null ? JsonParser.string(json['country_name']) : null,
        pinCode: JsonParser.string(json['pin_code'] ?? ''),
        latitude: json['latitude'] != null ? JsonParser.string(json['latitude']) : null,
        longitude: json['longitude'] != null ? JsonParser.string(json['longitude']) : null,
        fulfillsOnlineOrders: JsonParser.boolValue(json['fulfills_online_orders'] ?? true),
        isActive: JsonParser.boolValue(json['is_active'] ?? true),
        isDefault: JsonParser.boolValue(json['is_default'] ?? false),
        isRegisteredWithShiprocket: JsonParser.boolValue(json['is_registered_with_shiprocket'] ?? false),
        shiprocketSyncStatus: JsonParser.string(json['shiprocket_sync_status'] ?? ''),
        shiprocketSyncError: json['shiprocket_sync_error'] != null ? JsonParser.string(json['shiprocket_sync_error']) : null,
        createdAt: JsonParser.string(json['created_at'] ?? ''),
      );
}

class StoreLocationsResponse {
  final bool success;
  final String message;
  final List<StoreLocation> locations;

  const StoreLocationsResponse({required this.success, this.message = '', this.locations = const []});

  factory StoreLocationsResponse.fromJson(dynamic json) {
    final map = json as Map<String, dynamic>;
    final data = map['data'];
    return StoreLocationsResponse(
      success: JsonParser.boolValue(map['success'] ?? false),
      message: JsonParser.string(map['message'] ?? ''),
      locations: (data as List?)?.whereType<Map<String, dynamic>>().map(StoreLocation.fromJson).toList() ?? const [],
    );
  }
}
