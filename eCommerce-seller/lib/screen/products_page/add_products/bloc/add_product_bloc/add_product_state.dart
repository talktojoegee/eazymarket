part of 'add_product_bloc.dart';

@immutable
sealed class AddProductState {
  final ProductData productData;
  const AddProductState(this.productData);
}

final class AddProductInitial extends AddProductState {
  AddProductInitial() : super(ProductData());
}

/// Loading state while fetching product data from API for editing
final class AddProductLoadingEdit extends AddProductState {
  AddProductLoadingEdit() : super(ProductData());
}

final class AddProductUpdating extends AddProductState {
  const AddProductUpdating(super.productData);
}

final class AddProductSubmitting extends AddProductState {
  const AddProductSubmitting(super.productData);
}

final class AddProductSuccess extends AddProductState {
  const AddProductSuccess(super.productData);
}

final class AddProductFailure extends AddProductState {
  final String error;
  const AddProductFailure(super.productData, this.error);
}

/// Per-location inventory entry used in the redesigned Pricing & Inventory step.
class LocationInventory {
  final int storeId;
  final String storeName;
  final int locationId;
  final String locationName;
  final bool isDefault;
  bool sellFromThisLocation;
  bool trackInventory;
  String qty;

  LocationInventory({
    required this.storeId,
    required this.storeName,
    required this.locationId,
    required this.locationName,
    this.isDefault = false,
    this.sellFromThisLocation = true,
    this.trackInventory = true,
    this.qty = '0',
  });

  LocationInventory copyWith({
    int? storeId,
    String? storeName,
    int? locationId,
    String? locationName,
    bool? isDefault,
    bool? sellFromThisLocation,
    bool? trackInventory,
    String? qty,
  }) {
    return LocationInventory(
      storeId: storeId ?? this.storeId,
      storeName: storeName ?? this.storeName,
      locationId: locationId ?? this.locationId,
      locationName: locationName ?? this.locationName,
      isDefault: isDefault ?? this.isDefault,
      sellFromThisLocation: sellFromThisLocation ?? this.sellFromThisLocation,
      trackInventory: trackInventory ?? this.trackInventory,
      qty: qty ?? this.qty,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'store_id': storeId.toString(),
      'location_id': locationId.toString(),
      'store_location_id': locationId.toString(),
      'sell_from_this_location': sellFromThisLocation ? '1' : '0',
      'sell_from': sellFromThisLocation ? '1' : '0',
      'is_selling': sellFromThisLocation ? '1' : '0',
      'track_inventory': trackInventory ? '1' : '0',
      'qty': qty,
      'stock': qty,
    };
  }
}

class StorePricing {
  final int storeId;
  final String storeName;
  final String? price;
  final String? specialPrice;
  final String? cost;
  final String? stock;
  final String? sku;

  StorePricing({
    required this.storeId,
    required this.storeName,
    this.price,
    this.specialPrice,
    this.cost,
    this.stock,
    this.sku,
  });

  StorePricing copyWith({
    int? storeId,
    String? storeName,
    String? price,
    String? specialPrice,
    String? cost,
    String? stock,
    String? sku,
  }) {
    return StorePricing(
      storeId: storeId ?? this.storeId,
      storeName: storeName ?? this.storeName,
      price: price ?? this.price,
      specialPrice: specialPrice ?? this.specialPrice,
      cost: cost ?? this.cost,
      stock: stock ?? this.stock,
      sku: sku ?? this.sku,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "store_id": storeId.toString(),
      "price": price ?? "",
      "special_price": specialPrice ?? "",
      "cost": cost ?? "",
      "stock": stock ?? "",
      "sku": sku ?? "",
    };
  }
}

class VariantStorePricing {
  final int storeId;
  final String variantId;
  final String? price;
  final String? specialPrice;
  final String? cost;
  final String? stock;
  final String? sku;

  VariantStorePricing({
    required this.storeId,
    required this.variantId,
    this.price,
    this.specialPrice,
    this.cost,
    this.stock,
    this.sku,
  });

  VariantStorePricing copyWith({
    int? storeId,
    String? variantId,
    String? price,
    String? specialPrice,
    String? cost,
    String? stock,
    String? sku,
  }) {
    return VariantStorePricing(
      storeId: storeId ?? this.storeId,
      variantId: variantId ?? this.variantId,
      price: price ?? this.price,
      specialPrice: specialPrice ?? this.specialPrice,
      cost: cost ?? this.cost,
      stock: stock ?? this.stock,
      sku: sku ?? this.sku,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "store_id": storeId.toString(),
      "variant_id": variantId,
      "price": price ?? "",
      "special_price": specialPrice ?? "",
      "cost": cost ?? "",
      "stock": stock ?? "",
      "sku": sku ?? "",
    };
  }
}

class KitComponentData {
  final int productId;
  final int productVariantId;
  final String productTitle;
  final String variantTitle;
  final double price;
  final String? image;
  int quantity;

  KitComponentData({
    required this.productId,
    required this.productVariantId,
    required this.productTitle,
    required this.variantTitle,
    required this.price,
    this.image,
    this.quantity = 1,
  });

  KitComponentData copyWith({
    int? productId,
    int? productVariantId,
    String? productTitle,
    String? variantTitle,
    double? price,
    String? image,
    int? quantity,
  }) {
    return KitComponentData(
      productId: productId ?? this.productId,
      productVariantId: productVariantId ?? this.productVariantId,
      productTitle: productTitle ?? this.productTitle,
      variantTitle: variantTitle ?? this.variantTitle,
      price: price ?? this.price,
      image: image ?? this.image,
      quantity: quantity ?? this.quantity,
    );
  }

  factory KitComponentData.fromJson(Map<String, dynamic> json) {
    return KitComponentData(
      productId: json['product_id'] ?? 0,
      productVariantId: json['product_variant_id'] ?? json['variant_id'] ?? 0,
      productTitle: json['product_title'] ?? '',
      variantTitle: json['variant_title'] ?? '',
      price: (json['price'] is num)
          ? (json['price'] as num).toDouble()
          : (double.tryParse(json['price']?.toString() ?? '0') ?? 0.0),
      image: json['image'],
      quantity: json['quantity'] ?? 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "product_variant_id": productVariantId,
      "quantity": quantity,
    };
  }
}

class ProductData {
  int? id;
  int? categoryId;
  int? brandId;
  String? brandName; // New
  String? madeIn;
  String? title;
  String? hsnCode;
  String? indicator;
  bool isReturnable = false;
  int returnableDays = 0;
  bool isCancelable = false;
  String? cancelableTill;
  bool isCodAllowed = true;
  String? attachmentMode;
  String type = 'simple'; // 'simple', 'variant', 'kit'
  String? barcode;
  String? mainImage;
  List<String> otherImages = [];
  List<int> taxGroupIds = [];
  bool isInclusiveTax = false;
  List<VariantData> variants = [];
  List<StorePricing> storePricing = [];
  List<VariantStorePricing> variantStorePricing = []; // Added this
  // Global pricing fields for the redesigned Pricing & Inventory step
  String? globalPrice;
  String? globalSpecialPrice;
  String? globalCost;
  String? globalSku;
  List<LocationInventory> locationInventory = [];
  String imageFit = "cover"; // 'cover' or 'contain'
  String? videoType;
  String? videoLink;
  String? productVideo;
  String? shortDescription;
  String? description;
  int minimumOrderQuantity = 1;
  int quantityStepSize = 1;
  int totalAllowedQuantity = 0;
  bool isAttachmentRequired = false;
  bool featured = false;
  bool requiresOtp = false;
  String? warrantyPeriod;
  String? guaranteePeriod;
  List<String> tags = [];
  List<Map<String, String>> customFields = [];
  List<int> categoryLineage = []; // To track expanded parents during edit
  List<CustomProductSection> customProductSections = [];
  List<TaxBreakdown>? taxBreakdown;

  // Kit fields
  int? kitStoreId;
  String? kitStoreName;
  List<KitComponentData> kitComponents = [];
  String? kitQuantity;
  String? kitPrice;

  ProductData();

  ProductData copyWith({
    int? id,
    int? categoryId,
    int? brandId,
    String? brandName,
    String? madeIn,
    String? title,
    String? hsnCode,
    String? indicator,
    bool? isReturnable,
    int? returnableDays,
    bool? isCancelable,
    String? cancelableTill,
    bool? isCodAllowed,
    String? attachmentMode,
    String? type,
    String? barcode,
    String? mainImage,
    List<String>? otherImages,
    List<int>? taxGroupIds,
    bool? isInclusiveTax,
    List<VariantData>? variants,
    List<StorePricing>? storePricing,
    List<VariantStorePricing>? variantStorePricing,
    String? globalPrice,
    String? globalSpecialPrice,
    String? globalCost,
    String? globalSku,
    List<LocationInventory>? locationInventory,
    String? imageFit,
    String? videoType,
    String? videoLink,
    String? productVideo,
    String? shortDescription,
    String? description,
    int? minimumOrderQuantity,
    int? quantityStepSize,
    int? totalAllowedQuantity,
    bool? isAttachmentRequired,
    bool? featured,
    bool? requiresOtp,
    String? warrantyPeriod,
    String? guaranteePeriod,
    List<String>? tags,
    List<Map<String, String>>? customFields,
    List<int>? categoryLineage,
    List<CustomProductSection>? customProductSections,
    List<TaxBreakdown>? taxBreakdown,
    int? kitStoreId,
    String? kitStoreName,
    List<KitComponentData>? kitComponents,
    String? kitQuantity,
    String? kitPrice,
    bool clearMainImage = false,
    bool clearProductVideo = false,
    bool clearVideoLink = false,
  }) {
    final newData = ProductData();
    newData.id = id ?? this.id;
    newData.categoryId = categoryId ?? this.categoryId;
    newData.brandId = brandId ?? this.brandId;
    newData.brandName = brandName ?? this.brandName;
    newData.madeIn = madeIn ?? this.madeIn;
    newData.title = title ?? this.title;
    newData.hsnCode = hsnCode ?? this.hsnCode;
    newData.indicator = indicator ?? this.indicator;
    newData.isReturnable = isReturnable ?? this.isReturnable;
    newData.returnableDays = returnableDays ?? this.returnableDays;
    newData.isCancelable = isCancelable ?? this.isCancelable;
    newData.cancelableTill = cancelableTill ?? this.cancelableTill;
    newData.isCodAllowed = isCodAllowed ?? this.isCodAllowed;
    newData.attachmentMode = attachmentMode ?? this.attachmentMode;
    newData.type = type ?? this.type;
    newData.barcode = barcode ?? this.barcode;
    newData.mainImage = clearMainImage ? null : (mainImage ?? this.mainImage);
    newData.otherImages = otherImages ?? this.otherImages;
    newData.taxGroupIds = taxGroupIds ?? this.taxGroupIds;
    newData.isInclusiveTax = isInclusiveTax ?? this.isInclusiveTax;
    newData.variants = variants ?? this.variants;
    newData.storePricing = storePricing ?? this.storePricing;
    newData.variantStorePricing =
        variantStorePricing ?? this.variantStorePricing;
    newData.globalPrice = globalPrice ?? this.globalPrice;
    newData.globalSpecialPrice = globalSpecialPrice ?? this.globalSpecialPrice;
    newData.globalCost = globalCost ?? this.globalCost;
    newData.globalSku = globalSku ?? this.globalSku;
    newData.locationInventory = locationInventory ?? this.locationInventory;
    newData.imageFit = imageFit ?? this.imageFit;
    newData.videoType = videoType ?? this.videoType;
    newData.videoLink = clearVideoLink ? null : (videoLink ?? this.videoLink);
    newData.productVideo =
        clearProductVideo ? null : (productVideo ?? this.productVideo);
    newData.shortDescription = shortDescription ?? this.shortDescription;
    newData.description = description ?? this.description;
    newData.minimumOrderQuantity =
        minimumOrderQuantity ?? this.minimumOrderQuantity;
    newData.quantityStepSize = quantityStepSize ?? this.quantityStepSize;
    newData.totalAllowedQuantity =
        totalAllowedQuantity ?? this.totalAllowedQuantity;
    newData.isAttachmentRequired =
        isAttachmentRequired ?? this.isAttachmentRequired;
    newData.featured = featured ?? this.featured;
    newData.requiresOtp = requiresOtp ?? this.requiresOtp;
    newData.warrantyPeriod = warrantyPeriod ?? this.warrantyPeriod;
    newData.guaranteePeriod = guaranteePeriod ?? this.guaranteePeriod;
    newData.tags = tags ?? this.tags;
    newData.customFields = customFields ?? this.customFields;
    newData.categoryLineage = categoryLineage ?? this.categoryLineage;
    newData.customProductSections =
        customProductSections ?? this.customProductSections;
    newData.taxBreakdown = taxBreakdown ?? this.taxBreakdown;
    newData.kitStoreId = kitStoreId ?? this.kitStoreId;
    newData.kitStoreName = kitStoreName ?? this.kitStoreName;
    newData.kitComponents = kitComponents ?? this.kitComponents;
    newData.kitQuantity = kitQuantity ?? this.kitQuantity;
    newData.kitPrice = kitPrice ?? this.kitPrice;
    return newData;
  }
}

class VariantData {
  String? id;
  String title;
  String? name; // New optional name
  List<int> attributeValueIds;
  List<Map<String, dynamic>> attributes; // New detailed mapping
  String? barcode;
  String? weight;
  String? height;
  String? length;
  String? breadth;
  bool isAvailable;
  bool isDefault;
  String? image; // New variant image
  List<TaxBreakdown>? taxBreakdown;

  VariantData({
    this.id,
    required this.title,
    this.name,
    required this.attributeValueIds,
    this.attributes = const [],
    this.barcode,
    this.weight,
    this.height,
    this.length,
    this.breadth,
    this.isAvailable = true,
    this.isDefault = false,
    this.image,
    this.taxBreakdown,
  });

  VariantData copyWith({
    String? id,
    String? title,
    String? name,
    List<int>? attributeValueIds,
    List<Map<String, dynamic>>? attributes,
    String? barcode,
    String? weight,
    String? height,
    String? length,
    String? breadth,
    bool? isAvailable,
    bool? isDefault,
    String? image,
    List<TaxBreakdown>? taxBreakdown,
    bool clearImage = false,
  }) {
    return VariantData(
      id: id ?? this.id,
      title: title ?? this.title,
      name: name ?? this.name,
      attributeValueIds: attributeValueIds ?? this.attributeValueIds,
      attributes: attributes ?? this.attributes,
      barcode: barcode ?? this.barcode,
      weight: weight ?? this.weight,
      height: height ?? this.height,
      length: length ?? this.length,
      breadth: breadth ?? this.breadth,
      isAvailable: isAvailable ?? this.isAvailable,
      isDefault: isDefault ?? this.isDefault,
      image: clearImage ? null : (image ?? this.image),
      taxBreakdown: taxBreakdown ?? this.taxBreakdown,
    );
  }
}

class CustomProductSection {
  int? id;
  String? uuid;
  String? title;
  String? description;
  int sortOrder;
  List<CustomProductField> fields;

  CustomProductSection({
    this.id,
    this.uuid,
    this.title,
    this.description,
    this.sortOrder = 0,
    this.fields = const [],
  });

  CustomProductSection copyWith({
    int? id,
    String? uuid,
    String? title,
    String? description,
    int? sortOrder,
    List<CustomProductField>? fields,
  }) {
    return CustomProductSection(
      id: id ?? this.id,
      uuid: uuid ?? this.uuid,
      title: title ?? this.title,
      description: description ?? this.description,
      sortOrder: sortOrder ?? this.sortOrder,
      fields: fields ?? this.fields,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      'title': title ?? "",
      'description': description ?? "",
      'sort_order': sortOrder,
      'fields': fields.map((f) => f.toJson()).toList(),
    };
  }
}

class CustomProductField {
  int? id;
  String? uuid;
  String? title;
  String? description;
  String? image;
  int sortOrder;

  CustomProductField({
    this.id,
    this.uuid,
    this.title,
    this.description,
    this.image,
    this.sortOrder = 0,
  });

  CustomProductField copyWith({
    int? id,
    String? uuid,
    String? title,
    String? description,
    String? image,
    int? sortOrder,
    bool clearImage = false,
  }) {
    return CustomProductField(
      id: id ?? this.id,
      uuid: uuid ?? this.uuid,
      title: title ?? this.title,
      description: description ?? this.description,
      image: clearImage ? null : (image ?? this.image),
      sortOrder: sortOrder ?? this.sortOrder,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      'title': title ?? "",
      'description': description ?? "",
      if (image != null && !image!.startsWith('http')) 'image': image,
      'sort_order': sortOrder,
    };
  }
}
