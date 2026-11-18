import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../model/product_compare_model.dart';

class CompareAttributeRowWidget extends StatelessWidget {
  final CompareAttributeRow row;

  const CompareAttributeRowWidget({
    super.key,
    required this.row,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final dividerColor = isDark
        ? Colors.white.withValues(alpha: 0.1)
        : const Color(0xFFEEEEEE);

    final displayA = row.valueA.trim().isEmpty ? 'N/A' : row.valueA;
    final displayB = row.valueB.trim().isEmpty ? 'N/A' : row.valueB;

    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF181818) : Colors.white,
        border: Border(
          bottom: BorderSide(color: dividerColor, width: 0.8),
        ),
      ),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Left Column (Product 1)
            Expanded(
              child: Container(
                padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 12.h),
                alignment: Alignment.center,
                child: _buildCellText(displayA, isDark),
              ),
            ),

            // Vertical Column Divider
            VerticalDivider(
              color: dividerColor,
              width: 1,
              thickness: 0.8,
            ),

            // Right Column (Product 2)
            Expanded(
              child: Container(
                padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 12.h),
                alignment: Alignment.center,
                child: _buildCellText(displayB, isDark),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCellText(String text, bool isDark) {
    final isNA = text == 'N/A' || text == '-' || text == 'None';

    return Text(
      text,
      textAlign: TextAlign.center,
      style: TextStyle(
        fontSize: 11.5.sp,
        fontWeight: isNA ? FontWeight.w400 : FontWeight.w500,
        height: 1.35,
        color: isNA
            ? (isDark ? Colors.white38 : const Color(0xFF9E9E9E))
            : (isDark ? Colors.white : const Color(0xFF212121)),
      ),
    );
  }
}

class CompareSectionHeaderWidget extends StatelessWidget {
  final String title;

  const CompareSectionHeaderWidget({
    super.key,
    required this.title,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(vertical: 9.h, horizontal: 16.w),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF242424) : const Color(0xFFF1F3F6),
        border: Border(
          top: BorderSide(
            color: isDark ? Colors.white.withValues(alpha: 0.1) : const Color(0xFFE0E0E0),
            width: 0.8,
          ),
          bottom: BorderSide(
            color: isDark ? Colors.white.withValues(alpha: 0.1) : const Color(0xFFE0E0E0),
            width: 0.8,
          ),
        ),
      ),
      child: Text(
        title,
        textAlign: TextAlign.center,
        style: TextStyle(
          fontSize: 12.sp,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.2,
          color: isDark ? Colors.white70 : const Color(0xFF717478),
        ),
      ),
    );
  }
}
