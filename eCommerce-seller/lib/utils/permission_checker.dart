import 'package:flutter/foundation.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';

class PermissionChecker {
  /// Checks if the current user has the specified permission.
  /// Reads from `HiveStorage.userData['assigned_permissions']`, which is
  /// populated from the login/profile API by `AuthRepository`.
  ///
  /// Returns `true` for an empty [permissionName] so a call site can pass
  /// a nullable / optional gate without adding its own null check.
  static bool hasPermission(String permissionName) {
    if (permissionName.isEmpty) return true;

    try {
      final userData = HiveStorage.userData;
      if (userData == null) return false;

      final raw = userData['assigned_permissions'];
      if (raw is! List) return false;

      // Hive round-trips give back `List<dynamic>` even if the source was
      // `List<String>`, so a bare `List<String>.from(raw)` throws when any
      // element isn't already a String. Compare per-element via toString().
      for (final entry in raw) {
        if (entry?.toString() == permissionName) return true;
      }
      return false;
    } catch (e) {
      if (kDebugMode) {
        debugPrint('PermissionChecker error for "$permissionName": $e');
      }
      // Fail closed — deny on parse error so a corrupt cache never grants
      // access to something the user shouldn't have.
      return false;
    }
  }

  /// Convenience: true when the user has *any* of the supplied permissions.
  static bool hasAny(Iterable<String> permissions) {
    for (final p in permissions) {
      if (hasPermission(p)) return true;
    }
    return false;
  }

  /// Convenience: true when the user has *all* of the supplied permissions.
  static bool hasAll(Iterable<String> permissions) {
    for (final p in permissions) {
      if (!hasPermission(p)) return false;
    }
    return true;
  }
}
