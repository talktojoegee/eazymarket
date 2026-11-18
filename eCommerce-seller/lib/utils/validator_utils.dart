import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

class ValidatorUtils {
  static String? validateEmpty(BuildContext context, String? value) {
    if (value == null || value.trim().isEmpty) {
      return context.tr('fieldRequired');
    }
    return null;
  }

  static String? validateEmail(BuildContext context, String? value) {
    if (value == null || value.trim().isEmpty) {
      return context.tr('invalidEmail');
    }
    final emailRegExp = RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$');
    if (!emailRegExp.hasMatch(value)) {
      return context.tr('invalidEmail');
    }
    return null;
  }

  static String? validatePhone(BuildContext context, String? value) {
    if (value == null || value.trim().isEmpty) {
      return context.tr('pleaseEnterYourPhoneNumber');
    }
    final trimmed = value.trim();
    if (trimmed.length < 5) return context.tr('phoneNumberTooShort');
    if (trimmed.length > 16) return context.tr('phoneNumberTooLong');
    if (!RegExp(r'^\d+$').hasMatch(trimmed)) return context.tr('onlyNumbersAllowed');
    return null;
  }

  static String? validatePassword(BuildContext context, String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Please enter valid password';
    }
    if (value.length < 8) {
      return context.tr('passwordTooShort');
    }
    return null;
  }

  static String? validateConfirmPassword(
    BuildContext context,
    String? value,
    String? password,
  ) {
    if (value == null || value.trim().isEmpty) {
      return context.tr('fieldRequired');
    }
    if (value != password) {
      return context.tr('passwordsDoNotMatch');
    }
    return null;
  }

  static String? validateFile(BuildContext context, String? fileName) {
    if (fileName == null || fileName.isEmpty) {
      return context.tr('pleaseSelectFile');
    }
    return null;
  }
}
