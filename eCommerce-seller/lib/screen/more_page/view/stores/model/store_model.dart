import 'package:geocoding/geocoding.dart';
import 'package:hyper_commerce_seller/service/json_parser.dart';

const String modelName = 'stores_model';

class StoresResponse {
  bool? success;
  String? message;
  StoresPageData? data;

  StoresResponse({this.success, this.message, this.data});

  factory StoresResponse.fromJson(Map<String, dynamic> json) {
    return StoresResponse(
      success: JsonParser.boolValue(json['success'] ?? false),
      message: JsonParser.string(json['message'] ?? ''),
      data: json['data'] != null
          ? StoresPageData.fromJson(json['data'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['success'] = success;
    data['message'] = message;
    if (this.data != null) {
      data['data'] = this.data!.toJson();
    }
    return data;
  }
}

class StoresPageData {
  int? currentPage;
  int? lastPage;
  int? perPage;
  int? total;
  List<Store>? stores;

  StoresPageData({
    this.currentPage,
    this.lastPage,
    this.perPage,
    this.total,
    this.stores,
  });

  factory StoresPageData.fromJson(Map<String, dynamic> json) {
    return StoresPageData(
      currentPage: JsonParser.intValue(json['current_page'] ?? 1),
      lastPage: JsonParser.intValue(json['last_page'] ?? 1),
      perPage: JsonParser.intValue(json['per_page'] ?? 15),
      total: JsonParser.intValue(json['total'] ?? 0),

      stores: JsonParser.list<Store>(
        json['data'],
            (v) => Store.fromJson(v as Map<String, dynamic>),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['current_page'] = currentPage;
    data['last_page'] = lastPage;
    data['per_page'] = perPage;
    data['total'] = total;
    if (stores != null) {
      data['data'] = stores!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class Store {
  int id;
  String name;
  String slug;
  int? productCount;
  String? description;
  String? contactNumber;
  String? contactEmail;
  int? sellerId;
  String? taxName;
  String? taxNumber;
  String? currencyCode;
  String? promotionalText;
  String? aboutUs;
  String? returnReplacementPolicy;
  String? refundPolicy;
  String? termsAndConditions;
  dynamic metadata;
  String? logo;
  String? banner;
  String? avgProductsRating;
  String? avgStoreRating;
  int? totalStoreFeedback;
  String? createdAt;
  String? updatedAt;
  String? verificationStatus;
  String? visibilityStatus;
  bool isRecommended;
  List<StoreMarket> markets;
  List<Locations> locations;

  Store({
    required this.id,
    required this.name,
    required this.slug,
    this.productCount,
    this.description,
    this.contactNumber,
    this.contactEmail,
    this.sellerId,
    this.taxName,
    this.taxNumber,
    this.currencyCode,
    this.promotionalText,
    this.aboutUs,
    this.returnReplacementPolicy,
    this.refundPolicy,
    this.termsAndConditions,
    this.metadata,
    this.logo,
    this.banner,
    this.avgProductsRating,
    this.avgStoreRating,
    this.totalStoreFeedback,
    this.createdAt,
    this.updatedAt,
    this.verificationStatus,
    this.visibilityStatus,
    this.isRecommended = false,
    this.markets = const [],
    this.locations = const [],
  });

  factory Store.fromJson(Map<String, dynamic> json) {
    return Store(
      id: JsonParser.intValue(json['id']),
      name: JsonParser.string(json['name']),
      slug: JsonParser.string(json['slug']),

      productCount: JsonParser.intValue(json['product_count'] ?? 0),
      description: JsonParser.string(json['description'] ?? ''),
      contactNumber: JsonParser.string(json['contact_number'] ?? ''),
      contactEmail: JsonParser.string(json['contact_email'] ?? ''),
      sellerId: JsonParser.intValue(json['seller_id']),
      taxName: JsonParser.string(json['tax_name'] ?? ''),
      taxNumber: JsonParser.string(json['tax_number'] ?? ''),
      currencyCode: JsonParser.string(json['currency_code'] ?? ''),
      promotionalText: JsonParser.string(json['promotional_text'] ?? ''),
      aboutUs: JsonParser.string(json['about_us'] ?? ''),
      returnReplacementPolicy: JsonParser.string(json['return_replacement_policy'] ?? ''),
      refundPolicy: JsonParser.string(json['refund_policy'] ?? ''),
      termsAndConditions: JsonParser.string(json['terms_and_conditions'] ?? ''),
      metadata: json['metadata'],
      logo: JsonParser.string(json['logo'] ?? ''),
      avgProductsRating: JsonParser.string(json['avg_products_rating'] ?? '0.0'),
      avgStoreRating: JsonParser.string(json['avg_store_rating'] ?? '0.0'),
      totalStoreFeedback: JsonParser.intValue(json['total_store_feedback'] ?? 0),
      createdAt: JsonParser.string(json['created_at'] ?? ''),
      updatedAt: JsonParser.string(json['updated_at'] ?? ''),
      verificationStatus: JsonParser.string(json['verification_status'] ?? ''),
      visibilityStatus: JsonParser.string(json['visibility_status'] ?? ''),
      isRecommended: JsonParser.boolValue(json['is_recommended'] ?? false),
      markets: (json['markets'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(StoreMarket.fromJson)
              .toList() ??
          const [],
      locations: (json['locations'] as List?)
          ?.whereType<Map<String, dynamic>>()
          .map(Locations.fromJson)
          .toList() ??
          const [],
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['name'] = name;
    data['slug'] = slug;
    data['product_count'] = productCount;
    data['description'] = description;
    data['contact_number'] = contactNumber;
    data['contact_email'] = contactEmail;
    data['seller_id'] = sellerId;
    data['tax_name'] = taxName;
    data['tax_number'] = taxNumber;
    data['currency_code'] = currencyCode;
    data['promotional_text'] = promotionalText;
    data['about_us'] = aboutUs;
    data['return_replacement_policy'] = returnReplacementPolicy;
    data['refund_policy'] = refundPolicy;
    data['terms_and_conditions'] = termsAndConditions;
    data['metadata'] = metadata;
    data['logo'] = logo;
    data['banner'] = banner;
    data['avg_products_rating'] = avgProductsRating;
    data['avg_store_rating'] = avgStoreRating;
    data['total_store_feedback'] = totalStoreFeedback;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['verification_status'] = verificationStatus;
    data['visibility_status'] = visibilityStatus;
    data['is_recommended'] = isRecommended;
    data['markets'] = markets.map((m) => m.toJson()).toList();
    data['locations'] = locations.map((m) => m.toJson()).toList();
    return data;
  }
}

/// A market a store sells in (`StoreResource.markets[]`). Currency/format are
/// market-driven, not per-store.
class StoreMarket {
  final int id;
  final String code;
  final String name;

  const StoreMarket({required this.id, required this.code, required this.name});

  factory StoreMarket.fromJson(Map<String, dynamic> json) => StoreMarket(
        id: JsonParser.intValue(json['id']),
        code: JsonParser.string(json['code'] ?? ''),
        name: JsonParser.string(json['name'] ?? ''),
      );

  Map<String, dynamic> toJson() => {'id': id, 'code': code, 'name': name};
}

/// A ship-from location nested under a store by the inventory endpoint
/// (`stores/with-locations`). Full CRUD lives in the store-locations feature.
class StoreLocationBrief {
  final int id;
  final String name;
  final bool isDefault;
  final bool isActive;

  const StoreLocationBrief({
    required this.id,
    required this.name,
    this.isDefault = false,
    this.isActive = true,
  });

  factory StoreLocationBrief.fromJson(Map<String, dynamic> json) => StoreLocationBrief(
        id: JsonParser.intValue(json['id']),
        name: JsonParser.string(json['name'] ?? ''),
        isDefault: JsonParser.boolValue(json['is_default'] ?? false),
        isActive: JsonParser.boolValue(json['is_active'] ?? true),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'is_default': isDefault,
        'is_active': isActive,
      };
}


class Locations {
  int? id;
  int? storeId;
  String? name;
  String? address1;
  String? address2;
  String? city;
  String? state;
  String? countryIso2;
  String? countryName;
  String? pinCode;
  String? latitude;
  String? longitude;
  String? shiprocketPickupLocationId;
  bool? isRegisteredWithShiprocket;
  String? shiprocketRegisteredAt;
  String? shiprocketSyncHash;
  String? shiprocketSyncStatus;
  String? shiprocketSyncError;
  bool? isPhysicalStorefront;
  bool? fulfillsOnlineOrders;
  bool? offersLocalDelivery;
  bool? offersLocalPickup;
  bool? offersPos;
  bool? isActive;
  bool? isDefault;
  String? status;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  Locations(
      {this.id,
        this.storeId,
        this.name,
        this.address1,
        this.address2,
        this.city,
        this.state,
        this.countryIso2,
        this.countryName,
        this.pinCode,
        this.latitude,
        this.longitude,
        this.shiprocketPickupLocationId,
        this.isRegisteredWithShiprocket,
        this.shiprocketRegisteredAt,
        this.shiprocketSyncHash,
        this.shiprocketSyncStatus,
        this.shiprocketSyncError,
        this.isPhysicalStorefront,
        this.fulfillsOnlineOrders,
        this.offersLocalDelivery,
        this.offersLocalPickup,
        this.offersPos,
        this.isActive,
        this.isDefault,
        this.status,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

  Locations.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    storeId = json['store_id'];
    name = json['name'];
    address1 = json['address_1'];
    address2 = json['address_2'];
    city = json['city'];
    state = json['state'];
    countryIso2 = json['country_iso2'];
    countryName = json['country_name'];
    pinCode = json['pin_code'];
    latitude = json['latitude'];
    longitude = json['longitude'];
    shiprocketPickupLocationId = json['shiprocket_pickup_location_id'];
    isRegisteredWithShiprocket = json['is_registered_with_shiprocket'];
    shiprocketRegisteredAt = json['shiprocket_registered_at'];
    shiprocketSyncHash = json['shiprocket_sync_hash'];
    shiprocketSyncStatus = json['shiprocket_sync_status'];
    shiprocketSyncError = json['shiprocket_sync_error'];
    isPhysicalStorefront = json['is_physical_storefront'];
    fulfillsOnlineOrders = json['fulfills_online_orders'];
    offersLocalDelivery = json['offers_local_delivery'];
    offersLocalPickup = json['offers_local_pickup'];
    offersPos = json['offers_pos'];
    isActive = json['is_active'];
    isDefault = json['is_default'];
    status = json['status'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['store_id'] = storeId;
    data['name'] = name;
    data['address_1'] = address1;
    data['address_2'] = address2;
    data['city'] = city;
    data['state'] = state;
    data['country_iso2'] = countryIso2;
    data['country_name'] = countryName;
    data['pin_code'] = pinCode;
    data['latitude'] = latitude;
    data['longitude'] = longitude;
    data['shiprocket_pickup_location_id'] = shiprocketPickupLocationId;
    data['is_registered_with_shiprocket'] = isRegisteredWithShiprocket;
    data['shiprocket_registered_at'] = shiprocketRegisteredAt;
    data['shiprocket_sync_hash'] = shiprocketSyncHash;
    data['shiprocket_sync_status'] = shiprocketSyncStatus;
    data['shiprocket_sync_error'] = shiprocketSyncError;
    data['is_physical_storefront'] = isPhysicalStorefront;
    data['fulfills_online_orders'] = fulfillsOnlineOrders;
    data['offers_local_delivery'] = offersLocalDelivery;
    data['offers_local_pickup'] = offersLocalPickup;
    data['offers_pos'] = offersPos;
    data['is_active'] = isActive;
    data['is_default'] = isDefault;
    data['status'] = status;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}