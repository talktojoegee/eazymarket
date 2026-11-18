import 'package:hyper_commerce_seller/service/json_parser.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/model/tax_breakdown_model.dart';

const String modelName = 'product_model';

class ProductsResponse {
  bool? success;
  String? message;
  ProductsData? data;

  ProductsResponse({this.success, this.message, this.data});

  factory ProductsResponse.fromJson(Map<String, dynamic> json) {
    return ProductsResponse(
      success: JsonParser.boolValue(json['success'] ?? false),
      message: JsonParser.string(json['message'] ?? ''),
      data: json['data'] != null
          ? ProductsData.fromJson(json['data'] as Map<String, dynamic>)
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

class ProductsData {
  int? currentPage;
  int? lastPage;
  int? perPage;
  int? total;
  List<Product>? products;

  ProductsData({
    this.currentPage,
    this.lastPage,
    this.perPage,
    this.total,
    this.products,
  });

  factory ProductsData.fromJson(Map<String, dynamic> json) {
    return ProductsData(
      currentPage: JsonParser.intValue(json['current_page'] ?? 1),
      lastPage: JsonParser.intValue(json['last_page'] ?? 1),
      perPage: JsonParser.intValue(json['per_page'] ?? 15),
      total: JsonParser.intValue(json['total'] ?? 0),

      products: JsonParser.list<Product>(
        json['data'],
        (v) => Product.fromJson(v as Map<String, dynamic>),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['current_page'] = currentPage;
    data['last_page'] = lastPage;
    data['per_page'] = perPage;
    data['total'] = total;
    if (products != null) {
      data['data'] = products!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class Product {
  int id;
  String? uuid;
  int? categoryId;
  int? categoryParentId;
  CategoryHierarchyNode? categoryHierarchyKey;
  int? brandId;
  int? sellerId;
  String title;
  String slug;
  String? type;
  String? shortDescription;
  String? categorySlug;
  String? brandSlug;
  String? categoryName;
  String? brandName;
  String? sellerName;
  String? indicator;
  bool? isFavorite;
  String? estimatedDeliveryTime;
  int ratings;
  int ratingCount;
  String mainImage;
  String? imageFit;
  String? itemCountInCart;
  List<String> additionalImages;
  int? minimumOrderQuantity;
  int? quantityStepSize;
  int? totalAllowedQuantity;
  bool isReturnable;
  List<String> tags;
  String? warrantyPeriod;
  String? guaranteePeriod;
  String? madeIn;
  List<TaxClasses>? taxClasses;
  bool? isInclusiveTax;
  String? videoType;
  String? videoLink;
  String status;
  String? featured;
  String? description;
  String? hsnCode;
  int? prepTime;
  int? returnableDays;
  bool? isCancelable;
  String? cancelableTill;
  bool? isCodAllowed;
  bool? isAttachmentRequired;
  String? attachmentMode;
  bool? requiresOtp;
  String? productVideo;
  List<int> taxGroups;
  dynamic metadata;
  String? createdAt;
  String? updatedAt;
  List<Variant>? variants;
  List<ProductAttribute>? productAttributes;
  Map<String, dynamic>? customFields;
  List<CustomProductSection>? customProductSections;
  SellerRatings? sellerRatings;
  List<TaxBreakdown>? taxBreakdown;

  Product({
    required this.id,
    this.uuid,
    this.categoryId,
    this.categoryParentId,
    this.categoryHierarchyKey,
    this.brandId,
    this.sellerId,
    required this.title,
    required this.slug,
    this.type,
    this.shortDescription,
    this.categorySlug,
    this.brandSlug,
    this.categoryName,
    this.brandName,
    this.sellerName,
    this.indicator,
    this.isFavorite,
    this.estimatedDeliveryTime,
    this.ratings = 0,
    this.ratingCount = 0,
    required this.mainImage,
    this.imageFit,
    this.itemCountInCart,
    this.additionalImages = const [],
    this.minimumOrderQuantity,
    this.quantityStepSize,
    this.totalAllowedQuantity,
    this.isReturnable = false,
    this.tags = const [],
    this.warrantyPeriod,
    this.guaranteePeriod,
    this.madeIn,
    this.taxClasses,
    this.isInclusiveTax,
    this.videoType,
    this.videoLink,
    this.status = 'active',
    this.featured,
    this.description,
    this.hsnCode,
    this.prepTime,
    this.returnableDays,
    this.isCancelable,
    this.cancelableTill,
    this.isCodAllowed = true,
    this.isAttachmentRequired,
    this.attachmentMode,
    this.requiresOtp,
    this.productVideo,
    this.taxGroups = const [],
    this.metadata,
    this.createdAt,
    this.updatedAt,
    this.variants,
    this.productAttributes,
    this.customFields,
    this.customProductSections,
    this.sellerRatings,
    this.taxBreakdown,
  });

  /// SEO block the panel nests under `metadata`.
  ProductSeo? get seo => metadata is Map<String, dynamic>
      ? ProductSeo.fromJson(metadata as Map<String, dynamic>)
      : null;

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: JsonParser.requireInt(json['id'], model: modelName, field: 'id'),
      uuid: JsonParser.string(json['uuid']),
      categoryId: JsonParser.intValue(json['category_id']),
      categoryParentId: json['category_parent_id'] == null
          ? null
          : JsonParser.intValue(json['category_parent_id']),
      brandId: JsonParser.intValue(json['brand_id']),
      sellerId: JsonParser.intValue(json['seller_id']),
      categoryHierarchyKey:
          json['category_hierarchy_key'] is Map<String, dynamic>
          ? CategoryHierarchyNode.fromJson(
              json['category_hierarchy_key'] as Map<String, dynamic>,
            )
          : null,

      title: JsonParser.requireString(
        json['title'],
        model: modelName,
        field: 'title',
      ),
      slug: JsonParser.requireString(
        json['slug'],
        model: modelName,
        field: 'slug',
      ),

      type: JsonParser.string(json['type'] ?? 'simple'),
      shortDescription: JsonParser.string(json['short_description'] ?? ''),

      categorySlug: JsonParser.string(json['category']),
      brandSlug: JsonParser.string(json['brand']),
      categoryName: JsonParser.string(json['category_name']),
      brandName: JsonParser.string(json['brand_name']),
      sellerName: JsonParser.string(json['seller']),

      indicator: JsonParser.string(json['indicator']),
      isFavorite: JsonParser.boolValue(json['favorite']),

      estimatedDeliveryTime: JsonParser.string(json['estimated_delivery_time']),

      ratings: JsonParser.intValue(json['ratings'] ?? 0),
      ratingCount: JsonParser.intValue(json['rating_count'] ?? 0),

      mainImage: JsonParser.string(json['main_image']),
      imageFit: JsonParser.string(json['image_fit'] ?? 'cover'),

      itemCountInCart: JsonParser.string(json['item_count_in_cart'] ?? '0'),

      additionalImages: JsonParser.list<String>(
        json['additional_images'],
        (v) => JsonParser.string(v),
      ),

      minimumOrderQuantity: JsonParser.intValue(json['minimum_order_quantity']),
      quantityStepSize: JsonParser.intValue(json['quantity_step_size']),
      totalAllowedQuantity: JsonParser.intValue(json['total_allowed_quantity']),

      isReturnable: JsonParser.boolValue(json['is_returnable'] ?? false),

      tags: JsonParser.list<String>(json['tags'], (v) => JsonParser.string(v)),

      warrantyPeriod: JsonParser.string(json['warranty_period']),
      guaranteePeriod: JsonParser.string(json['guarantee_period']),
      madeIn: JsonParser.string(json['made_in']),
      taxClasses: JsonParser.list<TaxClasses>(
        json['tax_classes'],
            (v) => TaxClasses.fromJson(v as Map<String, dynamic>),
      ),
      isInclusiveTax: JsonParser.boolValue(json['is_inclusive_tax'] ?? false),

      videoType: JsonParser.string(json['video_type']),
      videoLink: JsonParser.string(json['video_link']),

      status: JsonParser.string(json['status'] ?? 'active'),
      featured: JsonParser.string(json['featured'] ?? '0'),
      description: JsonParser.string(json['description']),
      hsnCode: JsonParser.string(json['hsn_code']),
      prepTime: JsonParser.intValue(json['base_prep_time']),
      returnableDays: JsonParser.intValue(json['returnable_days']),
      isCancelable: JsonParser.boolValue(json['is_cancelable']),
      cancelableTill: JsonParser.string(json['cancelable_till']),
      // The panel sends `allow_cod`; the other two are legacy spellings.
      isCodAllowed: JsonParser.boolValue(
        json['allow_cod'] ?? json['is_cod_allowed'] ?? json['cod_allowed'] ?? true,
        fallback: true,
      ),
      isAttachmentRequired: JsonParser.boolValue(
        json['is_attachment_required'],
      ),
      attachmentMode: JsonParser.string(json['attachment_mode']),
      requiresOtp: JsonParser.boolValue(json['requires_otp']),
      productVideo: JsonParser.string(json['product_video']),
      taxGroups: JsonParser.list<int>(
        json['tax_groups'] ?? json['tax_classes'],
        (v) => v is Map ? JsonParser.intValue(v['id']) : JsonParser.intValue(v),
      ),
      metadata: json['metadata'],

      createdAt: JsonParser.string(json['created_at'] ?? ''),
      updatedAt: JsonParser.string(json['updated_at'] ?? ''),

      variants: JsonParser.list<Variant>(
        json['variants'],
        (v) => Variant.fromJson(v as Map<String, dynamic>),
      ),

      productAttributes: JsonParser.list<ProductAttribute>(
        json['attributes'],
        (v) => ProductAttribute.fromJson(v as Map<String, dynamic>),
      ),
      customFields: json['custom_fields'] is Map<String, dynamic>
          ? json['custom_fields'] as Map<String, dynamic>
          : null,
      customProductSections: JsonParser.list<CustomProductSection>(
        json['custom_product_sections'],
        (v) => CustomProductSection.fromJson(v as Map<String, dynamic>),
      ),
      sellerRatings: json['seller_ratings'] is Map<String, dynamic>
          ? SellerRatings.fromJson(json['seller_ratings'] as Map<String, dynamic>)
          : null,
      taxBreakdown: JsonParser.list<TaxBreakdown>(
        json['tax_breakdown'],
        (v) => TaxBreakdown.fromJson(v as Map<String, dynamic>),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['uuid'] = uuid;
    data['category_id'] = categoryId;
    data['category_parent_id'] = categoryParentId;
    data['brand_id'] = brandId;
    data['seller_id'] = sellerId;
    data['title'] = title;
    data['slug'] = slug;
    data['type'] = type;
    data['short_description'] = shortDescription;
    data['category'] = categorySlug;
    data['brand'] = brandSlug;
    data['category_name'] = categoryName;
    data['brand_name'] = brandName;
    data['seller'] = sellerName;
    data['indicator'] = indicator;
    data['favorite'] = isFavorite;
    data['estimated_delivery_time'] = estimatedDeliveryTime;
    data['ratings'] = ratings;
    data['rating_count'] = ratingCount;
    data['main_image'] = mainImage;
    data['image_fit'] = imageFit;
    data['item_count_in_cart'] = itemCountInCart;
    data['additional_images'] = additionalImages;
    data['minimum_order_quantity'] = minimumOrderQuantity;
    data['quantity_step_size'] = quantityStepSize;
    data['total_allowed_quantity'] = totalAllowedQuantity;
    data['is_returnable'] = isReturnable;
    data['tags'] = tags;
    data['warranty_period'] = warrantyPeriod;
    data['guarantee_period'] = guaranteePeriod;
    data['made_in'] = madeIn;
    data['is_inclusive_tax'] = isInclusiveTax;
    data['video_type'] = videoType;
    data['video_link'] = videoLink;
    data['status'] = status;
    data['featured'] = featured;
    data['description'] = description;
    data['hsn_code'] = hsnCode;
    data['base_prep_time'] = prepTime;
    data['returnable_days'] = returnableDays;
    data['is_cancelable'] = isCancelable;
    data['cancelable_till'] = cancelableTill;
    data['allow_cod'] = isCodAllowed;
    data['is_attachment_required'] = isAttachmentRequired;
    data['attachment_mode'] = attachmentMode;
    data['requires_otp'] = requiresOtp;
    data['product_video'] = productVideo;
    data['tax_groups'] = taxGroups;
    data['metadata'] = metadata;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    if (taxBreakdown != null) {
      data['tax_breakdown'] = taxBreakdown!.map((v) => v.toJson()).toList();
    }
    if (variants != null) {
      data['variants'] = variants!.map((v) => v.toJson()).toList();
    }
    if (productAttributes != null) {
      data['attributes'] = productAttributes!.map((a) => a.toJson()).toList();
    }
    if (customProductSections != null) {
      data['custom_product_sections'] = customProductSections!
          .map((s) => s.toJson())
          .toList();
    }
    if (sellerRatings != null) {
      data['seller_ratings'] = sellerRatings!.toJson();
    }
    return data;
  }
}

class TaxClasses {
  int? id;
  int? marketId;
  String? title;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  Pivot? pivot;
  List<TaxRates>? taxRates;

  TaxClasses(
      {this.id,
        this.marketId,
        this.title,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.pivot,
        this.taxRates});

  TaxClasses.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    marketId = json['market_id'];
    title = json['title'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    pivot = json['pivot'] != null ? Pivot.fromJson(json['pivot']) : null;
    if (json['tax_rates'] != null) {
      taxRates = <TaxRates>[];
      json['tax_rates'].forEach((v) {
        taxRates!.add(TaxRates.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['market_id'] = marketId;
    data['title'] = title;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    if (pivot != null) {
      data['pivot'] = pivot!.toJson();
    }
    if (taxRates != null) {
      data['tax_rates'] = taxRates!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class Pivot {
  int? productId;
  int? taxClassId;

  Pivot({this.productId, this.taxClassId});

  Pivot.fromJson(Map<String, dynamic> json) {
    productId = json['product_id'];
    taxClassId = json['tax_class_id'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['product_id'] = productId;
    data['tax_class_id'] = taxClassId;
    return data;
  }
}

class TaxRates {
  int? id;
  String? title;
  String? rate;
  String? createdAt;
  String? updatedAt;
  Pivot? pivot;

  TaxRates(
      {this.id,
        this.title,
        this.rate,
        this.createdAt,
        this.updatedAt,
        this.pivot});

  TaxRates.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    title = json['title'];
    rate = json['rate'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    pivot = json['pivot'] != null ? Pivot.fromJson(json['pivot']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['title'] = title;
    data['rate'] = rate;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    if (pivot != null) {
      data['pivot'] = pivot!.toJson();
    }
    return data;
  }
}

class SellerRatings {
  final int totalReviews;
  final double averageRating;
  final int oneStar;
  final int twoStar;
  final int threeStar;
  final int fourStar;
  final int fiveStar;

  const SellerRatings({
    this.totalReviews = 0,
    this.averageRating = 0,
    this.oneStar = 0,
    this.twoStar = 0,
    this.threeStar = 0,
    this.fourStar = 0,
    this.fiveStar = 0,
  });

  factory SellerRatings.fromJson(Map<String, dynamic> json) {
    return SellerRatings(
      totalReviews: JsonParser.intValue(json['total_reviews']),
      // Sent as a string, e.g. "5.0000".
      averageRating: JsonParser.doubleValue(json['average_rating']),
      oneStar: JsonParser.intValue(json['one_star_count']),
      twoStar: JsonParser.intValue(json['two_star_count']),
      threeStar: JsonParser.intValue(json['three_star_count']),
      fourStar: JsonParser.intValue(json['four_star_count']),
      fiveStar: JsonParser.intValue(json['five_star_count']),
    );
  }

  Map<String, dynamic> toJson() => {
        'total_reviews': totalReviews,
        'average_rating': averageRating,
        'one_star_count': oneStar,
        'two_star_count': twoStar,
        'three_star_count': threeStar,
        'four_star_count': fourStar,
        'five_star_count': fiveStar,
      };
}

/// SEO fields nested under the product's `metadata`.
class ProductSeo {
  final String? title;
  final String? description;
  final List<String> keywords;

  const ProductSeo({this.title, this.description, this.keywords = const []});

  factory ProductSeo.fromJson(Map<String, dynamic> json) => ProductSeo(
        title: JsonParser.string(json['seo_title']),
        description: JsonParser.string(json['seo_description']),
        keywords: JsonParser.list<String>(
          json['seo_keywords'],
          (v) => JsonParser.string(v),
        ),
      );

  bool get isEmpty =>
      (title?.isEmpty ?? true) &&
      (description?.isEmpty ?? true) &&
      keywords.isEmpty;

  Map<String, dynamic> toJson() => {
        'seo_title': title,
        'seo_description': description,
        'seo_keywords': keywords,
      };
}

class CategoryHierarchyNode {
  int id;
  CategoryHierarchyNode? child;

  CategoryHierarchyNode({required this.id, this.child});

  factory CategoryHierarchyNode.fromJson(Map<String, dynamic> json) {
    return CategoryHierarchyNode(
      id: JsonParser.requireInt(
        json['id'],
        model: 'category_hierarchy_node',
        field: 'id',
      ),
      child: json['child'] != null
          ? CategoryHierarchyNode.fromJson(
              json['child'] as Map<String, dynamic>,
            )
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    if (child != null) {
      data['child'] = child!.toJson();
    }
    return data;
  }
}

class CustomProductSection {
  int? id;
  String? uuid;
  String title;
  String? description;
  int sortOrder;
  List<CustomProductField> fields;

  CustomProductSection({
    this.id,
    this.uuid,
    required this.title,
    this.description,
    this.sortOrder = 0,
    this.fields = const [],
  });

  factory CustomProductSection.fromJson(Map<String, dynamic> json) {
    return CustomProductSection(
      id: JsonParser.intValue(json['id']),
      uuid: JsonParser.string(json['uuid']),
      title: JsonParser.string(json['title'] ?? ''),
      description: JsonParser.string(json['description']),
      sortOrder: JsonParser.intValue(json['sort_order'] ?? 0),
      fields: JsonParser.list<CustomProductField>(
        json['fields'],
        (v) => CustomProductField.fromJson(v as Map<String, dynamic>),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'uuid': uuid,
      'title': title,
      'description': description,
      'sort_order': sortOrder,
      'fields': fields.map((f) => f.toJson()).toList(),
    };
  }
}

class CustomProductField {
  int? id;
  String? uuid;
  String title;
  String? description;
  String? image;
  int sortOrder;

  CustomProductField({
    this.id,
    this.uuid,
    required this.title,
    this.description,
    this.image,
    this.sortOrder = 0,
  });

  factory CustomProductField.fromJson(Map<String, dynamic> json) {
    return CustomProductField(
      id: JsonParser.intValue(json['id']),
      uuid: JsonParser.string(json['uuid']),
      title: JsonParser.string(json['title'] ?? ''),
      description: JsonParser.string(json['description']),
      image: JsonParser.string(json['image']),
      sortOrder: JsonParser.intValue(json['sort_order'] ?? 0),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'uuid': uuid,
      'title': title,
      'description': description,
      'image': image,
      'sort_order': sortOrder,
    };
  }
}


class Variant {
  int? id;
  String? title;
  String? slug;
  String? image;
  double? weight;
  double? height;
  double? breadth;
  double? length;
  bool? availability;
  CartItem? cartItem;
  String? barcode;
  bool? isDefault;
  int? price;
  int? specialPrice;
  int? storeId;
  String? storeSlug;
  String? storeName;
  int? stock;
  String? sku;
  List<ProductAttribute>? variantAttributes;
  Map<String, String>? attributesMap;
  List<VariantStore>? stores; // Added this
  List<VariantInventory>? inventory;
  List<TaxBreakdown>? taxBreakdown;

  Variant({
    this.id,
    this.title,
    this.slug,
    this.image,
    this.weight,
    this.height,
    this.breadth,
    this.length,
    this.availability,
    this.cartItem,
    this.barcode,
    this.isDefault,
    this.price,
    this.specialPrice,
    this.storeId,
    this.storeSlug,
    this.storeName,
    this.stock,
    this.sku,
    this.variantAttributes,
    this.attributesMap,
    this.stores,
    this.inventory,
    this.taxBreakdown,
  });

  /// Markets model: variant-level price/stock are null — commerce data lives
  /// per store in [stores]. These read the (store-scoped) store row first,
  /// falling back to any legacy variant-level value.
  VariantStore? get _primaryStore =>
      (stores != null && stores!.isNotEmpty) ? stores!.first : null;

  double get displayPrice => _primaryStore?.price ?? price?.toDouble() ?? 0;

  double? get displaySpecialPrice => _primaryStore?.specialPrice ?? specialPrice?.toDouble();

  int get displayStock => _primaryStore?.stock ?? stock ?? 0;

  factory Variant.fromJson(Map<String, dynamic> json) {
    return Variant(
      id: JsonParser.intValue(json['id']),
      title: JsonParser.string(json['title']),
      slug: JsonParser.string(json['slug']),
      image: JsonParser.string(json['image'] ?? ''),
      weight: JsonParser.doubleValue(json['weight']),
      height: JsonParser.doubleValue(json['height']),
      breadth: JsonParser.doubleValue(json['breadth']),
      length: JsonParser.doubleValue(json['length']),
      availability: JsonParser.boolValue(json['availability'] ?? true),
      cartItem: json['cart_item'] is Map<String, dynamic>
          ? CartItem.fromJson(json['cart_item'] as Map<String, dynamic>)
          : null,
      barcode: JsonParser.string(json['barcode']),
      isDefault: JsonParser.boolValue(json['is_default'] ?? false),
      price: JsonParser.intValue(json['price']),
      specialPrice: JsonParser.intValue(json['special_price']),
      storeId: JsonParser.intValue(json['store_id']),
      storeSlug: JsonParser.string(json['store_slug']),
      storeName: JsonParser.string(json['store_name']),
      stock: JsonParser.intValue(json['stock']),
      sku: JsonParser.string(json['sku']),

      variantAttributes: JsonParser.list<ProductAttribute>(
        json['attributes'],
        (v) => ProductAttribute.fromJson(v as Map<String, dynamic>),
      ),
      attributesMap: json['attributes'] is Map<String, dynamic>
          ? (json['attributes'] as Map<String, dynamic>).map(
              (key, value) => MapEntry(key, value.toString()),
            )
          : null,
      stores: JsonParser.list<VariantStore>(
        json['stores'],
        (v) => VariantStore.fromJson(v as Map<String, dynamic>),
      ),
      inventory: VariantInventory.listFrom(json),
      taxBreakdown: JsonParser.list<TaxBreakdown>(
        json['tax_breakdown'],
        (v) => TaxBreakdown.fromJson(v as Map<String, dynamic>),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['title'] = title;
    data['slug'] = slug;
    data['image'] = image;
    data['weight'] = weight;
    data['height'] = height;
    data['breadth'] = breadth;
    data['length'] = length;
    data['availability'] = availability;
    if (cartItem != null) data['cart_item'] = cartItem!.toJson();
    data['barcode'] = barcode;
    data['is_default'] = isDefault;
    data['price'] = price;
    data['special_price'] = specialPrice;
    data['store_id'] = storeId;
    data['store_slug'] = storeSlug;
    data['store_name'] = storeName;
    data['stock'] = stock;
    data['sku'] = sku;
    if (variantAttributes != null) {
      data['attributes'] = variantAttributes!.map((a) => a.toJson()).toList();
    }
    if (stores != null) {
      data['stores'] = stores!.map((s) => s.toJson()).toList();
    }
    if (inventory != null) {
      data['inventory'] = inventory!.map((i) => i.toJson()).toList();
    }
    if (taxBreakdown != null) {
      data['tax_breakdown'] = taxBreakdown!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class VariantStore {
  int? id;
  int? storeId;
  String? storeSlug;
  String? storeName;
  String? sku;
  double? price;
  double? specialPrice;
  double? cost;
  int? stock;
  List<VariantInventory>? inventory;

  VariantStore({
    this.id,
    this.storeId,
    this.storeSlug,
    this.storeName,
    this.sku,
    this.price,
    this.specialPrice,
    this.cost,
    this.stock,
    this.inventory,
  });

  factory VariantStore.fromJson(Map<String, dynamic> json) {
    return VariantStore(
      id: JsonParser.intValue(json['id']),
      storeId: JsonParser.intValue(json['store_id']),
      storeSlug: JsonParser.string(json['store_slug']),
      storeName: JsonParser.string(json['store_name']),
      sku: JsonParser.string(json['sku']),
      price: JsonParser.doubleValue(json['price']),
      specialPrice: JsonParser.doubleValue(json['special_price']),
      cost: JsonParser.doubleValue(json['cost']),
      stock: JsonParser.intValue(json['stock']),
      inventory: VariantInventory.listFrom(json),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'store_id': storeId,
      'store_slug': storeSlug,
      'store_name': storeName,
      'sku': sku,
      'price': price,
      'special_price': specialPrice,
      'cost': cost,
      'stock': stock,
      if (inventory != null)
        'inventory': inventory!.map((i) => i.toJson()).toList(),
    };
  }
}

/// Per ship-from-location stock row returned with a variant (or a variant's
/// store row). Key names vary across payload versions, so every field is read
/// from the aliases the panel has used.
class VariantInventory {
  final int? storeId;
  final String? storeName;
  final int locationId;
  final String? locationName;
  final bool isDefault;
  final bool sellFrom;
  final bool tracked;
  final int qty;

  const VariantInventory({
    this.storeId,
    this.storeName,
    required this.locationId,
    this.locationName,
    this.isDefault = false,
    this.sellFrom = true,
    this.tracked = true,
    this.qty = 0,
  });

  /// Reads the inventory array off [json] regardless of which key holds it.
  static List<VariantInventory>? listFrom(Map<String, dynamic> json) {
    final raw = json['inventory'] ?? json['inventories'] ?? json['locations'];
    if (raw is! List) return null;
    return raw
        .whereType<Map<String, dynamic>>()
        .map(VariantInventory.fromJson)
        .toList();
  }

  factory VariantInventory.fromJson(Map<String, dynamic> json) {
    final location = json['location'] is Map<String, dynamic>
        ? json['location'] as Map<String, dynamic>
        : const <String, dynamic>{};

    final rawStoreId = json['store_id'] ?? location['store_id'];

    return VariantInventory(
      storeId: rawStoreId == null ? null : JsonParser.intValue(rawStoreId),
      storeName: JsonParser.string(json['store_name'] ?? location['store_name']),
      locationId: JsonParser.intValue(
        json['store_location_id'] ??
            json['location_id'] ??
            location['id'] ??
            json['id'],
      ),
      locationName: JsonParser.string(
        json['location_name'] ?? json['name'] ?? location['name'],
      ),
      isDefault: JsonParser.boolValue(
        json['is_default'] ?? location['is_default'],
      ),
      sellFrom: JsonParser.boolValue(
        json['sell_from'] ??
            json['sell_from_this_location'] ??
            json['is_selling'],
        fallback: true,
      ),
      tracked: JsonParser.boolValue(
        json['tracked'] ?? json['track_inventory'],
        fallback: true,
      ),
      qty: JsonParser.intValue(
        json['qty'] ?? json['stock'] ?? json['quantity'],
      ),
    );
  }

  Map<String, dynamic> toJson() => {
        'store_id': storeId,
        'store_location_id': locationId,
        'location_name': locationName,
        'is_default': isDefault,
        'sell_from': sellFrom,
        'tracked': tracked,
        'qty': qty,
      };
}

class CartItem {
  bool exists;
  int? cartItemId;

  CartItem({this.exists = false, this.cartItemId});

  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      exists: JsonParser.boolValue(json['exists'] ?? false),
      cartItemId: JsonParser.intValue(json['cart_item_id']),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['exists'] = exists;
    data['cart_item_id'] = cartItemId;
    return data;
  }
}

class ProductAttribute {
  String name;
  String slug;
  String? swatcheType;
  List<String> values;
  List<SwatchValue>? swatchValues;

  ProductAttribute({
    required this.name,
    required this.slug,
    this.swatcheType,
    this.values = const [],
    this.swatchValues,
  });

  factory ProductAttribute.fromJson(Map<String, dynamic> json) {
    return ProductAttribute(
      name: JsonParser.requireString(
        json['name'],
        model: modelName,
        field: 'attributes[].name',
      ),
      slug: JsonParser.requireString(
        json['slug'],
        model: modelName,
        field: 'attributes[].slug',
      ),
      swatcheType: JsonParser.string(json['swatche_type']),
      values: JsonParser.list<String>(
        json['values'],
        (v) => JsonParser.string(v),
      ),
      swatchValues: JsonParser.list<SwatchValue>(
        json['swatch_values'],
        (v) => SwatchValue.fromJson(v as Map<String, dynamic>),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['name'] = name;
    data['slug'] = slug;
    data['swatche_type'] = swatcheType;
    data['values'] = values;
    if (swatchValues != null) {
      data['swatch_values'] = swatchValues!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class SwatchValue {
  String? value;
  String? swatch;

  SwatchValue({this.value, this.swatch});

  factory SwatchValue.fromJson(Map<String, dynamic> json) {
    return SwatchValue(
      value: JsonParser.string(json['value']),
      swatch: JsonParser.string(json['swatch']),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['value'] = value;
    data['swatch'] = swatch;
    return data;
  }
}
