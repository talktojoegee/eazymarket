import 'package:hyper_commerce_seller/service/json_parser.dart';

const String modelName = 'seller_permissions_model';

class SellerPermissionsResponse {
  bool? success;
  String? message;
  SellerPermissionsData? data;

  SellerPermissionsResponse({this.success, this.message, this.data});

  factory SellerPermissionsResponse.fromJson(Map<String, dynamic> json) {
    return SellerPermissionsResponse(
      success: JsonParser.boolValue(json['success'] ?? false),
      message: JsonParser.string(json['message'] ?? ''),
      data: json['data'] != null
          ? SellerPermissionsData.fromJson(json['data'] as Map<String, dynamic>)
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

class SellerPermissionsData {
  RoleBasic? role;
  GroupedPermissions? groupedPermissions;
  List<String> assigned;

  SellerPermissionsData({
    this.role,
    this.groupedPermissions,
    this.assigned = const [],
  });

  factory SellerPermissionsData.fromJson(Map<String, dynamic> json) {
    return SellerPermissionsData(
      role: json['role'] != null
          ? RoleBasic.fromJson(json['role'] as Map<String, dynamic>)
          : null,
      groupedPermissions: json['grouped_permissions'] != null
          ? GroupedPermissions.fromJson(
              json['grouped_permissions'] as Map<String, dynamic>,
            )
          : null,
      assigned: JsonParser.list<String>(
        json['assigned'],
        (v) => JsonParser.string(v),
      ).where((p) => !GroupedPermissions.isExcludedPermission(p)).toList(),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    if (role != null) data['role'] = role!.toJson();
    if (groupedPermissions != null) {
      data['grouped_permissions'] = groupedPermissions!.toJson();
    }
    data['assigned'] = assigned;
    return data;
  }
}

class RoleBasic {
  int id;
  String name;

  RoleBasic({required this.id, required this.name});

  factory RoleBasic.fromJson(Map<String, dynamic> json) {
    return RoleBasic(
      id: JsonParser.requireInt(json['id'], model: modelName, field: 'role.id'),
      name: JsonParser.requireString(
        json['name'],
        model: modelName,
        field: 'role.name',
      ),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['name'] = name;
    return data;
  }
}

class GroupedPermissions {
  /// Dynamic list of permission groups — automatically picks up
  /// every key the API returns under `grouped_permissions`.
  final List<PermissionGroup> groups;

  GroupedPermissions({this.groups = const []});

  /// Inactive/unavailable feature permission group keys to exclude.
  static const Set<String> _excludedGroupKeys = {
    'ad_wallet',
    'ad_campaign',
    'ad_campaigns',
    'pos',
    'point_of_sale',
  };

  /// Inactive/unavailable feature permission group names to exclude.
  static const Set<String> _excludedGroupNames = {
    'ad wallet',
    'ad campaigns',
    'ad campaign',
    'point of sale',
    'point of sale (pos)',
    'pos',
  };

  /// Inactive/unavailable feature permission prefixes.
  static const List<String> _excludedPrefixes = [
    'ad_wallet.',
    'ad_campaign.',
    'pos.',
  ];

  static bool isExcludedGroup(String key, String name, List<String> permissions) {
    final k = key.trim().toLowerCase();
    final n = name.trim().toLowerCase();
    if (_excludedGroupKeys.contains(k) || _excludedGroupNames.contains(n)) {
      return true;
    }
    if (permissions.isNotEmpty &&
        permissions.every((p) => _excludedPrefixes.any((prefix) => p.startsWith(prefix)))) {
      return true;
    }
    return false;
  }

  static bool isExcludedPermission(String permission) {
    final p = permission.trim().toLowerCase();
    return _excludedPrefixes.any((prefix) => p.startsWith(prefix));
  }

  factory GroupedPermissions.fromJson(Map<String, dynamic> json) {
    final List<PermissionGroup> parsedGroups = [];
    for (final entry in json.entries) {
      // Nested JSON sometimes surfaces as Map<dynamic, dynamic> depending
      // on how the response was decoded, so widen the check and normalise
      // to Map<String, dynamic> before handing it to PermissionGroup.
      final raw = entry.value;
      if (raw is Map) {
        final group = PermissionGroup.fromJson(
          Map<String, dynamic>.from(raw),
          key: entry.key,
        );
        if (!isExcludedGroup(group.key, group.name, group.permissions)) {
          parsedGroups.add(group);
        }
      }
    }
    return GroupedPermissions(groups: parsedGroups);
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    for (final group in groups) {
      data[group.key] = group.toJson();
    }
    return data;
  }
}

class PermissionGroup {
  /// The original JSON key (e.g. 'ad_wallet', 'store', 'pos').
  String key;
  String name;
  List<String> permissions;

  PermissionGroup({
    required this.key,
    required this.name,
    this.permissions = const [],
  });

  factory PermissionGroup.fromJson(Map<String, dynamic> json, {String key = ''}) {
    return PermissionGroup(
      key: key,
      name: JsonParser.string(json['name'] ?? ''),
      permissions: JsonParser.list<String>(
        json['permissions'],
        (v) => JsonParser.string(v),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['name'] = name;
    data['permissions'] = permissions;
    return data;
  }
}
