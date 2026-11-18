import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_styled_toast/flutter_styled_toast.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/router/app_routes.dart';

import 'custom_snack_bar.dart';

enum ToastType { success, error, warning, info, authGuard }

class ToastManager {
  static void show({
    required BuildContext context,
    required String message,
    bool fromAuthGuard = false,
    ToastType? type,
    bool showIcon = true,
    Color? backgroundColor,
    Color? textColor,
    double fontSize = 14.5,
    double borderRadius = 14.0,
    EdgeInsets padding =
        const EdgeInsets.symmetric(horizontal: 14.0, vertical: 12.0),
    StyledToastPosition position = StyledToastPosition.bottom,
    Duration duration = const Duration(seconds: 4),
  }) {
    if (message.trim().isEmpty) return;

    final isAuth = fromAuthGuard || type == ToastType.authGuard;
    final snackBarType = isAuth
        ? CustomSnackBarType.authGuard
        : switch (type) {
            ToastType.success => CustomSnackBarType.success,
            ToastType.error => CustomSnackBarType.error,
            ToastType.warning => CustomSnackBarType.warning,
            ToastType.info => CustomSnackBarType.info,
            ToastType.authGuard => CustomSnackBarType.authGuard,
            null => CustomSnackBarType.info,
          };

    String? actionLabel;
    VoidCallback? onAction;

    if (isAuth) {
      actionLabel = context.tr('signIn', fallback: 'Sign In');
      onAction = () {
        GoRouter.of(context).go(AppRoutes.login);
      };
    }

    CustomSnackBar.show(
      context: context,
      message: message,
      type: snackBarType,
      duration: type == ToastType.error
          ? const Duration(seconds: 5)
          : (isAuth ? const Duration(seconds: 5) : duration),
      actionLabel: actionLabel,
      onAction: onAction,
      backgroundColor: backgroundColor,
      textColor: textColor,
    );
  }
}
