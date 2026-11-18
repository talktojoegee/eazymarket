import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_status_chip.dart';
import 'package:intl/intl.dart';


class AddonGroupCard extends StatelessWidget {
  final Map<String, dynamic> data;
  final ScreenType screenType;

  const AddonGroupCard({super.key, required this.data, required this.screenType});

  @override
  Widget build(BuildContext context) {
    // Derived Display values
    final bool isRequired = data['is_required'] ?? false;
    final requiredText = isRequired ? 'Yes' : 'No';
    final requiredColor = isRequired ? Colors.orange : Colors.grey;

    final String status = data['status']?.toString() ?? '';
    final statusText = status.isNotEmpty ? "${status[0].toUpperCase()}${status.substring(1)}" : '';
    final statusColor = status.toLowerCase() == 'active' ? Colors.green : Colors.grey;

    final String selectionType = data['selection_type']?.toString() ?? '';
    final selectionText = selectionType.isNotEmpty
        ? "${selectionType[0].toUpperCase()}${selectionType.substring(1)}"
        : '';
    final selectionColor = selectionType.toLowerCase() == 'active' || selectionType.toLowerCase() == 'multiple'
        ? Colors.green
        : Colors.grey;

    try {
      if (data['created_at'] != null && data['created_at'].toString().isNotEmpty) {
        DateTime _ = DateTime.parse(data['created_at'].toString());
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
          style: TextStyle(fontSize: UIUtils.tileTitle(screenType), fontWeight: FontWeight.w600),
        ),
        SizedBox(height: UIUtils.gapLG(screenType)),

        // Info Rows
        _buildInfoRow('${context.tr('isRequiredLabel', fallback: 'Required')}:', requiredText, requiredColor),
        SizedBox(height: UIUtils.gapMD(screenType)),
        _buildInfoRow('${context.tr('status', fallback: 'Status')}:', statusText, statusColor),
        SizedBox(height: UIUtils.gapMD(screenType)),
        _buildInfoRow('${context.tr('selectionType', fallback: 'Selection Type')}:', selectionText, selectionColor),

        SizedBox(height: UIUtils.gapXL(screenType)),

        // Footer
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              "${data['items_count'] ?? 0} ${context.tr('totalItems', fallback: 'Total Items')}",
              style: TextStyle(fontSize: UIUtils.body(screenType), color: Colors.grey.shade600),
            ),
            // Text(
            //   formattedDate,
            //   style: TextStyle(
            //     fontSize: UIUtils.body(screenType),
            //     color: Colors.grey.shade600,
            //   ),
            // ),
          ],
        ),
      ],
    );
  }

  Widget _buildInfoRow(String label, String value, Color chipColor) {
    return Row(
      children: [
        SizedBox(
          width: 120, // Fixed width for alignment
          child: Text(
            label,
            style: TextStyle(fontSize: UIUtils.body(screenType), color: Colors.grey.shade600),
          ),
        ),
        CustomStatusChip(label: value, baseColor: chipColor, screenType: screenType),
      ],
    );
  }
}
