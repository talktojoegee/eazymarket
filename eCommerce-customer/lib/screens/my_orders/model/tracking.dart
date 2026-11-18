import 'package:hyper_commerce/config/helper.dart';

class Tracking {
  final String? statusCode;
  final List<Milestones>? milestones;
  final List<History>? history;

  Tracking({
    this.statusCode,
    this.milestones,
    this.history,
  });

  factory Tracking.fromJson(Map<String, dynamic> json) {
    return Tracking(
      statusCode: parseString(json['status_code'].toString()),
      milestones: (json['milestones'] as List<dynamic>?)
          ?.map((e) => Milestones.fromJson(e as Map<String, dynamic>))
          .toList(),
      history: (json['history'] as List<dynamic>?)
          ?.map((e) => History.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

class Milestones {
  final String? key;
  final String? statusCode;
  final String? label;
  final bool? done;
  final bool? current;
  final String? marker;
  final String? at;
  final bool? inferred;
  final int? qty;
  final int? completedQty;

  Milestones(
      {this.key,
      this.statusCode,
      this.label,
      this.done,
      this.current,
      this.marker,
      this.at,
      this.inferred,
      this.qty,
      this.completedQty});

  factory Milestones.fromJson(Map<String, dynamic> json) {
    return Milestones(
      key: parseString(json['key']),
      statusCode: parseString(json['status_code']),
      label: parseString(json['label']),
      done: parseBool(json['done']),
      current: parseBool(json['current']),
      marker: parseString(json['marker']),
      at: parseString(json['at']),
      inferred: parseBool(json['inferred']),
      qty: parseInt(json['quantity']),
      completedQty: parseInt(json['completed_quantity']),
    );
  }
}

class History {
  final String? code;
  final String? label;
  final bool? done;
  final String? at;
  final bool? isException;
  final Meta? meta;

  History(
      {this.code, this.label, this.done, this.at, this.isException, this.meta});

  factory History.fromJson(Map<String, dynamic> json) {
    return History(
      code: parseString(json['code']),
      label: parseString(json['label']),
      done: parseBool(json['done']),
      at: parseString(json['at']),
      isException: parseBool(json['is_exception']),
      meta: json['meta'] is Map<String, dynamic>
          ? Meta.fromJson(json['meta'])
          : null,
    );
  }
}

class Meta {
  final int? qty;
  final int? shipmentId;
  final String? courier;
  final String? trackingId;
  final String? trackingUrl;
  final String? status;

  Meta(
      {this.qty,
      this.shipmentId,
      this.courier,
      this.trackingId,
      this.trackingUrl,
      this.status});

  factory Meta.fromJson(Map<String, dynamic> json) {
    return Meta(
      qty: parseInt(json['quantity']),
      shipmentId: parseInt(json['shipment_id']),
      courier: parseString(json['courier']),
      trackingId: parseString(json['tracking_id']),
      trackingUrl: parseString(json['tracking_url']),
      status: parseString(json['status']),
    );
  }
}

class Shipments {
  final int? id;
  final String? status;
  final String? customerStatus;
  final String? customerStatusLabel;
  final String? carrierName;
  final String? trackingNumber;
  final String? trackingUrl;
  final int? qty;
  final String? pickedUpAt;
  final String? deliveredAt;

  Shipments(
      {this.id,
      this.status,
      this.customerStatus,
      this.customerStatusLabel,
      this.carrierName,
      this.trackingNumber,
      this.trackingUrl,
      this.qty,
      this.pickedUpAt,
      this.deliveredAt});

  factory Shipments.fromJson(Map<String, dynamic> json) {
    return Shipments(
      id: parseInt(json['id']),
      status: parseString(json['status']),
      customerStatus: parseString(json['customer_status']),
      customerStatusLabel: parseString(json['customer_status_label']),
      carrierName: parseString(json['carrier_name']),
      trackingNumber: parseString(json['tracking_number']),
      trackingUrl: parseString(json['tracking_url']),
      qty: parseInt(json['qty']),
      pickedUpAt: parseString(json['picked_up_at']),
      deliveredAt: parseString(json['delivered_at']),
    );
  }
}
