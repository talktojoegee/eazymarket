import 'package:hyper_commerce_seller/service/json_parser.dart';

/// Mirrors the panel's `buildSellerFulfillment()` payload. The capability
/// flags on here are the single source of truth for which parcel buttons the
/// app may show — never re-derive them from the status string.
class OrderFulfillment {
  final int sellerOrderId;
  final String fulfillmentMethod;
  final String methodLabel;
  final bool isShiprocket;
  final String shiprocketTitle;
  final String fulfillmentStatus;
  final bool isPickup;
  final bool supported;
  final bool canFulfill;
  final bool canSetStatus;
  final String? shipBy;
  final bool shipByOverdue;
  final String? locationName;
  final List<FulfillmentLine> lines;
  final List<ParcelShipment> shipments;
  final List<ShipmentPackage> packages;
  final ShipmentDims defaults;

  const OrderFulfillment({
    this.sellerOrderId = 0,
    this.fulfillmentMethod = '',
    this.methodLabel = '',
    this.isShiprocket = false,
    this.shiprocketTitle = 'Shiprocket',
    this.fulfillmentStatus = 'unfulfilled',
    this.isPickup = false,
    this.supported = false,
    this.canFulfill = false,
    this.canSetStatus = false,
    this.shipBy,
    this.shipByOverdue = false,
    this.locationName,
    this.lines = const [],
    this.shipments = const [],
    this.packages = const [],
    this.defaults = const ShipmentDims(),
  });

  bool get isManual => fulfillmentMethod == 'manual';

  factory OrderFulfillment.fromJson(dynamic json) {
    final data = (json as Map<String, dynamic>)['data'] as Map<String, dynamic>? ?? {};
    return OrderFulfillment(
      sellerOrderId: JsonParser.intValue(data['seller_order_id'] ?? 0),
      fulfillmentMethod: JsonParser.string(data['fulfillment_method'] ?? ''),
      methodLabel: JsonParser.string(data['method_label'] ?? ''),
      isShiprocket: JsonParser.boolValue(data['is_shiprocket'] ?? false),
      shiprocketTitle: JsonParser.string(data['shiprocket_title'] ?? 'Shiprocket'),
      fulfillmentStatus: JsonParser.string(data['fulfillment_status'] ?? 'unfulfilled'),
      isPickup: JsonParser.boolValue(data['is_pickup'] ?? false),
      supported: JsonParser.boolValue(data['supported'] ?? false),
      canFulfill: JsonParser.boolValue(data['can_fulfill'] ?? false),
      canSetStatus: JsonParser.boolValue(data['can_set_status'] ?? false),
      shipBy: data['ship_by'] != null ? JsonParser.string(data['ship_by']) : null,
      shipByOverdue: JsonParser.boolValue(data['ship_by_overdue'] ?? false),
      locationName: data['location_name'] != null ? JsonParser.string(data['location_name']) : null,
      lines: (data['lines'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(FulfillmentLine.fromJson)
              .toList() ??
          const [],
      shipments: (data['shipments'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(ParcelShipment.fromJson)
              .toList() ??
          const [],
      packages: (data['packages'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(ShipmentPackage.fromJson)
              .toList() ??
          const [],
      defaults: ShipmentDims.fromJson(data['shiprocket_defaults'] as Map<String, dynamic>? ?? {}),
    );
  }
}

class FulfillmentLine {
  final int orderItemId;
  final String title;
  final String variant;
  final int ordered;
  final int shipped;
  final int delivered;
  final int remaining;
  final double weightKg;

  const FulfillmentLine({
    this.orderItemId = 0,
    this.title = '',
    this.variant = '',
    this.ordered = 0,
    this.shipped = 0,
    this.delivered = 0,
    this.remaining = 0,
    this.weightKg = 0,
  });

  factory FulfillmentLine.fromJson(Map<String, dynamic> json) => FulfillmentLine(
        orderItemId: JsonParser.intValue(json['order_item_id'] ?? 0),
        title: JsonParser.string(json['title'] ?? ''),
        variant: JsonParser.string(json['variant'] ?? ''),
        ordered: JsonParser.intValue(json['ordered'] ?? 0),
        shipped: JsonParser.intValue(json['shipped'] ?? 0),
        delivered: JsonParser.intValue(json['delivered'] ?? 0),
        remaining: JsonParser.intValue(json['remaining'] ?? 0),
        weightKg: JsonParser.doubleValue(json['weight_kg'] ?? 0),
      );
}

/// A line that can be re-picked while a pending Shiprocket parcel is edited.
/// `remaining` already includes the quantity currently sitting in this parcel.
class EditableLine {
  final int orderItemId;
  final String title;
  final String variant;
  final int ordered;
  final int remaining;
  final int preset;
  final double weightKg;

  const EditableLine({
    this.orderItemId = 0,
    this.title = '',
    this.variant = '',
    this.ordered = 0,
    this.remaining = 0,
    this.preset = 0,
    this.weightKg = 0,
  });

  factory EditableLine.fromJson(Map<String, dynamic> json) => EditableLine(
        orderItemId: JsonParser.intValue(json['order_item_id'] ?? 0),
        title: JsonParser.string(json['title'] ?? ''),
        variant: JsonParser.string(json['variant'] ?? ''),
        ordered: JsonParser.intValue(json['ordered'] ?? 0),
        remaining: JsonParser.intValue(json['remaining'] ?? 0),
        preset: JsonParser.intValue(json['preset'] ?? 0),
        weightKg: JsonParser.doubleValue(json['weight_kg'] ?? 0),
      );
}

class ShipmentDims {
  final double lengthCm;
  final double breadthCm;
  final double heightCm;
  final double weightKg;

  const ShipmentDims({
    this.lengthCm = 10,
    this.breadthCm = 10,
    this.heightCm = 10,
    this.weightKg = 0.5,
  });

  factory ShipmentDims.fromJson(Map<String, dynamic> json) => ShipmentDims(
        lengthCm: JsonParser.doubleValue(json['length_cm'] ?? 10),
        breadthCm: JsonParser.doubleValue(json['breadth_cm'] ?? 10),
        heightCm: JsonParser.doubleValue(json['height_cm'] ?? 10),
        weightKg: JsonParser.doubleValue(json['weight_kg'] ?? 0.5),
      );
}

class ShipmentPackage {
  final int id;
  final String name;
  final double lengthCm;
  final double breadthCm;
  final double heightCm;
  final double weightKg;
  final bool isDefault;

  const ShipmentPackage({
    this.id = 0,
    this.name = '',
    this.lengthCm = 0,
    this.breadthCm = 0,
    this.heightCm = 0,
    this.weightKg = 0,
    this.isDefault = false,
  });

  String get label => '$name — ${lengthCm.toStringAsFixed(0)} × '
      '${breadthCm.toStringAsFixed(0)} × ${heightCm.toStringAsFixed(0)} cm';

  factory ShipmentPackage.fromJson(Map<String, dynamic> json) => ShipmentPackage(
        id: JsonParser.intValue(json['id'] ?? 0),
        name: JsonParser.string(json['name'] ?? ''),
        lengthCm: JsonParser.doubleValue(json['length_cm'] ?? 0),
        breadthCm: JsonParser.doubleValue(json['breadth_cm'] ?? 0),
        heightCm: JsonParser.doubleValue(json['height_cm'] ?? 0),
        weightKg: JsonParser.doubleValue(json['weight_kg'] ?? 0),
        isDefault: JsonParser.boolValue(json['is_default'] ?? false),
      );
}

/// Parcel as the panel sees it: every action button is gated by one of the
/// `can*` flags the server computed, never by the raw status.
class ParcelShipment {
  final int id;
  final String status;
  final String statusLabel;
  final String? providerCode;
  final String? carrierName;
  final String? trackingNumber;
  final String? trackingUrl;
  final String? awbNumber;
  final String? labelUrl;
  final String? invoiceUrl;
  final String? manifestUrl;
  final String? bookingError;
  final String? bookingWarning;
  final bool isShiprocket;
  final bool isPending;
  final bool isTerminal;
  final bool hasReturn;
  final bool canDeliver;
  final bool canCancel;
  final bool canEdit;
  final bool canRetry;
  final bool canNdr;
  final bool canSync;
  final ShipmentDims dims;
  final List<EditableLine> editLines;
  final List<ParcelProduct> products;

  const ParcelShipment({
    this.id = 0,
    this.status = '',
    this.statusLabel = '',
    this.providerCode,
    this.carrierName,
    this.trackingNumber,
    this.trackingUrl,
    this.awbNumber,
    this.labelUrl,
    this.invoiceUrl,
    this.manifestUrl,
    this.bookingError,
    this.bookingWarning,
    this.isShiprocket = false,
    this.isPending = false,
    this.isTerminal = false,
    this.hasReturn = false,
    this.canDeliver = false,
    this.canCancel = false,
    this.canEdit = false,
    this.canRetry = false,
    this.canNdr = false,
    this.canSync = false,
    this.dims = const ShipmentDims(),
    this.editLines = const [],
    this.products = const [],
  });

  /// The panel exposes no `can_update_lines`; it renders the button when a
  /// pending parcel has editable lines.
  bool get canUpdateLines => isPending && editLines.isNotEmpty;

  /// Packing slip is server-side valid always, but the panel hides it once a
  /// return exists and prefers Shiprocket's own documents.
  bool get canPrintSlip => !hasReturn;

  static String? _nullableString(dynamic v) {
    if (v == null) return null;
    final s = JsonParser.string(v);
    return s.isEmpty ? null : s;
  }

  factory ParcelShipment.fromJson(Map<String, dynamic> json) => ParcelShipment(
        id: JsonParser.intValue(json['id'] ?? 0),
        status: JsonParser.string(json['status'] ?? ''),
        statusLabel: JsonParser.string(json['status_label'] ?? ''),
        providerCode: _nullableString(json['provider_code']),
        carrierName: _nullableString(json['carrier_name']),
        trackingNumber: _nullableString(json['tracking_number']),
        trackingUrl: _nullableString(json['tracking_url']),
        awbNumber: _nullableString(json['awb_number']),
        labelUrl: _nullableString(json['label_url']),
        invoiceUrl: _nullableString(json['invoice_url']),
        manifestUrl: _nullableString(json['manifest_url']),
        bookingError: _nullableString(json['booking_error']),
        bookingWarning: _nullableString(json['booking_warning']),
        isShiprocket: JsonParser.boolValue(json['is_shiprocket'] ?? false),
        isPending: JsonParser.boolValue(json['is_pending'] ?? false),
        isTerminal: JsonParser.boolValue(json['is_terminal'] ?? false),
        hasReturn: JsonParser.boolValue(json['has_return'] ?? false),
        canDeliver: JsonParser.boolValue(json['can_deliver'] ?? false),
        canCancel: JsonParser.boolValue(json['can_cancel'] ?? false),
        canEdit: JsonParser.boolValue(json['can_edit'] ?? false),
        canRetry: JsonParser.boolValue(json['can_retry'] ?? false),
        canNdr: JsonParser.boolValue(json['can_ndr'] ?? false),
        canSync: JsonParser.boolValue(json['can_sync'] ?? false),
        dims: ShipmentDims.fromJson(json['dims'] as Map<String, dynamic>? ?? {}),
        editLines: (json['edit_lines'] as List?)
                ?.whereType<Map<String, dynamic>>()
                .map(EditableLine.fromJson)
                .toList() ??
            const [],
        products: (json['products'] as List?)
                ?.whereType<Map<String, dynamic>>()
                .map(ParcelProduct.fromJson)
                .toList() ??
            const [],
      );
}

class ParcelProduct {
  final String title;
  final String variant;
  final int quantity;

  const ParcelProduct({this.title = '', this.variant = '', this.quantity = 0});

  factory ParcelProduct.fromJson(Map<String, dynamic> json) => ParcelProduct(
        title: JsonParser.string(json['title'] ?? ''),
        variant: JsonParser.string(json['variant'] ?? ''),
        quantity: JsonParser.intValue(json['quantity'] ?? 0),
      );
}

/// Volumetric weight uses the same divisor the panel embeds in the DOM
/// (StorePackage::VOLUMETRIC_DIVISOR). Display only — the server never
/// recomputes it from what the client sends.
class VolumetricWeight {
  static const int divisor = 5000;

  static double round3(double v) => (v * 1000).round() / 1000;

  static double volumetric(double lengthCm, double breadthCm, double heightCm) =>
      round3((lengthCm * breadthCm * heightCm) / divisor);

  static double billed(double deadWeightKg, double volumetricKg) =>
      round3(deadWeightKg > volumetricKg ? deadWeightKg : volumetricKg);
}
