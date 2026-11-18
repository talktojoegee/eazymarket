import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_status_chip.dart';
import 'package:intl/intl.dart';

class ProductAddonCard extends StatelessWidget {
  final Map<String, dynamic> data;
  final ScreenType screenType;

  const ProductAddonCard({super.key, required this.data, required this.screenType});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    final String requiredText = data['variant']?.toString() ?? '';
    final String groupTitle = data['group_title']?.toString() ?? '';

    final String selectionType = data['stores_count']?.toString() ?? '';

    String formattedDate = data['created_at']?.toString() ?? "";
    try {
      if (data['created_at'] != null && data['created_at'].toString().isNotEmpty) {
        DateTime dateTime = DateTime.parse(data['created_at'].toString());
        formattedDate = DateFormat('MMM dd, yyyy').format(dateTime);
      }
    } catch (e) {
      // Keep default or passed date
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Title
        Text(
          data['title']?.toString() ?? '',
          style: TextStyle(
            fontSize: UIUtils.tileTitle(screenType),
            fontWeight: FontWeight.w600,
            color: colorScheme.onSurface,
          ),
        ),
        SizedBox(height: UIUtils.gapLG(screenType)),

        // Info Rows
        _buildInfoRow(context, '${context.tr('variant', fallback: 'Variant')}:', requiredText, Colors.orange),
        SizedBox(height: UIUtils.gapMD(screenType)),
        _buildInfoRow(context, '${context.tr('addOnGroup', fallback: 'Add-on Groups')}:', groupTitle, Colors.green),
        SizedBox(height: UIUtils.gapMD(screenType)),
        _buildInfoRow(context, '${context.tr('stores', fallback: 'Stores')}:', selectionType, Colors.green),

        SizedBox(height: UIUtils.gapXL(screenType)),

        // Footer
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              "${data['items_count'] ?? 0} ${context.tr('totalItems', fallback: 'Total Items')}",
              style: TextStyle(fontSize: UIUtils.body(screenType), color: colorScheme.onSurfaceVariant),
            ),
            Text(
              formattedDate,
              style: TextStyle(fontSize: UIUtils.body(screenType), color: colorScheme.onSurfaceVariant),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildInfoRow(BuildContext context, String label, String value, Color chipColor) {
    return Row(
      children: [
        SizedBox(
          width: 120, // Fixed width for alignment
          child: Text(
            label,
            style: TextStyle(fontSize: UIUtils.body(screenType), color: Theme.of(context).colorScheme.onSurfaceVariant),
          ),
        ),
        Flexible(
          child: CustomStatusChip(label: value, baseColor: chipColor, screenType: screenType),
        ),
      ],
    );
  }
}
