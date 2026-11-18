import 'package:hyper_commerce_seller/service/json_parser.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';

/// A customer return against a seller's order item. The seller reviews it
/// (approve / decline); pickup + refund are driven by Shiprocket/admin.
class OrderReturn {
  final int id;
  final int orderItemId;
  final int sellerOrderId;
  final int quantity;
  final int orderId;
  final int? userId;
  final int? storeId;
  final int? sellerId;

  // Reason / classification
  final String? reason;
  final String reasonType; // SELLER_FAULT | CUSTOMER_FAULT
  final String reasonCode; // damaged | wrong_item | ...

  // Financials
  final String refundAmount;
  final String refundAmountFormatted;
  final String returnShippingCharge;
  final String returnShippingChargeFormatted;
  final String? reimbursementAmount;
  final String? reimbursementAmountFormatted;
  final String shippingCostPayer; // SELLER | CUSTOMER | ''

  // Return status + admin adjudication
  final String returnStatus; // requested | approved | ... | refunded | declined
  final String adminAdjudicationStatus; // none | pending | ...
  final String? adminNotes;
  final int? returnShipmentId;

  // Shipment / label
  final String? returnCarrier;
  final String? returnTrackingNumber;
  final String? returnTrackingUrl;
  final String? returnLabelUrl;
  final String? sellerComment;
  final bool noShippingRequired;

  // Timestamps
  final String createdAt;
  final String? updatedAt;
  final String? sellerApprovedAt;
  final String? pickedUpAt;
  final String? receivedAt;
  final String? refundProcessedAt;

  // Attachments — customer-uploaded evidence photos.
  final List<String> images;

  // Order item snapshot
  final int? productId;
  final String? productTitle;
  final String? productImage;
  final String? variantTitle;
  final String? sku;
  final String? orderItemSubtotal;
  final String? orderItemPrice;
  final String? orderNumber;

  // Related entities
  final List<ReturnAddon> addons;
  final ReturnCustomer? customer;
  final ReturnStore? store;

  const OrderReturn({
    required this.id,
    required this.orderItemId,
    required this.sellerOrderId,
    this.quantity = 1,
    this.orderId = 0,
    this.userId,
    this.storeId,
    this.sellerId,
    this.reason,
    this.reasonType = '',
    this.reasonCode = '',
    this.refundAmount = '0.00',
    this.refundAmountFormatted = '0.00',
    this.returnShippingCharge = '0.00',
    this.returnShippingChargeFormatted = '0.00',
    this.reimbursementAmount,
    this.reimbursementAmountFormatted,
    this.shippingCostPayer = '',
    this.returnStatus = '',
    this.adminAdjudicationStatus = '',
    this.adminNotes,
    this.returnShipmentId,
    this.returnCarrier,
    this.returnTrackingNumber,
    this.returnTrackingUrl,
    this.returnLabelUrl,
    this.sellerComment,
    this.noShippingRequired = false,
    this.createdAt = '',
    this.updatedAt,
    this.sellerApprovedAt,
    this.pickedUpAt,
    this.receivedAt,
    this.refundProcessedAt,
    this.images = const [],
    this.productId,
    this.productTitle,
    this.productImage,
    this.variantTitle,
    this.sku,
    this.orderItemSubtotal,
    this.orderItemPrice,
    this.orderNumber,
    this.addons = const [],
    this.customer,
    this.store,
  });

  factory OrderReturn.fromJson(Map<String, dynamic> json) {
    final orderItem = json['order_item'] ?? json['orderItem'];
    final order = json['order'];
    final product = orderItem is Map ? orderItem['product'] : null;

    final mediaTitle = orderItem is Map
        ? JsonParser.string(orderItem['title'] ?? '')
        : '';
    final resolvedTitle = product is Map
        ? JsonParser.string(product['title'] ?? mediaTitle)
        : mediaTitle;
    final productImage = product is Map
        ? JsonParser.string(product['main_image'] ?? '')
        : '';

    // media[*].original_url is a graceful fallback if `images` is absent.
    final mediaList = (json['media'] as List?)
            ?.map((e) => e is Map ? (e['original_url'] ?? e['url'] ?? '').toString() : e.toString())
            .where((s) => s.isNotEmpty)
            .toList() ??
        const [];

    final rawAddons = orderItem is Map ? orderItem['addons'] : null;
    final addonList = rawAddons is List
        ? rawAddons
            .whereType<Map<String, dynamic>>()
            .map(ReturnAddon.fromJson)
            .toList()
        : const <ReturnAddon>[];

    return OrderReturn(
      id: JsonParser.intValue(json['id']),
      orderItemId: JsonParser.intValue(json['order_item_id']),
      sellerOrderId: JsonParser.intValue(json['seller_order_id']),
      quantity: JsonParser.intValue(json['quantity'] ?? 1),
      orderId: JsonParser.intValue(json['order_id'] ?? 0),
      userId: json['user_id'] != null ? JsonParser.intValue(json['user_id']) : null,
      storeId: json['store_id'] != null ? JsonParser.intValue(json['store_id']) : null,
      sellerId: json['seller_id'] != null ? JsonParser.intValue(json['seller_id']) : null,
      reason: json['reason'] != null ? JsonParser.string(json['reason']) : null,
      reasonType: JsonParser.string(json['reason_type'] ?? ''),
      reasonCode: JsonParser.string(json['reason_code'] ?? ''),
      refundAmount: JsonParser.string(json['refund_amount'] ?? '0.00'),
      refundAmountFormatted: JsonParser.string(json['refund_amount_formatted'] ?? '0.00'),
      returnShippingCharge: JsonParser.string(json['return_shipping_charge'] ?? '0.00'),
      returnShippingChargeFormatted: JsonParser.string(json['return_shipping_charge_formatted'] ?? '0.00'),
      reimbursementAmount: json['reimbursement_amount'] != null
          ? JsonParser.string(json['reimbursement_amount'])
          : null,
      reimbursementAmountFormatted: json['reimbursement_amount_formatted'] != null
          ? JsonParser.string(json['reimbursement_amount_formatted'])
          : null,
      shippingCostPayer: JsonParser.string(json['shipping_cost_payer'] ?? ''),
      returnStatus: JsonParser.string(json['return_status'] ?? ''),
      adminAdjudicationStatus:
          JsonParser.string(json['admin_adjudication_status'] ?? ''),
      adminNotes: json['admin_notes'] != null
          ? JsonParser.string(json['admin_notes'])
          : null,
      returnShipmentId: json['return_shipment_id'] != null
          ? JsonParser.intValue(json['return_shipment_id'])
          : null,
      returnCarrier: json['return_carrier'] != null ? JsonParser.string(json['return_carrier']) : null,
      returnTrackingNumber: json['return_tracking_number'] != null ? JsonParser.string(json['return_tracking_number']) : null,
      returnTrackingUrl: json['return_tracking_url'] != null ? JsonParser.string(json['return_tracking_url']) : null,
      returnLabelUrl: json['return_label_url'] != null
          ? JsonParser.string(json['return_label_url'])
          : null,
      sellerComment: json['seller_comment'] != null ? JsonParser.string(json['seller_comment']) : null,
      noShippingRequired: JsonParser.boolValue(json['no_shipping_required'] ?? false),
      createdAt: JsonParser.string(json['created_at'] ?? ''),
      updatedAt: json['updated_at'] != null ? JsonParser.string(json['updated_at']) : null,
      sellerApprovedAt: json['seller_approved_at'] != null
          ? JsonParser.string(json['seller_approved_at'])
          : null,
      pickedUpAt: json['picked_up_at'] != null
          ? JsonParser.string(json['picked_up_at'])
          : null,
      receivedAt: json['received_at'] != null
          ? JsonParser.string(json['received_at'])
          : null,
      refundProcessedAt: json['refund_processed_at'] != null
          ? JsonParser.string(json['refund_processed_at'])
          : null,
      images: (json['images'] as List?)?.map((e) => e.toString()).toList() ?? mediaList,
      productId: product is Map && product['id'] != null
          ? JsonParser.intValue(product['id'])
          : null,
      productTitle: resolvedTitle.isNotEmpty ? resolvedTitle : null,
      productImage: productImage.isNotEmpty ? productImage : null,
      variantTitle: orderItem is Map
          ? (JsonParser.string(orderItem['variant_title'] ?? '').isNotEmpty
              ? JsonParser.string(orderItem['variant_title'])
              : null)
          : null,
      sku: orderItem is Map && orderItem['sku'] != null
          ? JsonParser.string(orderItem['sku'])
          : null,
      orderItemSubtotal: orderItem is Map && orderItem['subtotal'] != null
          ? JsonParser.string(orderItem['subtotal'])
          : null,
      orderItemPrice: orderItem is Map && orderItem['price'] != null
          ? JsonParser.string(orderItem['price'])
          : null,
      orderNumber: order is Map ? JsonParser.string(order['order_number'] ?? order['uuid'] ?? '') : null,
      addons: addonList,
      customer: json['user'] is Map<String, dynamic>
          ? ReturnCustomer.fromJson(json['user'] as Map<String, dynamic>)
          : null,
      store: json['store'] is Map<String, dynamic>
          ? ReturnStore.fromJson(json['store'] as Map<String, dynamic>)
          : null,
    );
  }
}

/// An add-on line captured on the returned order item — carries what the
/// customer bought alongside the base product so the seller can see the
/// full breakdown of what's being refunded.
class ReturnAddon {
  final int id;
  final int addonGroupId;
  final int addonItemId;
  final double price;
  final String priceFormatted;
  final String groupTitle;
  final String groupSelectionType; // single | multiple
  final bool groupIsRequired;
  final String itemTitle;
  final String? itemIndicator; // veg | non-veg | null

  const ReturnAddon({
    required this.id,
    this.addonGroupId = 0,
    this.addonItemId = 0,
    this.price = 0,
    this.priceFormatted = '',
    this.groupTitle = '',
    this.groupSelectionType = '',
    this.groupIsRequired = false,
    this.itemTitle = '',
    this.itemIndicator,
  });

  factory ReturnAddon.fromJson(Map<String, dynamic> json) {
    final group = json['group'];
    final item = json['item'];
    return ReturnAddon(
      id: JsonParser.intValue(json['id']),
      addonGroupId: JsonParser.intValue(json['addon_group_id'] ?? 0),
      addonItemId: JsonParser.intValue(json['addon_item_id'] ?? 0),
      price: JsonParser.doubleValue(json['price'] ?? 0),
      priceFormatted: JsonParser.string(json['price_formatted'] ?? ''),
      groupTitle: group is Map ? JsonParser.string(group['title'] ?? '') : '',
      groupSelectionType:
          group is Map ? JsonParser.string(group['selection_type'] ?? '') : '',
      groupIsRequired:
          group is Map ? JsonParser.boolValue(group['is_required'] ?? false) : false,
      itemTitle: item is Map ? JsonParser.string(item['title'] ?? '') : '',
      itemIndicator:
          item is Map && item['indicator'] != null ? JsonParser.string(item['indicator']) : null,
    );
  }
}

/// Minimal snapshot of the customer who raised the return — enough for the
/// seller to identify and contact them.
class ReturnCustomer {
  final int id;
  final String? name;
  final String? mobile;
  final String? email;
  final String? profileImage;

  const ReturnCustomer({
    required this.id,
    this.name,
    this.mobile,
    this.email,
    this.profileImage,
  });

  factory ReturnCustomer.fromJson(Map<String, dynamic> json) {
    return ReturnCustomer(
      id: JsonParser.intValue(json['id']),
      name: json['name'] != null ? JsonParser.string(json['name']) : null,
      mobile: json['mobile'] != null ? JsonParser.string(json['mobile']) : null,
      email: json['email'] != null ? JsonParser.string(json['email']) : null,
      profileImage: json['profile_image'] != null
          ? JsonParser.string(json['profile_image'])
          : null,
    );
  }
}

/// Minimal store snapshot on a return — helpful when a seller operates
/// multiple storefronts.
class ReturnStore {
  final int id;
  final String? name;
  final String? contactNumber;
  final String? contactEmail;

  const ReturnStore({
    required this.id,
    this.name,
    this.contactNumber,
    this.contactEmail,
  });

  factory ReturnStore.fromJson(Map<String, dynamic> json) {
    return ReturnStore(
      id: JsonParser.intValue(json['id']),
      name: json['name'] != null ? JsonParser.string(json['name']) : null,
      contactNumber: json['contact_number'] != null
          ? JsonParser.string(json['contact_number'])
          : null,
      contactEmail: json['contact_email'] != null
          ? JsonParser.string(json['contact_email'])
          : null,
    );
  }
}

class OrderReturnsResponse {
  final bool success;
  final String message;
  final List<OrderReturn> returns;
  final int currentPage;
  final int lastPage;
  final CurrencyPayload? currency;

  const OrderReturnsResponse({
    required this.success,
    this.message = '',
    this.returns = const [],
    this.currentPage = 1,
    this.lastPage = 1,
    this.currency,
  });

  factory OrderReturnsResponse.fromJson(dynamic json) {
    final map = json as Map<String, dynamic>;
    final data = map['data'];
    final rows = data is Map ? data['data'] : data;
    return OrderReturnsResponse(
      success: JsonParser.boolValue(map['success'] ?? false),
      message: JsonParser.string(map['message'] ?? ''),
      returns: (rows as List?)?.whereType<Map<String, dynamic>>().map(OrderReturn.fromJson).toList() ?? const [],
      currentPage: data is Map ? JsonParser.intValue(data['current_page'] ?? 1) : 1,
      lastPage: data is Map ? JsonParser.intValue(data['last_page'] ?? 1) : 1,
      currency: data is Map && data['currency'] is Map<String, dynamic>
          ? CurrencyPayload.fromJson(data['currency'] as Map<String, dynamic>)
          : null,
    );
  }
}

/// `orders/returns/{id}` wraps the return with its order item + currency.
class OrderReturnDetail {
  final OrderReturn returnInfo;
  final int sellerOrderId;
  final CurrencyPayload? currency;

  const OrderReturnDetail({required this.returnInfo, this.sellerOrderId = 0, this.currency});

  factory OrderReturnDetail.fromJson(dynamic json) {
    final data = (json as Map<String, dynamic>)['data'] as Map<String, dynamic>? ?? {};
    return OrderReturnDetail(
      returnInfo: OrderReturn.fromJson(data['return'] as Map<String, dynamic>? ?? {}),
      sellerOrderId: JsonParser.intValue(data['seller_order_id'] ?? 0),
      currency: data['currency'] is Map<String, dynamic>
          ? CurrencyPayload.fromJson(data['currency'] as Map<String, dynamic>)
          : null,
    );
  }
}
