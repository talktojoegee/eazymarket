class EligibleProductModel {
  final int id;
  final int? productId;
  final String? productSlug;
  final String productTitle;
  final String? productImage;
  final int? storeId;
  final String? storeSlug;
  final String? storeName;
  final int? variantId;
  final String? variantTitle;
  final double price;
  final double? specialPrice;
  final String? currencyCode;
  final String? currencySymbol;
  final bool available;
  final bool isOutOfStock;
  final int sortOrder;
  final bool isPrimary;

  EligibleProductModel({
    required this.id,
    this.productId,
    this.productSlug,
    required this.productTitle,
    this.productImage,
    this.storeId,
    this.storeSlug,
    this.storeName,
    this.variantId,
    this.variantTitle,
    this.price = 0.0,
    this.specialPrice,
    this.currencyCode,
    this.currencySymbol,
    this.available = true,
    this.isOutOfStock = false,
    this.sortOrder = 0,
    this.isPrimary = false,
  });

  String get formattedPrice {
    final symbol = currencySymbol ?? '\$';
    if (specialPrice != null && specialPrice! > 0 && specialPrice! < price) {
      return '$symbol${specialPrice!.toStringAsFixed(2)}';
    }
    return '$symbol${price.toStringAsFixed(2)}';
  }

  String? get formattedOriginalPrice {
    if (specialPrice != null && specialPrice! > 0 && specialPrice! < price) {
      final symbol = currencySymbol ?? '\$';
      return '$symbol${price.toStringAsFixed(2)}';
    }
    return null;
  }

  /// Unique key to identify products/variants and avoid collision in selection maps
  String get uniqueKey {
    if (variantId != null && variantId! > 0) {
      return 'v_${variantId}_p_${productId ?? id}';
    }
    if (productId != null && productId! > 0) {
      return 'p_$productId';
    }
    return 'id_$id';
  }

  /// Attachment ID to send to the backend API
  int get attachmentId {
    if (variantId != null && variantId! > 0) return variantId!;
    if (productId != null && productId! > 0) return productId!;
    if (id > 0) return id;
    return 0;
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is EligibleProductModel &&
          runtimeType == other.runtimeType &&
          uniqueKey == other.uniqueKey;

  @override
  int get hashCode => uniqueKey.hashCode;

  factory EligibleProductModel.fromJson(Map<String, dynamic> json) {
    final rawId = json['attachment_id'] ?? json['id'] ?? json['product_id'] ?? json['variant_id'];
    final parsedId = rawId is int ? rawId : int.tryParse(rawId?.toString() ?? '0') ?? 0;

    final rawPrice = json['price'];
    final double parsedPrice = rawPrice is num ? rawPrice.toDouble() : double.tryParse(rawPrice?.toString() ?? '0') ?? 0.0;

    final rawSpecialPrice = json['special_price'];
    final double? parsedSpecialPrice = rawSpecialPrice is num ? rawSpecialPrice.toDouble() : double.tryParse(rawSpecialPrice?.toString() ?? '');

    final bool isAvail = json['available'] == null ? true : (json['available'] == true || json['available'] == 1);
    final bool isOut = json['is_out_of_stock'] == true || json['is_out_of_stock'] == 1 || !isAvail;

    return EligibleProductModel(
      id: parsedId,
      productId: json['product_id'] is int ? json['product_id'] : int.tryParse(json['product_id']?.toString() ?? ''),
      productSlug: json['product_slug']?.toString() ?? json['slug']?.toString(),
      productTitle: json['title']?.toString() ?? json['product_title']?.toString() ?? '',
      productImage: json['image']?.toString() ?? json['product_image']?.toString(),
      storeId: json['store_id'] is int ? json['store_id'] : int.tryParse(json['store_id']?.toString() ?? ''),
      storeSlug: json['store_slug']?.toString(),
      storeName: json['store_name']?.toString(),
      variantId: json['variant_id'] is int ? json['variant_id'] : int.tryParse(json['variant_id']?.toString() ?? ''),
      variantTitle: json['variant_title']?.toString(),
      price: parsedPrice,
      specialPrice: parsedSpecialPrice,
      currencyCode: json['currency_code']?.toString(),
      currencySymbol: json['currency_symbol']?.toString(),
      available: isAvail,
      isOutOfStock: isOut,
      sortOrder: json['sort_order'] is int ? json['sort_order'] : int.tryParse(json['sort_order']?.toString() ?? '0') ?? 0,
      isPrimary: json['is_primary'] == true || json['is_primary'] == 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'product_id': productId,
      'product_slug': productSlug,
      'title': productTitle,
      'image': productImage,
      'store_id': storeId,
      'store_slug': storeSlug,
      'store_name': storeName,
      'variant_id': variantId,
      'variant_title': variantTitle,
      'price': price,
      'special_price': specialPrice,
      'currency_code': currencyCode,
      'currency_symbol': currencySymbol,
      'available': available,
      'is_out_of_stock': isOutOfStock,
      'sort_order': sortOrder,
      'is_primary': isPrimary,
    };
  }

  EligibleProductModel copyWith({
    int? id,
    int? productId,
    String? productSlug,
    String? productTitle,
    String? productImage,
    int? storeId,
    String? storeSlug,
    String? storeName,
    int? variantId,
    String? variantTitle,
    double? price,
    double? specialPrice,
    String? currencyCode,
    String? currencySymbol,
    bool? available,
    bool? isOutOfStock,
    int? sortOrder,
    bool? isPrimary,
  }) {
    return EligibleProductModel(
      id: id ?? this.id,
      productId: productId ?? this.productId,
      productSlug: productSlug ?? this.productSlug,
      productTitle: productTitle ?? this.productTitle,
      productImage: productImage ?? this.productImage,
      storeId: storeId ?? this.storeId,
      storeSlug: storeSlug ?? this.storeSlug,
      storeName: storeName ?? this.storeName,
      variantId: variantId ?? this.variantId,
      variantTitle: variantTitle ?? this.variantTitle,
      price: price ?? this.price,
      specialPrice: specialPrice ?? this.specialPrice,
      currencyCode: currencyCode ?? this.currencyCode,
      currencySymbol: currencySymbol ?? this.currencySymbol,
      available: available ?? this.available,
      isOutOfStock: isOutOfStock ?? this.isOutOfStock,
      sortOrder: sortOrder ?? this.sortOrder,
      isPrimary: isPrimary ?? this.isPrimary,
    );
  }
}

class CursorPaginationModel {
  final int perPage;
  final String? nextCursor;
  final String? previousCursor;
  final bool hasMorePages;
  final int? total;

  CursorPaginationModel({
    this.perPage = 15,
    this.nextCursor,
    this.previousCursor,
    this.hasMorePages = false,
    this.total,
  });

  factory CursorPaginationModel.fromJson(Map<String, dynamic> json) {
    return CursorPaginationModel(
      perPage: json['per_page'] is int ? json['per_page'] : int.tryParse(json['per_page']?.toString() ?? '15') ?? 15,
      nextCursor: json['next_cursor']?.toString(),
      previousCursor: json['previous_cursor']?.toString(),
      hasMorePages: json['has_more_pages'] == true || (json['next_cursor'] != null && json['next_cursor'].toString().isNotEmpty),
      total: json['total'] is int ? json['total'] : int.tryParse(json['total']?.toString() ?? ''),
    );
  }
}

class EligibleProductsResponse {
  final List<EligibleProductModel> items;
  final CursorPaginationModel pagination;

  EligibleProductsResponse({
    required this.items,
    required this.pagination,
  });

  factory EligibleProductsResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic> ? json['data'] : json;
    final itemsList = data['items'] as List<dynamic>? ?? [];
    final paginationMap = data['pagination'] as Map<String, dynamic>? ?? {};

    return EligibleProductsResponse(
      items: itemsList.map((e) => EligibleProductModel.fromJson(e as Map<String, dynamic>)).toList(),
      pagination: CursorPaginationModel.fromJson(paginationMap),
    );
  }
}
