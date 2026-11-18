import 'package:flutter/material.dart';

import '../../../utils/widgets/custom_toast.dart';

enum SupportFeedbackType { success, error, warning }

void showSupportFeedback(
  BuildContext context, {
  required String message,
  required SupportFeedbackType type,
}) {
  if (message.trim().isEmpty) return;

  final toastType = switch (type) {
    SupportFeedbackType.success => ToastType.success,
    SupportFeedbackType.error => ToastType.error,
    SupportFeedbackType.warning => ToastType.warning,
  };

  ToastManager.show(
    context: context,
    message: message,
    type: toastType,
  );
}
