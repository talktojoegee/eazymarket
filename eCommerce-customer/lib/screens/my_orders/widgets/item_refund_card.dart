import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_colors.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/screens/my_orders/model/order_detail_model.dart';
import 'package:intl/intl.dart';

class ItemRefundCard extends StatelessWidget {
  final OrderDetailData order;
  final OrderItems item;

  const ItemRefundCard({
    super.key,
    required this.order,
    required this.item,
  });

  /// Finds all refunds that belong to the specified [item].
  /// Matches via `refundItem.orderItemId == item.id`, or if items are empty
  /// and `settledByRefundId == item.id`.
  static List<RefundsList> getRefundsForItem(OrderDetailData order, OrderItems item) {
    final refunds = order.refundsList;
    if (refunds == null || refunds.isEmpty) return const [];
    return refunds.where((r) {
      final items = r.refundItem;
      if (items != null && items.isNotEmpty) {
        return items.any((ri) => ri.orderItemId != null && ri.orderItemId == item.id);
      }
      if (r.settledByRefundId != null) {
        return r.settledByRefundId == item.id;
      }
      return false;
    }).toList();
  }

  /// Checks if there is any refund for the item (supports both new (order, item)
  /// and legacy (item) calls for backward compatibility).
  static bool hasRefund(dynamic orderOrItem, [OrderItems? item]) {
    if (orderOrItem is OrderDetailData && item != null) {
      return getRefundsForItem(orderOrItem, item).isNotEmpty;
    }
    if (orderOrItem is OrderItems) {
      return orderOrItem.returns.any((r) => (r.refundAmount ?? 0) > 0);
    }
    return false;
  }

  @override
  Widget build(BuildContext context) {
    final refunds = getRefundsForItem(order, item);
    if (refunds.isEmpty) return const SizedBox.shrink();

    final scheme = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: EdgeInsets.all(14.w),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(14.r),
        border: Border.all(
          color: scheme.outlineVariant.withValues(alpha: 0.3),
          width: 1,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: EdgeInsets.all(6.w),
                decoration: BoxDecoration(
                  color: scheme.primary.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(8.r),
                ),
                child: Icon(
                  Icons.currency_exchange_rounded,
                  size: 15.sp,
                  color: scheme.tertiary,
                ),
              ),
              SizedBox(width: 8.w),
              Text(
                context.tr('itemRefundTitle', fallback: 'Refund Details'),
                style: AppTextStyles.bodySmall.copyWith(
                  fontWeight: FontWeight.w700,
                  color: scheme.onSurface,
                ),
              ),
            ],
          ),
          for (int i = 0; i < refunds.length; i++) ...[
            if (i > 0)
              Padding(
                padding: EdgeInsets.symmetric(vertical: 12.h),
                child: Divider(
                  height: 1,
                  color: scheme.outlineVariant.withValues(alpha: 0.4),
                ),
              )
            else
              SizedBox(height: 12.h),
            _RefundBlock(
              order: order,
              item: item,
              refund: refunds[i],
            ),
          ],
        ],
      ),
    );
  }
}

class _RefundBlock extends StatelessWidget {
  final OrderDetailData order;
  final OrderItems item;
  final RefundsList refund;

  const _RefundBlock({
    required this.order,
    required this.item,
    required this.refund,
  });

  RefundItem? get _matchedItem {
    final items = refund.refundItem;
    if (items == null || items.isEmpty) return null;
    for (final ri in items) {
      if (ri.orderItemId == item.id) return ri;
    }
    return null;
  }

  bool get _isIssued {
    final s = (refund.status ?? '').toLowerCase().trim();
    return s == 'issued' || s == 'completed' || s == 'refunded' || s == 'success';
  }

  bool get _isOwed {
    final s = (refund.status ?? '').toLowerCase().trim();
    return s == 'owed' || s == 'pending' || s == 'processing';
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final amountText = _money(order, (refund.amount ?? 0).toDouble());
    final pill = _pill(context);
    final dateRaw = refund.issuedAt?.isNotEmpty == true
        ? refund.issuedAt
        : refund.createdAt;
    final date = _formatDate(dateRaw);
    final method = _methodText(context);
    final matchedItem = _matchedItem;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  /*Text(
                    context.tr('itemRefundAmountLabel', fallback: 'Refund amount'),
                    style: AppTextStyles.label.copyWith(
                      color: scheme.onSurfaceVariant.withValues(alpha: 0.8),
                    ),
                  ),
                  SizedBox(height: 3.h),*/
                  Text(
                    amountText,
                    style: AppTextStyles.titleLarge.copyWith(
                      color: scheme.onSurface,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
            Container(
              padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 4.h),
              decoration: BoxDecoration(
                color: pill.color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20.r),
                border: Border.all(
                  color: pill.color.withValues(alpha: 0.3),
                  width: 0.8,
                ),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(pill.icon, size: 13.sp, color: pill.color),
                  SizedBox(width: 4.w),
                  Text(
                    pill.text,
                    style: AppTextStyles.caption.copyWith(
                      fontWeight: FontWeight.w700,
                      color: pill.color,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        SizedBox(height: 10.h),
        if (matchedItem?.qty != null && (matchedItem!.qty ?? 0) > 0)
          _detailRow(
            context,
            context.tr('itemRefundQtyLabel', fallback: 'Refunded quantity'),
            '${matchedItem.qty}',
          ),
        _detailRow(
          context,
          context.tr('itemRefundMethodLabel', fallback: 'Refund method'),
          method,
        ),
        if (date != null)
          _detailRow(
            context,
            _isIssued
                ? context.tr('itemRefundDateLabel', fallback: 'Refunded on')
                : context.tr('itemRefundRequestedDateLabel', fallback: 'Initiated on'),
            date,
          ),
        if ((refund.shippingRefundAmount ?? 0) > 0)
          _detailRow(
            context,
            context.tr('shippingRefundLabel', fallback: 'Shipping refund'),
            _money(order, refund.shippingRefundAmount!),
          ),
      ],
    );
  }

  _RefundPill _pill(BuildContext context) {
    if (_isIssued) {
      return _RefundPill(
        text: context.tr('refundStatusIssued', fallback: 'Issued'),
        color: context.appColors.success,
        icon: Icons.check_circle_rounded,
      );
    }
    if (_isOwed) {
      return _RefundPill(
        text: context.tr('refundStatusOwed', fallback: 'Owed'),
        color: context.appColors.success,
        icon: Icons.schedule_rounded,
      );
    }
    return _RefundPill(
      text: (refund.status ?? 'Processing').toUpperCase(),
      color: context.appColors.info,
      icon: Icons.check_circle_rounded,
    );
  }

  String _methodText(BuildContext context) {
    final m = refund.method?.trim();
    if (m != null && m.isNotEmpty) {
      return m.replaceAll('_', ' ').split(' ').map((w) {
        if (w.isEmpty) return '';
        return w[0].toUpperCase() + w.substring(1).toLowerCase();
      }).join(' ');
    }
    final isCod = (order.paymentMethod ?? '').toLowerCase().contains('cod');
    if (isCod) return context.tr('refundMethodWallet', fallback: 'Wallet');
    return context.tr('refundMethodSource', fallback: 'Original payment method');
  }

  Widget _detailRow(BuildContext context, String label, String value) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: EdgeInsets.symmetric(vertical: 3.5.h),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: AppTextStyles.label.copyWith(
              color: scheme.onSurfaceVariant.withValues(alpha: 0.8),
            ),
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: AppTextStyles.label.copyWith(
                fontWeight: FontWeight.w600,
                color: scheme.onSurface,
              ),
            ),
          ),
        ],
      ),
    );
  }

  String? _formatDate(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    final dt = DateTime.tryParse(raw);
    if (dt == null) return null;
    return DateFormat('d MMM yyyy').format(dt.toLocal());
  }
}

class _RefundPill {
  final String text;
  final Color color;
  final IconData icon;

  const _RefundPill({
    required this.text,
    required this.color,
    required this.icon,
  });
}

String _money(OrderDetailData order, double value) {
  final symbol = (order.currencySymbol == null || order.currencySymbol!.isEmpty)
      ? '₹'
      : order.currencySymbol!;
  return '$symbol${formatPrice(value)}';
}
