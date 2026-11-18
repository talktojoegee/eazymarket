import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:intl/intl.dart';

enum TimeFormatType {
  store,
  product,
  order,
  attribute,
  attributeValues,
  wallet,
  transactions,
  earnings
}

class TimeUtils {
  static String formatTimeAgo(
    String? dateStr,
    BuildContext context, {
    TimeFormatType type = TimeFormatType.product,
  }) {
    if (dateStr == null || dateStr.isEmpty) return "";

    try {
      final DateTime? dateTime = _parseDate(dateStr);
      if (dateTime == null) return dateStr;

      final DateTime now = DateTime.now();
      final Duration difference = now.difference(dateTime);

      // Handle "time ago" logic
      if (type == TimeFormatType.store) {
        // For store, if more than 7 days, show actual date
        if (difference.inDays > 7) {
          return DateFormat('d MMM, yyyy').format(dateTime);
        }
      }

      if (type == TimeFormatType.earnings) {
        return DateFormat('dd-MM-yyyy').format(dateTime);
      }

      // Default relative time logic
      if (difference.inSeconds < 60) {
        return context.tr('justNow', fallback: 'Just now');
      } else if (difference.inMinutes < 60) {
        return context.tr(
          'minutesAgo',
          positionalArgs: [difference.inMinutes],
          fallback: '${difference.inMinutes}m ago',
        );
      } else if (difference.inHours < 24) {
        return context.tr(
          'hoursAgo',
          positionalArgs: [difference.inHours],
          fallback: '${difference.inHours}h ago',
        );
      } else if (difference.inDays < 30) {
        return context.tr(
          'daysAgo',
          positionalArgs: [difference.inDays],
          fallback: '${difference.inDays}d ago',
        );
      } else {
        // Fallback for very old dates
        return DateFormat('d MMM, yyyy').format(dateTime);
      }
    } catch (e) {
      debugPrint("Error formatting date: $e");
      return dateStr;
    }
  }

  static DateTime? _parseDate(String dateStr) {
    // 1. Try ISO 8601
    try {
      return DateTime.parse(dateStr);
    } catch (_) {}

    // 2. Try "d MMM, yyyy" (e.g., "26 Jan, 2026")
    try {
      return DateFormat('d MMM, yyyy').parse(dateStr);
    } catch (_) {}

    // 3. Try "EEEE, d MMM" (e.g., "Monday, 26 Jan") - assuming current year
    try {
      DateTime parsed = DateFormat('EEEE, d MMM').parse(dateStr);
      return DateTime(DateTime.now().year, parsed.month, parsed.day);
    } catch (_) {}

    // 4. Try "d MMM" (e.g., "26 Jan") - assuming current year
    try {
      DateTime parsed = DateFormat('d MMM').parse(dateStr);
      return DateTime(DateTime.now().year, parsed.month, parsed.day);
    } catch (_) {}

    return null;
  }
}
