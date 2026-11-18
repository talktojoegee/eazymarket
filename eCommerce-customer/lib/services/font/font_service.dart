import 'dart:developer';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:hive_flutter/hive_flutter.dart';

class FontService {
  FontService._();

  static const String _fontBoxKey = 'appFontFamily';
  static const String defaultFontFamily = 'Figtree';

  static String _currentFont = defaultFontFamily;

  /// Reactive notifier to trigger app rebuild when the font changes.
  static final ValueNotifier<String> fontNotifier =
      ValueNotifier<String>(defaultFontFamily);

  /// Currently active human-readable font name (e.g., 'Figtree', 'Inter', 'Poppins').
  static String get currentFont => _currentFont;

  /// Synchronously loads the persisted font from Hive during AppBootstrap.
  /// No API call is needed on app start / restart.
  static void init() {
    try {
      if (Hive.isBoxOpen('themebox')) {
        final box = Hive.box('themebox');
        final saved = box.get(_fontBoxKey, defaultValue: defaultFontFamily) as String?;
        _currentFont = sanitizeFontName(saved);
        fontNotifier.value = _currentFont;
        log('🔤 FontService initialized with persisted font: $_currentFont');
      } else {
        _currentFont = defaultFontFamily;
        fontNotifier.value = defaultFontFamily;
      }
    } catch (e) {
      log('⚠️ FontService init fallback: $e');
      _currentFont = defaultFontFamily;
      fontNotifier.value = defaultFontFamily;
    }
  }

  /// Sanitizes raw string and checks case-insensitively against GoogleFonts.
  /// Falls back to [defaultFontFamily] if not found or invalid.
  static String sanitizeFontName(String? raw) {
    if (raw == null || raw.trim().isEmpty) return defaultFontFamily;
    final clean = raw.trim();

    if (clean.toLowerCase() == defaultFontFamily.toLowerCase()) {
      return defaultFontFamily;
    }

    try {
      final googleFontMap = GoogleFonts.asMap();
      final cleanLower = clean.toLowerCase();

      // Direct case-insensitive match
      for (final key in googleFontMap.keys) {
        if (key.toLowerCase() == cleanLower) {
          return key; // Returns exact casing expected by GoogleFonts (e.g. "Inter")
        }
      }

      // Normalized match (handling space/hyphen/underscore variations)
      final normalized = cleanLower.replaceAll(RegExp(r'[\s\-_]'), '');
      for (final key in googleFontMap.keys) {
        if (key.toLowerCase().replaceAll(RegExp(r'[\s\-_]'), '') == normalized) {
          return key;
        }
      }
    } catch (e) {
      log('⚠️ Error searching GoogleFonts map: $e');
    }

    return defaultFontFamily;
  }

  /// Checks if the incoming font is different from current font.
  /// If different: saves to Hive and notifies the app to rebuild.
  /// If identical: performs no action.
  static Future<bool> applyFontIfChanged(String? newFontFamily) async {
    if (newFontFamily == null || newFontFamily.trim().isEmpty) return false;

    final resolvedFont = sanitizeFontName(newFontFamily);

    // If identical, do nothing (no disk writes, no rebuild)
    if (resolvedFont.toLowerCase() == _currentFont.toLowerCase()) {
      return false;
    }

    log('🎨 Font change detected: $_currentFont -> $resolvedFont');
    _currentFont = resolvedFont;
    fontNotifier.value = resolvedFont;

    try {
      if (Hive.isBoxOpen('themebox')) {
        final box = Hive.box('themebox');
        await box.put(_fontBoxKey, resolvedFont);
      }
    } catch (e) {
      log('⚠️ Error persisting font to themebox: $e');
    }

    return true;
  }

  /// Provides TextStyle for typography with Figtree asset fallback.
  static TextStyle getTextStyle({
    required double fontSize,
    required FontWeight fontWeight,
    Color? color,
    double? height,
    TextDecoration? decoration,
    double? letterSpacing,
  }) {
    if (_currentFont == defaultFontFamily) {
      return TextStyle(
        fontFamily: defaultFontFamily,
        fontSize: fontSize,
        fontWeight: fontWeight,
        color: color,
        height: height,
        decoration: decoration,
        letterSpacing: letterSpacing,
      );
    }

    try {
      return GoogleFonts.getFont(
        _currentFont,
        fontSize: fontSize,
        fontWeight: fontWeight,
        color: color,
        height: height,
        decoration: decoration,
        letterSpacing: letterSpacing,
        textStyle: TextStyle(
          fontFamily: defaultFontFamily,
          fontSize: fontSize,
          fontWeight: fontWeight,
          color: color,
          height: height,
          decoration: decoration,
          letterSpacing: letterSpacing,
        ),
      );
    } catch (_) {
      return TextStyle(
        fontFamily: defaultFontFamily,
        fontSize: fontSize,
        fontWeight: fontWeight,
        color: color,
        height: height,
        decoration: decoration,
        letterSpacing: letterSpacing,
      );
    }
  }

  /// Resolves the engine-registered font family string.
  static String getFontFamily() {
    if (_currentFont == defaultFontFamily) return defaultFontFamily;
    try {
      return GoogleFonts.getFont(_currentFont).fontFamily ?? defaultFontFamily;
    } catch (_) {
      return defaultFontFamily;
    }
  }

  /// Resolves font family fallback list.
  static List<String> getFontFamilyFallback() {
    if (_currentFont == defaultFontFamily) return const [defaultFontFamily];
    try {
      return GoogleFonts.getFont(_currentFont).fontFamilyFallback ??
          const [defaultFontFamily];
    } catch (_) {
      return const [defaultFontFamily];
    }
  }

  /// Applies the Google Font textTheme onto ThemeData's base text theme.
  static TextTheme applyTextTheme(TextTheme base) {
    if (_currentFont == defaultFontFamily) return base;
    try {
      return GoogleFonts.getTextTheme(_currentFont, base);
    } catch (_) {
      return base;
    }
  }
}
