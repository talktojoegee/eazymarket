// order_acceptance_bottom_sheet.dart
import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

// Typography scale — mirrors the one in order_details_page.dart.
class _T {
  _T._();
  static TextStyle get display => _b(28, FontWeight.w800);
  static TextStyle get title => _b(15, FontWeight.w700);
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

/// Push-triggered order acceptance bottom sheet.
class OrderAcceptanceBottomSheet extends StatefulWidget {
  final int orderId;
  final String orderIdText;
  final String productImage;
  final String productTitle;
  final int quantity;
  final String price;
  final String deliveryType;
  final String totalPrice;
  final String paymentMethod;
  final VoidCallback onViewOrder;
  final int timeoutSeconds;
  final String currency;

  const OrderAcceptanceBottomSheet({
    super.key,
    required this.orderId,
    required this.orderIdText,
    required this.productImage,
    required this.productTitle,
    required this.quantity,
    required this.price,
    required this.deliveryType,
    required this.totalPrice,
    required this.paymentMethod,
    required this.onViewOrder,
    this.timeoutSeconds = 30,
    required this.currency,
  });

  @override
  State<OrderAcceptanceBottomSheet> createState() =>
      _OrderAcceptanceBottomSheetState();
}

class _OrderAcceptanceBottomSheetState
    extends State<OrderAcceptanceBottomSheet> {
  late int _remainingSeconds;
  Timer? _timer;
  bool _isProcessing = false;

  @override
  void initState() {
    super.initState();
    _remainingSeconds = widget.timeoutSeconds;
    _startTimer();
  }

  void _startTimer() {
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_remainingSeconds > 0) {
        setState(() => _remainingSeconds--);
      } else {
        _timer?.cancel();
        if (mounted) Navigator.of(context).pop();
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    final progress = _remainingSeconds / widget.timeoutSeconds;
    final bool isRush = widget.deliveryType.toLowerCase().contains('rush');

    return Container(
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          Padding(
            padding: const EdgeInsets.only(top: 10, bottom: 4),
            child: Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: theme.colorScheme.outlineVariant,
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
            ),
          ),

          // Timer row
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 10),
            child: Row(
              children: [
                Icon(
                  TablerIcons.clock,
                  size: 16,
                  color: tertiary.withValues(alpha: 0.65),
                ),
                const SizedBox(width: 6),
                Text(
                  'VIEW IN',
                  style: _T.micro.copyWith(
                    color: tertiary.withValues(alpha: 0.65),
                  ),
                ),
                const Spacer(),
                Text(
                  '${_remainingSeconds}s',
                  style: _T.display.copyWith(
                    fontSize: 22,
                    color: progress > 0.3
                        ? tertiary
                        : AppColors.errorColor,
                    height: 1.0,
                  ),
                ),
              ],
            ),
          ),

          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 6,
                backgroundColor:
                    theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
                valueColor: AlwaysStoppedAnimation<Color>(
                  progress > 0.3
                      ? AppColors.primaryColor
                      : AppColors.errorColor,
                ),
              ),
            ),
          ),

          const SizedBox(height: 18),

          // Order details
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'ORDER ID',
                  style: _T.micro.copyWith(
                    color: tertiary.withValues(alpha: 0.55),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  widget.orderId.toString(),
                  style: _T.subtitle.copyWith(
                    fontWeight: FontWeight.w800,
                    color: tertiary,
                  ),
                ),
                const SizedBox(height: 14),

                // Product card
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: theme.cardColor,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: theme.colorScheme.outlineVariant
                          .withValues(alpha: 0.6),
                    ),
                  ),
                  child: Row(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: CachedNetworkImage(
                          imageUrl: widget.productImage,
                          width: 60,
                          height: 60,
                          fit: BoxFit.cover,
                          placeholder: (c, u) => Container(
                            width: 60,
                            height: 60,
                            color: theme.colorScheme.outlineVariant
                                .withValues(alpha: 0.4),
                            child: const Center(
                              child:
                                  CustomLoadingIndicator(strokeWidth: 2),
                            ),
                          ),
                          errorWidget: (c, u, e) => Container(
                            width: 60,
                            height: 60,
                            color: theme.colorScheme.outlineVariant
                                .withValues(alpha: 0.4),
                            child: Icon(
                              TablerIcons.package,
                              color: tertiary.withValues(alpha: 0.5),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.productTitle,
                              style: _T.subtitle.copyWith(color: tertiary),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Qty ${widget.quantity} × ${widget.currency}${widget.price}',
                              style: _T.meta.copyWith(
                                color: tertiary.withValues(alpha: 0.6),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 14),

                _row(
                  context,
                  'Delivery',
                  widget.deliveryType,
                  emphasise: isRush,
                  emphasiseColor: Colors.orange.shade700,
                ),
                _hair(context),
                _row(
                  context,
                  'Total',
                  '${widget.currency}${widget.totalPrice}',
                  bold: true,
                ),
                _hair(context),
                _row(
                  context,
                  'Payment',
                  widget.paymentMethod.toUpperCase(),
                ),
              ],
            ),
          ),

          const SizedBox(height: 20),

          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 14),
            child: SafeArea(
              top: false,
              minimum: const EdgeInsets.only(bottom: 6),
              child: SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: _isProcessing
                      ? null
                      : () {
                          setState(() => _isProcessing = true);
                          _timer?.cancel();
                          widget.onViewOrder();
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primaryColor,
                    foregroundColor: Colors.black,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    textStyle:
                        _T.title.copyWith(fontWeight: FontWeight.w800),
                  ),
                  icon: const Icon(
                    TablerIcons.arrow_right,
                    size: 18,
                    color: Colors.black,
                  ),
                  label: const Text('View order'),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _row(
    BuildContext context,
    String label,
    String value, {
    bool emphasise = false,
    bool bold = false,
    Color? emphasiseColor,
  }) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: _T.body.copyWith(
              color: tertiary.withValues(alpha: 0.65),
            ),
          ),
          Text(
            value,
            style: (bold ? _T.title : _T.body).copyWith(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
              color: emphasise && emphasiseColor != null
                  ? emphasiseColor
                  : tertiary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _hair(BuildContext context) => Divider(
        height: 1,
        thickness: 1,
        color: Theme.of(context)
            .colorScheme
            .outlineVariant
            .withValues(alpha: 0.5),
      );
}
