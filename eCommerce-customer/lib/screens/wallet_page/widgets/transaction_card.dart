import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:intl/intl.dart';

import '../model/prepare_wallet_recharge_model.dart';


class TransactionCard extends StatelessWidget {
  final Transaction transaction;

  const TransactionCard({
    super.key,
    required this.transaction,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    final bool isCredit = _isCreditTransaction();

    final Color accentColor = isCredit
        ? Colors.green.shade700
        : theme.colorScheme.error;

    log('Transaction Card: ${transaction.orderId}');

    return Container(
      margin: EdgeInsets.only(bottom: 14.h),
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(18.r),
        border: Border.all(
          color: theme.colorScheme.outlineVariant,
          width: 1,
        ),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(18.r),
        child: Column(
          children: [
            // ==========================================================
            // TOP SUMMARY
            // ==========================================================
            Padding(
              padding: EdgeInsets.fromLTRB(
                12.w,
                14.h,
                12.w,
                12.h,
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  // ----------------------------------------------------
                  // TRANSACTION ICON
                  // ----------------------------------------------------
                  _TransactionIcon(
                    isCredit: isCredit,
                    color: accentColor,
                  ),

                  SizedBox(width: 14.w),

                  // ----------------------------------------------------
                  // TITLE + ORDER + PAYMENT METHOD
                  // ----------------------------------------------------
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _transactionTitle(),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 16.sp,
                            fontWeight: FontWeight.w600,
                            color: theme.textTheme.bodyLarge?.color,
                          ),
                        ),

                        SizedBox(height: 3.h),

                        Row(
                          children: [
                            if (transaction.orderId != null && transaction.orderId! > 0) ...[
                              Flexible(
                                child: Text(
                                  'Order #${transaction.orderId}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 13.sp,
                                    color: theme.textTheme.bodySmall?.color
                                        ?.withValues(alpha: 0.65),
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),

                  SizedBox(width: 10.w),

                  // ----------------------------------------------------
                  // AMOUNT + STATUS
                  // ----------------------------------------------------
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      ConstrainedBox(
                        constraints: BoxConstraints(
                          maxWidth: 125.w,
                        ),
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.centerRight,
                          child: Text(
                            '${isCredit ? '+' : '-'} ${_formattedAmount()}',
                            maxLines: 1,
                            style: TextStyle(
                              fontSize: 17.sp,
                              fontWeight: FontWeight.w700,
                              color: accentColor,
                            ),
                          ),
                        ),
                      ),

                      SizedBox(height: 7.h),

                      _StatusBadge(
                        status: _statusText(),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // ==========================================================
            // DETAIL SECTION
            // ==========================================================
            Container(
              width: double.infinity,
              padding: EdgeInsets.fromLTRB(
                12.w,
                12.h,
                12.w,
                12.h,
              ),
              decoration: BoxDecoration(
                border: Border(
                  top: BorderSide(
                    color: theme.colorScheme.outlineVariant,
                    width: 1,
                  ),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ----------------------------------------------------
                  // DATE + PAYMENT METHOD
                  // ----------------------------------------------------
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _DetailColumn(
                          title: 'DATE & TIME',
                          value: _formattedDate(),
                        ),
                      ),

                      SizedBox(width: 16.w),

                      Expanded(
                        child: _DetailColumn(
                          title: 'PAYMENT METHOD',
                          value: _paymentMethod(),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 18),

                  // ----------------------------------------------------
                  // DESCRIPTION
                  // ----------------------------------------------------
                  if (_description().isNotEmpty) ...[
                    _DetailColumn(
                      title: 'DESCRIPTION',
                      value: _description(),
                    ),

                  ],

                  // ----------------------------------------------------
                  // PAYMENT / TRANSACTION ID
                  // ----------------------------------------------------
                  if (_transactionReference().isNotEmpty) ...[
                    const SizedBox(height: 14),
                    _buildIdRow(
                      context,
                      label: 'PAYMENT ID',
                      value: _transactionReference(),
                    ),
                  ],

                  // ----------------------------------------------------
                  // ORDER ID
                  // ----------------------------------------------------
                  if (transaction.orderId != null && transaction.orderId! > 0) ...[
                    SizedBox(height: 10.h),
                    _buildIdRow(
                      context,
                      label: 'ORDER ID',
                      value: transaction.orderId.toString(),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildIdRow(BuildContext context, {required String label, required String value}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: Theme.of(context).colorScheme.outlineVariant,
          width: 0.5,
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 11.sp,
                    color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.4),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 13.sp,
                    fontWeight: FontWeight.w500,
                    fontFamily: 'monospace',
                    color: Theme.of(context).colorScheme.onSurface,
                    letterSpacing: 0.2,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          GestureDetector(
            onTap: () {
              Clipboard.setData(ClipboardData(text: value));
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('$label copied'),
                  duration: const Duration(seconds: 2),
                  behavior: SnackBarBehavior.floating,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
              );
            },
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                border: Border.all(
                  color: Theme.of(context).colorScheme.outlineVariant,
                  width: 0.5,
                ),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                'Copy',
                style: TextStyle(
                  fontSize: 11.sp,
                  color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.55),
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ===========================================================================
  // TITLE
  // ===========================================================================

  String _transactionTitle() {
    final type = transaction.transactionType?.trim();

    if (type != null && type.isNotEmpty) {
      return _formatText(type);
    }

    final description = transaction.description?.trim();

    if (description != null && description.isNotEmpty) {
      return description;
    }

    return 'Payment received';
  }

  // ===========================================================================
  // PAYMENT METHOD
  // ===========================================================================

  String _paymentMethod() {
    final method = transaction.paymentMethod?.trim();

    if (method == null || method.isEmpty) {
      return 'Payment';
    }

    return _formatText(method);
  }

  // ===========================================================================
  // DESCRIPTION
  // ===========================================================================

  String _description() {
    return transaction.description?.trim() ?? '';
  }

  // ===========================================================================
  // TRANSACTION REFERENCE
  // ===========================================================================

  String _transactionReference() {
    return transaction.transactionReference?.trim() ?? '';
  }

  // ===========================================================================
  // STATUS
  // ===========================================================================

  String _statusText() {
    final status = transaction.status?.trim();

    if (status == null || status.isEmpty) {
      return 'Unknown';
    }

    return _formatText(status);
  }

  // ===========================================================================
  // AMOUNT
  // ===========================================================================

  String _formattedAmount() {
    final formatted = transaction.formattedAmount?.trim();

    if (formatted != null && formatted.isNotEmpty) {
      return formatted;
    }

    final amount = transaction.amount?.trim();

    if (amount == null || amount.isEmpty) {
      return '${transaction.currencySymbol ?? ''}0.00';
    }

    final parsedAmount = double.tryParse(amount);

    if (parsedAmount == null) {
      return '${transaction.currencySymbol ?? ''}$amount';
    }

    final symbol = transaction.currencySymbol ?? '';

    return '$symbol${NumberFormat(
      '#,##0.00',
    ).format(parsedAmount)}';
  }

  // ===========================================================================
  // DATE
  // ===========================================================================

  String _formattedDate() {
    final value = transaction.createdAt?.trim();

    if (value == null || value.isEmpty) {
      return '—';
    }

    try {
      final date = DateTime.parse(value).toLocal();
      final now = DateTime.now();

      final today = DateTime(
        now.year,
        now.month,
        now.day,
      );

      final transactionDay = DateTime(
        date.year,
        date.month,
        date.day,
      );

      final difference = today.difference(transactionDay).inDays;

      if (difference == 0) {
        return 'Today, ${DateFormat('h:mm a').format(date)}';
      }

      if (difference == 1) {
        return 'Yesterday, ${DateFormat('h:mm a').format(date)}';
      }

      return DateFormat(
        'dd MMM, h:mm a',
      ).format(date);
    } catch (_) {
      return value;
    }
  }

  // ===========================================================================
  // CREDIT / DEBIT
  // ===========================================================================

  bool _isCreditTransaction() {
    final type =
        transaction.transactionType?.toLowerCase().trim() ?? '';

    final description =
        transaction.description?.toLowerCase().trim() ?? '';

    final combined = '$type $description';

    return combined.contains('deposit') ||
        combined.contains('recharge') ||
        combined.contains('refund') ||
        combined.contains('cashback') ||
        combined.contains('credit') ||
        combined.contains('received') ||
        combined.contains('wallet_add') ||
        combined.contains('wallet recharge');
  }

  // ===========================================================================
  // TEXT FORMATTER
  // ===========================================================================

  String _formatText(String value) {
    final formatted = value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .trim();

    if (formatted.isEmpty) {
      return 'Transaction';
    }

    return formatted
        .split(' ')
        .where((word) => word.isNotEmpty)
        .map(
          (word) =>
      '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}',
    )
        .join(' ');
  }
}

// ============================================================================
// TRANSACTION ICON
// ============================================================================

class _TransactionIcon extends StatelessWidget {
  final bool isCredit;
  final Color color;

  const _TransactionIcon({
    required this.isCredit,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(13.r),
      ),
      child: Icon(
        isCredit
            ? TablerIcons.arrow_down
            : TablerIcons.arrow_up,
        size: 20,
        color: color,
      ),
    );
  }
}

// ============================================================================
// DETAIL COLUMN
// ============================================================================

class _DetailColumn extends StatelessWidget {
  final String title;
  final String value;

  const _DetailColumn({
    required this.title,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: 11.sp,
            fontWeight: FontWeight.w600,
            letterSpacing: 0.5,
            color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.4),
          ),
        ),
        SizedBox(height: 4.h),
        Text(
          value,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: 14.sp,
            fontWeight: FontWeight.w500,
            color: theme.textTheme.bodyLarge?.color,
          ),
        ),
      ],
    );
  }
}

// ============================================================================
// STATUS BADGE
// ============================================================================

class _StatusBadge extends StatelessWidget {
  final String status;

  const _StatusBadge({
    required this.status,
  });

  @override
  Widget build(BuildContext context) {
    final normalizedStatus = status.toLowerCase();

    final bool isSuccess = [
      'completed',
      'complete',
      'success',
      'successful',
      'paid',
      'approved',
    ].contains(normalizedStatus);

    final bool isPending = [
      'pending',
      'processing',
      'in progress',
    ].contains(normalizedStatus);

    final Color color = isSuccess
        ? Colors.green.shade700
        : isPending
        ? Colors.orange.shade700
        : Theme.of(context).colorScheme.error;

    final Color backgroundColor = color.withValues(
      alpha: 0.10,
    );

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: 11.w,
        vertical: 2.h,
      ),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(20.r),
      ),
      child: Text(
        status,
        style: TextStyle(
          fontSize: 11.sp,
          fontWeight: FontWeight.w600,
          color: color,
        ),
      ),
    );
  }
}