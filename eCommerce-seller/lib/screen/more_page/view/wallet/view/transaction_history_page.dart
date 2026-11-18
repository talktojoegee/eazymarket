import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/time_utils.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';

import '../bloc/transactions_bloc/transactions_history_bloc.dart';
import '../model/transactions_model.dart';

class TransactionHistoryPage extends StatefulWidget {
  const TransactionHistoryPage({super.key});

  @override
  State<TransactionHistoryPage> createState() => _TransactionHistoryPageState();
}

class _TransactionHistoryPageState extends State<TransactionHistoryPage> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    context.read<TransactionsHistoryBloc>().add(LoadTransactionsInitial());
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_isBottom) {
      context.read<TransactionsHistoryBloc>().add(LoadMoreTransactions());
    }
  }

  bool get _isBottom {
    if (!_scrollController.hasClients) return false;
    final maxScroll = _scrollController.position.maxScrollExtent;
    final currentScroll = _scrollController.offset;
    return currentScroll >= (maxScroll * 0.9);
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final theme = Theme.of(context);
    return CustomScaffold(
      title: context.tr('transactions', fallback: 'Transactions'),
      showAppbar: true,
      centerTitle: true,
      body: BlocBuilder<TransactionsHistoryBloc, TransactionsHistoryState>(
        builder: (context, state) {
          if ((state.isInitialLoading || state.isRefreshing) && state.items.isEmpty) {
            return ListView.separated(
              padding: UIUtils.pagePadding(screenType),
              separatorBuilder: (context, index) =>
                  SizedBox(height: UIUtils.gapMD(screenType)),
              itemCount: 10,
              itemBuilder: (context, index) =>
                  CardShimmer(type: 'walletHistory', screenType: screenType),
            );
          }

          if (state.error != null && state.items.isEmpty) {
            return EmptyStateWidget(
              svgPath: ImagesPath.noOrderFoundSvg,
              title:
                  context.tr('somethingWentWrong', fallback: 'Seems like there is some issue'),
              subtitle: context.tr('tryAgainLater', fallback: 'Please try again later'),
              actionText: context.tr('tryAgain', fallback: 'Try Again'),
              onAction: () {
                context.read<TransactionsHistoryBloc>().add(
                  RefreshTransactionsHistory(),
                );
              },
            );
          }

          final history = state.items;
          if (history.isEmpty) {
            return EmptyStateWidget(
              svgPath: ImagesPath.noOrderFoundSvg,
              title: context.tr('noTransactionsFound', fallback: 'No transactions found'),
              subtitle:
                  context.tr('noTransactionsSubtitle', fallback: 'You have not performed any transactions yet'),
              actionText: context.tr('refresh', fallback: 'Refresh'),
              onAction: () {
                context.read<TransactionsHistoryBloc>().add(
                  RefreshTransactionsHistory(),
                );
              },
            );
          }

          return RefreshIndicator(
            color: AppColors.primaryColor,
            onRefresh: () async {
              context.read<TransactionsHistoryBloc>().add(
                RefreshTransactionsHistory(),
              );
            },
            child: ListView.separated(
              controller: _scrollController,
              padding: UIUtils.pagePadding(screenType),
              physics: const AlwaysScrollableScrollPhysics(),
              itemCount: history.length + (state.isPaginating ? 1 : 0),
              separatorBuilder: (context, index) =>
                  SizedBox(height: UIUtils.gapMD(screenType)),
              itemBuilder: (context, index) {
                if (index >= history.length) {
                  return CardShimmer(
                    type: 'walletHistory',
                    screenType: screenType,
                  );
                }

                final item = history[index];
                return _buildTransactionCard(
                  context,
                  item,
                  screenType,
                  theme,
                );
              },
            ),
          );
        },
      ),
    );
  }

  Widget _buildTransactionCard(
    BuildContext context,
    TransactionData item,
    ScreenType screenType,
    ThemeData theme,
  ) {
    final isDark = theme.brightness == Brightness.dark;
    final isCredit = item.isCredit;
    final currency = item.currencySymbol ?? HiveStorage.currencySymbol;
    final amountRaw =
        (item.formattedAmount != null && item.formattedAmount!.isNotEmpty)
            ? item.formattedAmount!
            : '$currency${item.amount}';
    final cleanAmount =
        amountRaw.replaceAll('+', '').replaceAll('-', '').trim();
    final amountDisplay = isCredit ? '+ $cleanAmount' : '- $cleanAmount';

    final (statusBg, statusTextColor) = _getStatusColors(item.status, isDark);
    final statusText = item.status != null && item.status!.isNotEmpty
        ? _formatMethod(item.status)
        : context.tr('completed', fallback: 'Completed');

    final title = _getTransactionTitle(context, item);

    final rawDesc =
        (item.description != null && item.description!.trim().isNotEmpty)
            ? item.description!.trim()
            : (item.message != null && item.message!.trim().isNotEmpty)
                ? item.message!.trim()
                : (item.orderId != null && item.orderId! > 0)
                    ? '${isCredit ? context.tr('credit', fallback: 'Credit') : context.tr('payment', fallback: 'Payment')} ${context.tr('forOrder', fallback: 'for Order')} #${item.orderId}.'
                    : null;

    final formattedMethod = _formatMethod(item.paymentMethod);
    final paymentMethodDisplay = formattedMethod.isNotEmpty
        ? formattedMethod
        : (item.paymentMethod != null && item.paymentMethod!.isNotEmpty
            ? item.paymentMethod!
            : (isCredit
                ? context.tr('refund', fallback: 'Refund')
                : context.tr('payment', fallback: 'Payment')));

    final ref = item.transactionReference ??
        (item.transactionId != null && item.transactionId!.isNotEmpty
            ? item.transactionId
            : (item.id > 0 ? '#${item.id}' : null));

    final labelColor =
        isDark ? Colors.grey.shade400 : const Color(0xFF6B7280);
    final valueColor = theme.textTheme.titleMedium?.color ??
        (isDark ? Colors.white : const Color(0xFF111827));
    final borderColor =
        isDark ? Colors.grey.shade800 : const Color(0xFFE5E7EB);
    final dividerColor =
        isDark ? Colors.grey.shade800 : const Color(0xFFF1F5F9);

    final iconBg = isCredit
        ? (isDark ? const Color(0xFF1B3820) : const Color(0xFFE8F5E9))
        : (isDark ? const Color(0xFF371C1D) : const Color(0xFFFFEBEE));
    final iconColor = isCredit
        ? (isDark ? const Color(0xFF81C784) : const Color(0xFF2E7D32))
        : (isDark ? const Color(0xFFE57373) : const Color(0xFFC62828));
    final amountColor = isCredit
        ? (isDark ? const Color(0xFF81C784) : const Color(0xFF2E7D32))
        : (isDark ? const Color(0xFFE57373) : const Color(0xFFD32F2F));

    return Container(
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: borderColor, width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Header: Icon + Title/Subtitle | Amount + Status Pill ──
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Direction icon container
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: iconBg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  alignment: Alignment.center,
                  child: Icon(
                    isCredit
                        ? Icons.arrow_downward_rounded
                        : Icons.arrow_upward_rounded,
                    color: iconColor,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),

                // Title + Subtitle (e.g. Order #85)
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: TextStyle(
                          fontSize: 16.5,
                          fontWeight: FontWeight.w700,
                          color: valueColor,
                        ),
                      ),
                      if (item.orderId != null && item.orderId! > 0) ...[
                        const SizedBox(height: 3),
                        Text(
                          '${context.tr('order', fallback: 'Order')} #${item.orderId}',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                            color: isDark
                                ? Colors.grey.shade400
                                : Colors.grey.shade500,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),

                // Amount + Status Pill
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      amountDisplay,
                      style: TextStyle(
                        fontSize: 17.5,
                        fontWeight: FontWeight.w800,
                        color: amountColor,
                        letterSpacing: -0.2,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 3.5,
                      ),
                      decoration: BoxDecoration(
                        color: statusBg,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        statusText,
                        style: TextStyle(
                          color: statusTextColor,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // ── Divider ──
          Divider(height: 1, thickness: 1, color: dividerColor),

          // ── Details Body ──
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Date & Time + Payment Method
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            context
                                .tr('dateAndTime', fallback: 'Date & Time')
                                .toUpperCase(),
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 0.5,
                              color: labelColor,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            _formatDateTime(item.createdAt),
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: valueColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            context
                                .tr('paymentMethod', fallback: 'Payment Method')
                                .replaceAll(':', '')
                                .trim()
                                .toUpperCase(),
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 0.5,
                              color: labelColor,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            paymentMethodDisplay,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: valueColor,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                // Description
                if (rawDesc != null) ...[
                  const SizedBox(height: 14),
                  Text(
                    context
                        .tr('description', fallback: 'Description')
                        .toUpperCase(),
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 0.5,
                      color: labelColor,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    rawDesc,
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w500,
                      height: 1.35,
                      color: valueColor,
                    ),
                  ),
                ],

                // Payment ID / Reference with Copy button
                if (ref != null && ref.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.fromLTRB(14, 10, 10, 10),
                    decoration: BoxDecoration(
                      color: isDark
                          ? Colors.white.withValues(alpha: 0.04)
                          : const Color(0xFFF9FAFB),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: borderColor,
                        width: 1,
                      ),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                context
                                    .tr('paymentId', fallback: 'Payment ID')
                                    .toUpperCase(),
                                style: TextStyle(
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w600,
                                  letterSpacing: 0.5,
                                  color: labelColor,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                ref,
                                style: TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w600,
                                  color: valueColor,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 10),
                        Material(
                          color: isDark
                              ? const Color(0xFF262C36)
                              : Colors.white,
                          borderRadius: BorderRadius.circular(8),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(8),
                            onTap: () {
                              Clipboard.setData(ClipboardData(text: ref));
                              showCustomSnackbar(
                                context: context,
                                message: context.tr(
                                  'referenceCopied',
                                  fallback: 'Reference copied to clipboard',
                                ),
                              );
                            },
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                  color: isDark
                                      ? Colors.grey.shade700
                                      : const Color(0xFFE5E7EB),
                                  width: 1,
                                ),
                              ),
                              child: Text(
                                context.tr('copy', fallback: 'Copy'),
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: isDark
                                      ? Colors.grey.shade300
                                      : const Color(0xFF374151),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  (Color, Color) _getStatusColors(String? status, bool isDark) {
    switch (status?.toLowerCase()) {
      case 'completed':
      case 'success':
      case 'approved':
        return (
          isDark ? const Color(0xFF1B3820) : const Color(0xFFE8F5E9),
          isDark ? const Color(0xFF81C784) : const Color(0xFF2E7D32),
        );
      case 'pending':
      case 'processing':
        return (
          isDark ? const Color(0xFF3E2723) : const Color(0xFFFFF3E0),
          isDark ? const Color(0xFFFFB74D) : const Color(0xFFE65100),
        );
      case 'failed':
      case 'rejected':
      case 'cancelled':
        return (
          isDark ? const Color(0xFF371C1D) : const Color(0xFFFFEBEE),
          isDark ? const Color(0xFFE57373) : const Color(0xFFC62828),
        );
      default:
        return (
          isDark ? Colors.grey.shade800 : Colors.grey.shade100,
          isDark ? Colors.grey.shade300 : Colors.grey.shade700,
        );
    }
  }

  String _getTransactionTitle(BuildContext context, TransactionData item) {
    final lowerType = item.type.toLowerCase();
    if (lowerType == 'deposit' || lowerType == 'credit') {
      return context.tr('deposit', fallback: 'Deposit');
    } else if (lowerType == 'withdrawal' || lowerType == 'withdraw') {
      return context.tr('withdrawal', fallback: 'Withdrawal');
    } else if (lowerType == 'payment' || lowerType == 'debit') {
      return context.tr('payment', fallback: 'Payment');
    } else if (lowerType == 'refund') {
      return context.tr('refund', fallback: 'Refund');
    }
    return _formatMethod(item.type);
  }

  String _formatDateTime(String dateStr) {
    if (dateStr.isEmpty) return '';
    try {
      final dt = DateTime.parse(dateStr).toLocal();
      final now = DateTime.now();
      if (dt.year == now.year) {
        return DateFormat('d MMM, h:mm a').format(dt);
      } else {
        return DateFormat('d MMM yyyy, h:mm a').format(dt);
      }
    } catch (_) {
      return dateStr;
    }
  }

  String _formatMethod(String? method) {
    if (method == null || method.isEmpty) return '';
    return method
        .split('_')
        .map((word) => word.isNotEmpty
            ? '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}'
            : '')
        .join(' ');
  }
}
