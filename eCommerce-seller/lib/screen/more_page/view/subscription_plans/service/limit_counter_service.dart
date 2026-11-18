import 'package:flutter/widgets.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

class LimitCounterService {
  String formatDuration(
    dynamic context,
    String durationType,
    int? durationDays,
  ) {
    if (durationType == 'unlimited') {
      if (context is BuildContext) {
        return context.tr('durationUnlimited', fallback: 'Unlimited');
      }
      return 'Unlimited';
    }
    if (durationDays != null) {
      if (context is BuildContext) {
        return context.tr(
          'durationPerXDays',
          positionalArgs: [durationDays],
          fallback: '/ $durationDays days',
        );
      }
      return '/ $durationDays days';
    }
    return durationType;
  }

  String getStoresFeatureText(int? rawLimit, [dynamic context]) {
    final limit = rawLimit ?? -1;

    if (limit < 0) {
      if (context is BuildContext) {
        return context.tr('featureStoresUnlimited', fallback: 'Unlimited Stores');
      }
      return 'Unlimited Stores';
    }

    if (context is BuildContext) {
      return context.tr(
        'featureStores',
        positionalArgs: [limit],
        fallback: 'Up to $limit ${limit > 1 ? 'Stores' : 'Store'}',
      );
    }
    return 'Up to $limit ${limit > 1 ? 'Stores' : 'Store'}';
  }

  String getProductFeatureText(int? rawLimit, [dynamic context]) {
    final limit = rawLimit ?? -1;

    if (limit < 0) {
      if (context is BuildContext) {
        return context.tr('featureProductsUnlimited', fallback: 'Unlimited Products');
      }
      return 'Unlimited Products';
    }

    if (context is BuildContext) {
      return context.tr(
        'featureProducts',
        positionalArgs: [limit],
        fallback: 'Up to $limit ${limit > 1 ? 'Products' : 'Product'}',
      );
    }
    return 'Up to $limit ${limit > 1 ? 'Products' : 'Product'}';
  }

  String getRolesFeatureText(int? rawLimit, [dynamic context]) {
    final limit = rawLimit ?? -1;

    if (limit < 0) {
      if (context is BuildContext) {
        return context.tr('featureRolesUnlimited', fallback: 'Unlimited Roles');
      }
      return 'Unlimited Roles';
    }

    if (context is BuildContext) {
      return context.tr(
        'featureRoles',
        positionalArgs: [limit],
        fallback: 'Up to $limit ${limit > 1 ? 'Roles' : 'Role'}',
      );
    }
    return 'Up to $limit ${limit > 1 ? 'Roles' : 'Role'}';
  }

  String getSystemUserFeatureText(int? rawLimit, [dynamic context]) {
    final limit = rawLimit ?? -1;

    if (limit < 0) {
      if (context is BuildContext) {
        return context.tr('featureUsersUnlimited', fallback: 'Unlimited System Users');
      }
      return 'Unlimited System Users';
    }

    if (context is BuildContext) {
      return context.tr(
        'featureUsers',
        positionalArgs: [limit],
        fallback: 'Up to $limit ${limit > 1 ? 'System Users' : 'System User'}',
      );
    }
    return 'Up to $limit ${limit > 1 ? 'System Users' : 'System User'}';
  }
}
