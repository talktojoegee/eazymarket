import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';

/// Rounded, filled auth input matching the customer app's field styling.
/// Used across the login / forgot-password / OTP / registration screens so the
/// auth flow reads as one system. An optional [label] renders above the field
/// (used by the multi-field registration form); login/OTP omit it.
class AuthTextField extends StatelessWidget {
  final TextEditingController? controller;
  final String? label;
  final bool isRequired;
  final String? hint;
  final IconData? prefixIcon;
  final Widget? suffixIcon;
  final bool obscureText;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onChanged;
  final String? Function(String?)? validator;
  final FocusNode? focusNode;
  final List<TextInputFormatter>? inputFormatters;
  final AutovalidateMode? autovalidateMode;
  final bool enabled;
  final bool readOnly;
  final int maxLines;
  final TextCapitalization textCapitalization;

  const AuthTextField({
    super.key,
    this.controller,
    this.label,
    this.isRequired = false,
    this.hint,
    this.prefixIcon,
    this.suffixIcon,
    this.obscureText = false,
    this.keyboardType,
    this.textInputAction,
    this.onChanged,
    this.validator,
    this.focusNode,
    this.inputFormatters,
    this.autovalidateMode,
    this.enabled = true,
    this.readOnly = false,
    this.maxLines = 1,
    this.textCapitalization = TextCapitalization.none,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final tertiary = theme.colorScheme.tertiary;
    final fillColor = isDark ? AppColors.mainDarkContainerBgColor : Colors.white;
    final borderColor = isDark ? AppColors.darkOutline : Colors.grey.shade200;

    OutlineInputBorder buildBorder(Color color, double width) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: color, width: width),
        );

    final field = TextFormField(
      controller: controller,
      focusNode: focusNode,
      obscureText: obscureText,
      enabled: enabled,
      readOnly: readOnly,
      maxLines: maxLines,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      textCapitalization: textCapitalization,
      onChanged: onChanged,
      validator: validator,
      inputFormatters: inputFormatters,
      autovalidateMode: autovalidateMode ?? AutovalidateMode.onUserInteraction,
      cursorColor: AppColors.primaryColor,
      style: TextStyle(
        fontFamily: AppConstants.fontFamily,
        fontSize: 15,
        color: tertiary,
      ),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(
          fontFamily: AppConstants.fontFamily,
          color: Colors.grey.shade500,
          fontSize: 15,
          fontWeight: FontWeight.w400,
        ),
        prefixIcon: prefixIcon != null
            ? Icon(prefixIcon, color: Colors.grey.shade500, size: 20)
            : null,
        suffixIcon: suffixIcon,
        filled: true,
        fillColor: fillColor,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: buildBorder(borderColor, 1.2),
        enabledBorder: buildBorder(borderColor, 1.2),
        focusedBorder: buildBorder(AppColors.primaryColor, 1.5),
        errorBorder: buildBorder(AppColors.errorColor, 1.2),
        focusedErrorBorder: buildBorder(AppColors.errorColor, 1.5),
        disabledBorder: buildBorder(borderColor, 1.2),
      ),
    );

    if (label == null) return field;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        RichText(
          text: TextSpan(
            text: label,
            style: TextStyle(
              fontFamily: AppConstants.fontFamily,
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: isDark ? Colors.grey.shade300 : const Color(0xFF5A6270),
            ),
            children: [
              if (isRequired)
                const TextSpan(
                  text: ' *',
                  style: TextStyle(color: Color(0xFFE0483A), fontWeight: FontWeight.w700),
                ),
            ],
          ),
        ),
        const SizedBox(height: 7),
        field,
      ],
    );
  }
}
