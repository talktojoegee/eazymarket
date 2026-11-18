import 'package:hyper_commerce_seller/service/json_parser.dart';

/// Why a product is (or isn't) customer-visible — mirrors the panel
/// ProductVisibilityInspector: overall status + per-gate checks + zone summary.
class ProductVisibility {
  final String status; // live | partial | hidden
  final List<VisibilityCheck> checks;
  final int reachableCount;
  final int totalCount;

  const ProductVisibility({
    this.status = 'hidden',
    this.checks = const [],
    this.reachableCount = 0,
    this.totalCount = 0,
  });

  factory ProductVisibility.fromJson(dynamic json) {
    final data = (json as Map<String, dynamic>)['data'] as Map<String, dynamic>? ?? {};
    final zone = data['zone_summary'] as Map<String, dynamic>? ?? {};
    return ProductVisibility(
      status: JsonParser.string(data['status'] ?? 'hidden'),
      checks: (data['checks'] as List?)
              ?.whereType<Map<String, dynamic>>()
              .map(VisibilityCheck.fromJson)
              .toList() ??
          const [],
      reachableCount: JsonParser.intValue(zone['reachable_count'] ?? 0),
      totalCount: JsonParser.intValue(zone['total_count'] ?? 0),
    );
  }
}

class VisibilityCheck {
  final String key;
  final String state; // pass | fail
  final String label;
  final String message;
  final String? fix;

  const VisibilityCheck({
    this.key = '',
    this.state = 'fail',
    this.label = '',
    this.message = '',
    this.fix,
  });

  bool get passed => state == 'pass';

  factory VisibilityCheck.fromJson(Map<String, dynamic> json) => VisibilityCheck(
        key: JsonParser.string(json['key'] ?? ''),
        state: JsonParser.string(json['state'] ?? 'fail'),
        label: JsonParser.string(json['label'] ?? ''),
        message: JsonParser.string(json['message'] ?? ''),
        fix: json['fix'] != null ? JsonParser.string(json['fix']) : null,
      );
}
