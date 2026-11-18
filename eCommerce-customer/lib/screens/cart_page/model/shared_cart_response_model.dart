import '../../../config/helper.dart';

class SharedCartResponseModel {
  final bool? success;
  final String? message;
  final SharedCartData? data;

  SharedCartResponseModel({
    this.success,
    this.message,
    this.data,
  });

  factory SharedCartResponseModel.fromJson(Map<String, dynamic> json) {
    return SharedCartResponseModel(
      success: parseBool(json['success']),
      message: parseString(json['message']),
      data: json['data'] != null && json['data'] is Map<String, dynamic>
          ? SharedCartData.fromJson(json['data'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'success': success,
        'message': message,
        if (data != null) 'data': data?.toJson(),
      };
}

class SharedCartData {
  final List<SharedCartItem> items;
  final int? itemsCount;
  final String? createdAt;
  final SharedCartStore? store;

  SharedCartData({
    this.items = const [],
    this.itemsCount,
    this.createdAt,
    this.store,
  });

  factory SharedCartData.fromJson(Map<String, dynamic> json) {
    return SharedCartData(
      items: json['items'] != null && json['items'] is Iterable
          ? (json['items'] as Iterable)
              .whereType<Map<String, dynamic>>()
              .map(SharedCartItem.fromJson)
              .toList()
          : const [],
      itemsCount: parseInt(json['items_count']),
      createdAt: parseString(json['created_at']),
      store: json['store'] != null && json['store'] is Map<String, dynamic>
          ? SharedCartStore.fromJson(json['store'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'items': items.map((e) => e.toJson()).toList(),
        'items_count': itemsCount,
        'created_at': createdAt,
        if (store != null) 'store': store?.toJson(),
      };
}

class SharedCartItem {
  final int? productId;
  final String? title;
  final String? slug;
  final String? image;
  final SharedCartVariant? variant;
  final SharedCartStore? store;
  final int? quantity;
  final bool isOutOfStock;

  SharedCartItem({
    this.productId,
    this.title,
    this.slug,
    this.image,
    this.variant,
    this.store,
    this.quantity,
    this.isOutOfStock = false,
  });

  factory SharedCartItem.fromJson(Map<String, dynamic> json) {
    final bool outOfStock = parseBool(json['is_out_of_stock']) ||
        (json.containsKey('in_stock') && !parseBool(json['in_stock'])) ||
        (json['stock_status'] != null &&
            json['stock_status'].toString().toLowerCase().contains('out')) ||
        (json['stock'] != null && parseInt(json['stock']) <= 0);

    return SharedCartItem(
      productId: parseInt(json['product_id']),
      title: parseString(json['title']),
      slug: parseString(json['slug']),
      image: parseString(json['image']),
      variant: json['variant'] != null && json['variant'] is Map<String, dynamic>
          ? SharedCartVariant.fromJson(json['variant'] as Map<String, dynamic>)
          : null,
      store: json['store'] != null && json['store'] is Map<String, dynamic>
          ? SharedCartStore.fromJson(json['store'] as Map<String, dynamic>)
          : null,
      quantity: parseInt(json['quantity']) > 0 ? parseInt(json['quantity']) : 1,
      isOutOfStock: outOfStock,
    );
  }

  Map<String, dynamic> toJson() => {
        'product_id': productId,
        'title': title,
        'slug': slug,
        'image': image,
        if (variant != null) 'variant': variant?.toJson(),
        if (store != null) 'store': store?.toJson(),
        'quantity': quantity,
        'is_out_of_stock': isOutOfStock,
      };
}

class SharedCartVariant {
  final int? id;
  final String? title;
  final String? image;
  final double? price;
  final double? priceBase;
  final double? specialPrice;
  final String? currencyCode;
  final String? currencySymbol;

  SharedCartVariant({
    this.id,
    this.title,
    this.image,
    this.price,
    this.priceBase,
    this.specialPrice,
    this.currencyCode,
    this.currencySymbol,
  });

  factory SharedCartVariant.fromJson(Map<String, dynamic> json) {
    return SharedCartVariant(
      id: parseInt(json['id']),
      title: parseString(json['title']),
      image: parseString(json['image']),
      price: parseDouble(json['price']),
      priceBase: parseDouble(json['price_base']),
      specialPrice: parseDouble(json['special_price']),
      currencyCode: parseString(json['currency_code']),
      currencySymbol: parseString(json['currency_symbol']),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'image': image,
        'price': price,
        'price_base': priceBase,
        'special_price': specialPrice,
        'currency_code': currencyCode,
        'currency_symbol': currencySymbol,
      };
}

class SharedCartStore {
  final int? id;
  final String? name;
  final String? slug;
  final String? image;

  SharedCartStore({
    this.id,
    this.name,
    this.slug,
    this.image,
  });

  factory SharedCartStore.fromJson(Map<String, dynamic> json) {
    return SharedCartStore(
      id: parseInt(json['id']),
      name: parseString(json['name']),
      slug: parseString(json['slug']),
      image: parseString(json['image'] ?? json['logo'] ?? json['avatar']),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'slug': slug,
        if (image != null) 'image': image,
      };
}
