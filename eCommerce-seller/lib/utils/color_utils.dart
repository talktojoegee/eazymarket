import 'package:flutter/material.dart';

/// Parses a hex color string (e.g. "#FFB616", "FFB616", "#FFFFB616") into a [Color].
/// Returns null if [hex] is null, empty, or an invalid format.
Color? hexStringToColor(String? hex) {
  if (hex == null) return null;
  var value = hex.trim();
  if (value.isEmpty) return null;

  if (value.startsWith('#')) {
    value = value.substring(1);
  }

  if (value.length == 6) {
    // RRGGBB -> AARRGGBB with alpha FF
    value = 'FF$value';
  } else if (value.length == 8) {
    // already AARRGGBB
  } else {
    return null;
  }

  try {
    final intColor = int.parse(value, radix: 16);
    return Color(intColor);
  } catch (_) {
    return null;
  }
}
