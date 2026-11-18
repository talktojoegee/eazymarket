// ignore_for_file: deprecated_member_use

import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/utils/time_utils.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';

// Typography scale — mirrors the one in order_details_page.dart.
class _T {
  _T._();
  static TextStyle get subtitle => _b(14, FontWeight.w700);
  static TextStyle get body => _b(13, FontWeight.w600);
  static TextStyle get meta => _b(12, FontWeight.w500);
  static TextStyle get micro => _b(11, FontWeight.w800, letter: 0.08);
  static TextStyle _b(double s, FontWeight w, {double letter = 0}) =>
      TextStyle(
        fontFamily: AppConstants.fontFamily,
        fontSize: s,
        fontWeight: w,
        letterSpacing: letter,
        height: 1.35,
      );
}

/// Card used in the orders list.
class OrderCard extends StatelessWidget {
  final Map<String, dynamic> data;
  final ScreenType screenType;
  final Function(String status)? onStatusUpdate;

  const OrderCard({
    super.key,
    required this.data,
    required this.screenType,
    this.onStatusUpdate,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    final status = (data['fulfillment_status'] ?? '').toString().toLowerCase();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Header — image + title + status chip
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildImage(),
            SizedBox(width: UIUtils.gapMD(screenType)),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    data['title'] ?? '',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: _T.subtitle.copyWith(color: tertiary),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    "${context.tr('qtyWithCount', positionalArgs: [data['quantity']], fallback: "Qty ${data['quantity']}")} · ${data['subtotal']}",
                    style: _T.meta.copyWith(
                      color: tertiary.withValues(alpha: 0.6),
                    ),
                  ),
                  // const SizedBox(height: 8),
                  // _StatusChip(text: status),
                ],
              ),
            ),
          ],
        ),

        SizedBox(height: UIUtils.gapMD(screenType)),
        Divider(
          height: 1,
          thickness: 1,
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
        SizedBox(height: UIUtils.gapMD(screenType)),

        // Delivery + placed
        Row(
          children: [
            Expanded(
              child: _MetaBlock(
                label: context.tr('delivery'),
                icon: TablerIcons.truck_delivery,
                value: data['isRushOrder'] == true
                    ? (context.tr('rushDelivery', fallback: 'Rush delivery'))
                    : (context.tr('standardDelivery', fallback: 'Standard delivery')),
                emphasise: data['isRushOrder'] == true,
                emphasiseColor: Colors.orange.shade700,
              ),
            ),
            Container(
              width: 1,
              height: 32,
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(left: 12),
                child: _MetaBlock(
                  label: context.tr('placed'),
                  icon: TablerIcons.clock,
                  value: TimeUtils.formatTimeAgo(
                    data['created_at'],
                    context,
                    type: TimeFormatType.order,
                  ),
                ),
              ),
            ),
          ],
        ),

        // Action buttons
        if (status == 'pending' ||
            status == 'awaiting_store_response' ||
            status == 'partially_accepted') ...[
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: SecondaryButton(
                  text: context.tr('reject', fallback: 'Reject'),
                  onPressed: () => onStatusUpdate?.call('reject'),
                  foregroundColor: AppColors.errorColor,
                  borderColor: AppColors.errorColor,
                  borderRadius: 12,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: PrimaryButton(
                  text: context.tr('accept', fallback: 'Accept'),
                  onPressed: () => onStatusUpdate?.call('accept'),
                  backgroundColor: Colors.green.shade600,
                  foregroundColor: Colors.white,
                  borderRadius: 12,
                ),
              ),
            ],
          ),
        ] else if (status == 'accepted' &&
            data['fulfillment_method'] == 'manual') ...[
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: PrimaryButton(
              text: context.tr('markInProgress', fallback: 'Mark in progress'),
              onPressed: () => onStatusUpdate?.call('in_progress'),
              backgroundColor: AppColors.primaryColor,
              foregroundColor: Colors.black,
              borderRadius: 12,
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildImage() {
    return ClipRRect(
      borderRadius: BorderRadius.circular(UIUtils.radiusSM(screenType)),
      child: CustomNetworkImage(
        imageUrl: data['image'] ?? '',
        width: UIUtils.avatarXL(screenType),
        height: UIUtils.avatarXL(screenType),
        fit: BoxFit.cover,
      ),
    );
  }
}

class _MetaBlock extends StatelessWidget {
  final String label;
  final IconData icon;
  final String value;
  final bool emphasise;
  final Color? emphasiseColor;

  const _MetaBlock({
    required this.label,
    required this.icon,
    required this.value,
    this.emphasise = false,
    this.emphasiseColor,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: _T.micro.copyWith(
            color: tertiary.withValues(alpha: 0.55),
          ),
        ),
        const SizedBox(height: 4),
        Row(
          children: [
            Icon(
              icon,
              size: 14,
              color: emphasise
                  ? (emphasiseColor ?? tertiary)
                  : tertiary.withValues(alpha: 0.6),
            ),
            const SizedBox(width: 5),
            Expanded(
              child: Text(
                value,
                style: _T.body.copyWith(
                  fontWeight: FontWeight.w700,
                  color: emphasise
                      ? (emphasiseColor ?? tertiary)
                      : tertiary,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ],
    );
  }
}