// ignore_for_file: deprecated_member_use

import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';

class AppTheme {
  /// Light Theme
  static ThemeData lightTheme = ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: ColorScheme.light(
      surface: AppColors.mainLightBackgroundColor,
      surfaceContainer: AppColors.mainLightBackgroundColor2,
      primary: AppColors.mainLightContainerBgColor,
      onPrimary: AppColors.lightProductCardColor,
      onSecondary: AppColors.lightSubCategoryCardColor,
      secondary: AppColors.lightSecondary,
      tertiary: AppColors.lightTertiary,
      outline: AppColors.lightOutline,
      outlineVariant: AppColors.lightOutlineVariant,
      onSecondaryContainer: Colors.grey[700],
    ),
    fontFamily: AppConstants.fontFamily,
    textButtonTheme: TextButtonThemeData(
      style: ButtonStyle(
        foregroundColor: WidgetStateProperty.all(AppColors.lightTertiary),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: ButtonStyle(
        foregroundColor: WidgetStateProperty.all(AppColors.lightTertiary),
      ),
    ),
    textSelectionTheme: TextSelectionThemeData(
      selectionColor: AppColors.primaryColor.withValues(alpha: 0.3),
      selectionHandleColor: AppColors.primaryColor,
      cursorColor: AppColors.primaryColor,
    ),

  );

  /// Dark Theme
  static ThemeData darkTheme = ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    scaffoldBackgroundColor: AppColors.mainDarkBackgroundColor,
    colorScheme: ColorScheme.dark(
      surface: AppColors.mainDarkBackgroundColor,
      surfaceContainer: AppColors.mainDarkContainerBgColor,
      primary: AppColors.mainDarkContainerBgColor,
      onPrimary: AppColors.mainDarkContainerBgColor,
      onSecondary: AppColors.darkSubCategoryCardColor,
      secondary: AppColors.secondaryColor,
      tertiary: AppColors.darkTertiary,
      onSecondaryContainer: const Color(0xFF9E9E9E),
      outline: AppColors.darkOutline,
      outlineVariant: AppColors.darkOutlineVariant,
      error: Colors.red,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: AppColors.mainDarkBackgroundColor,
      foregroundColor: AppColors.darkTertiary,
      elevation: 0,
      scrolledUnderElevation: 0,
      surfaceTintColor: Colors.transparent,
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: Color(0xFF111111),
      selectedItemColor: AppColors.primaryColor,
      unselectedItemColor: Color(0xFF6B6B6B),
      elevation: 0,
      type: BottomNavigationBarType.fixed,
    ),
    cardTheme: CardThemeData(
      color: AppColors.mainDarkContainerBgColor,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: Color(0x1FFFFFFF), width: 0.5),
      ),
      margin: EdgeInsets.zero,
    ),
    dividerTheme: const DividerThemeData(
      color: Color(0x14FFFFFF),
      thickness: 0.5,
      space: 0,
    ),
    inputDecorationTheme: InputDecorationTheme(
      fillColor: AppColors.mainDarkContainerBgColor,
      filled: true,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0x1FFFFFFF), width: 0.5),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0x1FFFFFFF), width: 0.5),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.primaryColor, width: 1),
      ),
      hintStyle: const TextStyle(color: Color(0xFF6B6B6B)),
    ),
    fontFamily: AppConstants.fontFamily,
    textButtonTheme: TextButtonThemeData(
      style: ButtonStyle(
        foregroundColor: WidgetStateProperty.all(AppColors.darkTertiary),
      ),
    ),
    textSelectionTheme: TextSelectionThemeData(
      selectionColor: AppColors.primaryColor.withValues(alpha: 0.3),
      selectionHandleColor: AppColors.primaryColor,
      cursorColor: AppColors.primaryColor,
    ),


  );
}
