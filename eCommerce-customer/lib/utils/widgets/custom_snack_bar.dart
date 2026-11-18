import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/global_keys.dart';
import 'package:hyper_commerce/config/theme.dart';

enum CustomSnackBarType { success, error, warning, info, authGuard }

class CustomSnackBar {
  const CustomSnackBar._();

  static const Color _background = Colors.black87;
  static const Color _textColor = Colors.white;

  static const Color _successColor = Color(0xFF28A745);
  static const Color _errorColor = Color(0xFFDC3545);
  static const Color _warningColor = Color(0xFFFFC107);
  static const Color _infoColor = Color(0xFF17A2B8);

  static ScaffoldFeatureController<SnackBar, SnackBarClosedReason>? show({
    required BuildContext context,
    required String message,
    CustomSnackBarType type = CustomSnackBarType.info,
    Duration duration = const Duration(seconds: 4),
    String? actionLabel,
    VoidCallback? onAction,
    bool clearPrevious = true,
    Color? backgroundColor,
    Color? textColor,
  }) {
    final messenger = ScaffoldMessenger.maybeOf(context) ??
        GlobalKeys.scaffoldMessengerKey.currentState;
    if (messenger == null || message.trim().isEmpty) return null;
    if (clearPrevious) messenger.clearSnackBars();
    return messenger.showSnackBar(
      build(
        message: message,
        type: type,
        duration: duration,
        actionLabel: actionLabel,
        onAction: onAction,
        backgroundColor: backgroundColor,
        textColor: textColor,
      ),
    );
  }

  static SnackBar build({
    required String message,
    CustomSnackBarType type = CustomSnackBarType.info,
    Duration duration = const Duration(seconds: 4),
    String? actionLabel,
    VoidCallback? onAction,
    Color? backgroundColor,
    Color? textColor,
  }) {
    final accent = _accentColor(type);
    final bg = backgroundColor ?? _background;
    final textCol = textColor ?? _textColor;

    return SnackBar(
      behavior: SnackBarBehavior.floating,
      backgroundColor: bg,
      elevation: 8,
      duration: duration,
      dismissDirection: DismissDirection.horizontal,
      margin: EdgeInsets.symmetric(horizontal: 12.w, vertical: 8.h),
      padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 4.h),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8.r),
      ),
      content: Row(
        children: [
          Container(
            width: 30.r,
            height: 30.r,
            alignment: Alignment.center,
            child: Icon(_icon(type), color: accent, size: 18.sp),
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Text(
              message,
              style: AppTextStyles.body.copyWith(
                color: textCol,
                fontWeight: FontWeight.w500,
                height: 1.3,
              ),
            ),
          ),
        ],
      ),
      action: actionLabel != null
          ? SnackBarAction(
              label: actionLabel,
              textColor: accent,
              onPressed: onAction ?? () {},
            )
          : null,
    );
  }

  static Color _accentColor(CustomSnackBarType type) {
    switch (type) {
      case CustomSnackBarType.success:
        return _successColor;
      case CustomSnackBarType.error:
        return _errorColor;
      case CustomSnackBarType.warning:
        return _warningColor;
      case CustomSnackBarType.info:
        return _infoColor;
      case CustomSnackBarType.authGuard:
        return AppTheme.primaryColor;
    }
  }

  static IconData _icon(CustomSnackBarType type) {
    switch (type) {
      case CustomSnackBarType.success:
        return Icons.check_circle_rounded;
      case CustomSnackBarType.error:
        return Icons.error_rounded;
      case CustomSnackBarType.warning:
        return Icons.warning_amber_rounded;
      case CustomSnackBarType.info:
        return Icons.info_rounded;
      case CustomSnackBarType.authGuard:
        return Icons.lock_outline;
    }
  }
}
