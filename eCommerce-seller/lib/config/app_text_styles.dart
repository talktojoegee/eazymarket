import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/constant.dart';

/// Semantic typography scale. Names describe role, not pixels, so the scale can
/// be retuned without touching call sites. Colour is intentionally NOT baked in
/// — apply it at the call site via `.copyWith(color: ...)` so styles stay
/// theme-aware across light/dark.
class AppTextStyles {
  AppTextStyles._();

  static TextStyle get displayLarge => _base(28, FontWeight.w700);
  static TextStyle get headline => _base(22, FontWeight.w700);
  static TextStyle get titleLarge => _base(20, FontWeight.w700);
  static TextStyle get titleMedium => _base(16, FontWeight.w600);
  static TextStyle get body => _base(14, FontWeight.w400);
  static TextStyle get bodySmall => _base(13, FontWeight.w400);
  static TextStyle get label => _base(12, FontWeight.w400);
  static TextStyle get caption => _base(10, FontWeight.w400);

  static TextStyle _base(double size, FontWeight weight) => TextStyle(
        fontSize: size,
        fontWeight: weight,
        fontFamily: AppConstants.fontFamily,
      );
}
