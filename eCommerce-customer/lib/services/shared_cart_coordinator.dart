import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce/bloc/user_cart_bloc/user_cart_bloc.dart';
import 'package:hyper_commerce/bloc/user_cart_bloc/user_cart_event.dart';
import 'package:hyper_commerce/config/global.dart';
import 'package:hyper_commerce/screens/cart_page/bloc/get_user_cart/get_user_cart_bloc.dart';
import 'package:hyper_commerce/screens/cart_page/repo/cart_repository.dart';
import 'package:hyper_commerce/screens/cart_page/widgets/shared_cart_bottom_sheet.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:hyper_commerce/utils/widgets/custom_toast.dart';

/// Coordinator that retains pending shared cart code and action across app lifecycle,
/// cold-start splash gates, and user authentication redirects.
class SharedCartCoordinator {
  SharedCartCoordinator._();

  static final ValueNotifier<String?> pendingCartNotifier =
      ValueNotifier<String?>(null);

  static String? _pendingCartCode;
  static String? _pendingAction;

  /// Returns the currently pending shared cart code without consuming it.
  static String? get pendingCartCode => _pendingCartCode;

  /// Returns the pending action ('merge' or 'replace') if any.
  static String? get pendingAction => _pendingAction;

  /// Returns true if a shared cart code is waiting to be imported or viewed.
  static bool get hasPending =>
      _pendingCartCode != null && _pendingCartCode!.isNotEmpty;

  /// Stores a pending shared cart code and optional action.
  static void setPending(String code, {String? action}) {
    final trimmed = code.trim();
    if (trimmed.isEmpty) return;
    _pendingCartCode = trimmed;
    _pendingAction = action;
    pendingCartNotifier.value = trimmed;
    debugPrint('[SharedCartCoordinator] Pending code set: $trimmed (action: $action)');
  }

  /// Consumes and returns the pending cart code and action, atomically clearing them.
  static ({String code, String? action})? consume() {
    final code = _pendingCartCode;
    if (code == null || code.isEmpty) return null;
    final action = _pendingAction;
    clear();
    debugPrint('[SharedCartCoordinator] Consumed code: $code (action: $action)');
    return (code: code, action: action);
  }

  /// Clears the pending code and action.
  static void clear() {
    _pendingCartCode = null;
    _pendingAction = null;
    pendingCartNotifier.value = null;
  }

  /// Automatically resumes an import if the user has authenticated after clicking
  /// "Add to Cart" or "Combine / Replace" on a shared cart link.
  static Future<void> checkAndResumePendingImport(BuildContext context) async {
    if (!hasPending) return;

    final token = Global.token ?? Global.userData?.token;
    if (token == null || token.isEmpty) {
      // User is still a guest; do nothing until they log in.
      return;
    }

    final pending = consume();
    if (pending == null) return;

    if (pending.action != null && pending.action!.isNotEmpty) {
      // Automatically execute import with user's selected action
      try {
        if (pending.action == 'replace') {
          try {
            context.read<CartBloc>().add(ClearCart(context: context));
            await Future.delayed(const Duration(milliseconds: 300));
          } catch (_) {}
        }

        final repository = CartRepository();
        final result = await repository.importSharedList(
          pending.code,
          action: 'merge',
        );

        if (context.mounted) {
          try {
            context.read<GetUserCartBloc>().add(FetchUserCart(isRefresh: false));
          } catch (_) {}
          try {
            context.read<CartBloc>().add(LoadCart());
          } catch (_) {}

          final failedItems = result.data?.failedItems ?? const [];
          if (failedItems.isNotEmpty) {
            ToastManager.show(
              context: context,
              message: context.tr(
                'someItemsOutOfStock',
                fallback:
                    '${failedItems.length} items were out of stock and skipped.',
              ),
              type: ToastType.warning,
            );
          } else {
            ToastManager.show(
              context: context,
              message: result.message ??
                  context.tr('sharedCartAddedSuccessfully',
                      fallback: 'Shared cart items added to your cart.'),
              type: ToastType.success,
            );
          }

          try {
            final currentLoc = GoRouterState.of(context).matchedLocation;
            if (currentLoc != AppRoutes.cart) {
              context.push(AppRoutes.cart);
            }
          } catch (_) {
            context.push(AppRoutes.cart);
          }
        }
      } catch (e) {
        if (context.mounted) {
          ToastManager.show(
            context: context,
            message: e.toString(),
            type: ToastType.error,
          );
        }
      }
    } else {
      // No action was chosen yet, re-open bottom sheet for the logged-in user
      SharedCartBottomSheet.show(context, token: pending.code);
    }
  }
}
