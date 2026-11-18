import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

/// Order confirm dialogs (Accept / Reject / Mark prepared).
///
/// No visual changes to the dialog shell itself (it delegates to the
/// project's `showAppAlertDialog`); this file just centralises the copy
/// and the semantic colours used for each action. Kept as-is because the
/// dialog widget lives in `widgets/custom` and its restyle is a
/// cross-cutting change we'll do separately if needed.
class OrderDialogs {
  static void showAcceptDialog(
    BuildContext context,
    Future<void> Function() onConfirm,
  ) {
    showAppAlertDialog(
      context: context,
      title: context.tr('acceptOrderTitle'),
      message: context.tr('acceptOrderConfirmation'),
      svgPath: ImagesPath.acceptSvg,
      size: 24,
      padding: 24,
      iconColor: Colors.green.shade600,
      confirmColor: Colors.green.shade600,
      confirmText: context.tr('accept'),
      onConfirm: () async {
        await _handleAction(
          context,
          onConfirm,
          context.tr('orderAccepted'),
        );
      },
    );
  }

  static void showRejectDialog(
    BuildContext context,
    Future<void> Function() onConfirm,
  ) {
    showAppAlertDialog(
      context: context,
      title: context.tr('rejectOrderTitle'),
      message: context.tr('rejectOrderConfirmation'),
      svgPath: ImagesPath.rejectSvg,
      iconColor: Colors.red.shade600,
      confirmColor: Colors.red.shade600,
      confirmText: context.tr('reject'),
      onConfirm: () async {
        await _handleAction(
          context,
          onConfirm,
          context.tr('orderRejected'),
        );
      },
    );
  }

  static void showPreparedDialog(
    BuildContext context,
    Future<void> Function() onConfirm,
  ) {
    showAppAlertDialog(
      context: context,
      title: context.tr('markAsPreparedTitle'),
      message: context.tr('markAsPreparedConfirmation'),
      svgPath: ImagesPath.markAsPrepareSvg,
      iconColor: Colors.blue.shade600,
      confirmText: context.tr('markAsPreparedTitle'),
      size: 36,
      padding: 12,
      onConfirm: () async {
        await _handleAction(
          context,
          onConfirm,
          context.tr('orderMarkedAsPrepared'),
        );
      },
    );
  }

  static Future<void> _handleAction(
    BuildContext context,
    Future<void> Function() onConfirm,
    String message,
  ) async {
    final bool allowed = DemoGuard.shouldProceed(context);
    if (!allowed) return;

    _showLoading(context);
    try {
      await onConfirm();
      if (context.mounted) {
        _hideLoading(context);
        showCustomSnackbar(context: context, message: message);
      }
    } catch (e) {
      if (context.mounted) {
        _hideLoading(context);
        showCustomSnackbar(
          context: context,
          message: context.tr(
            'errorWithDetail',
            positionalArgs: [e.toString().replaceAll("Exception: ", "")],
            fallback: "Error: ${e.toString().replaceAll("Exception: ", "")}",
          ),
          isError: true,
        );
      }
    }
  }

  static void _showLoading(BuildContext context) {
    showDialog(
      context: context,
      barrierDismissible: false,
      useRootNavigator: true,
      builder: (context) => const Center(child: CustomLoadingIndicator()),
    );
  }

  static void _hideLoading(BuildContext context) {
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
    }
  }
}
