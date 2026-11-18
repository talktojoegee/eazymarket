import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/repo/store_repo.dart';

/// Central gate that decides whether a signed-in seller may enter the app.
///
/// A seller is allowed in once they have at least one store approved by the
/// admin (`Store.verificationStatus == 'approved'`). Until then they are held
/// on the awaiting-approval screen. The result is cached in [HiveStorage] so a
/// cold start with no network falls back to the last-known status instead of
/// wrongly locking out an already-approved seller.
class SellerGate {
  const SellerGate._();

  /// Fetches the seller's stores and returns whether any is approved,
  /// persisting the result. Falls back to the cached value on failure.
  static Future<bool> refreshApproval() async {
    try {
      final response = await StoresRepo().getStores(page: 1, perPage: 50);
      final stores = StoresResponse.fromJson(response).data?.stores ?? <Store>[];
      final approved = stores.any(
        (s) => (s.verificationStatus ?? '').trim().toLowerCase() == 'approved',
      );
      await HiveStorage.setSellerApproved(approved);
      return approved;
    } catch (_) {
      return HiveStorage.sellerApproved;
    }
  }

  /// Navigation for an approved seller — mirrors the post-login routing
  /// (subscription choose-plan when required, otherwise home).
  static void goApproved(BuildContext context) {
    if (HiveStorage.isSubscriptionAvailable &&
        (HiveStorage.activePlanId == null || HiveStorage.activePlanId == 0) &&
        HiveStorage.pendingSubscriptionId == null) {
      context.go(AppRoutes.subscriptionChoosePlan);
    } else {
      context.go(AppRoutes.home);
    }
  }
}
