import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/screen/order_page/cubit/order_returns_cubit.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_return_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/repo/order_repo.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';
import 'package:hyper_commerce_seller/utils/money_formatter.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

class OrderReturnsPage extends StatelessWidget {
  const OrderReturnsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => OrderReturnsCubit(OrdersRepo())..load(),
      child: const _OrderReturnsView(),
    );
  }
}

class _OrderReturnsView extends StatelessWidget {
  const _OrderReturnsView();

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      title: context.tr('orderReturnsTitle', fallback: 'Order Returns'),
      showAppbar: true,
      centerTitle: true,
      onBackTap: () => Navigator.of(context).pop(),
      body: BlocConsumer<OrderReturnsCubit, OrderReturnsState>(
        listenWhen: (p, c) => p.actionMessage != c.actionMessage && c.actionMessage != null,
        listener: (context, state) => showCustomSnackbar(
          context: context,
          message: state.actionMessage ?? '',
          isError: state.actionSuccess == false,
        ),
        builder: (context, state) {
          if (state.status == OrderReturnsStatus.loading) {
            return const Center(child: CustomLoadingIndicator());
          }
          if (state.status == OrderReturnsStatus.error) {
            return Center(child: Text(state.error ?? ''));
          }
          if (state.returns.isEmpty) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.assignment_return_outlined, size: 56, color: Colors.grey.shade400),
                const SizedBox(height: 12),
                Text(context.tr('noReturnsFound', fallback: 'No returns yet'), style: TextStyle(color: Colors.grey.shade600)),
              ]),
            );
          }
          return RefreshIndicator(
            onRefresh: () => context.read<OrderReturnsCubit>().refresh(),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: state.returns.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, i) => _ReturnCard(
                item: state.returns[i],
                l10n: null,
                currency: state.currency,
              ),
            ),
          );
        },
      ),
    );
  }
}

class _ReturnCard extends StatelessWidget {
  final OrderReturn item;
  final dynamic l10n;
  final CurrencyPayload? currency;
  const _ReturnCard({
    required this.item,
    required this.l10n,
    required this.currency,
  });

  bool get _isPending => item.returnStatus == 'requested' || item.returnStatus == 'pending';

  String _money(num v) {
    final c = currency;
    if (c != null) return MoneyFormatter.formatPayload(v, c);
    return MoneyFormatter.format(v, HiveStorage.currencySymbol, const MoneyFormat());
  }

  String _moneyStr(String v) => _money(double.tryParse(v) ?? 0);

  String _orderLabel() {
    if ((item.orderNumber ?? '').isNotEmpty) return 'Order #${item.orderNumber}';
    if (item.orderId != 0) return 'Order #${item.orderId}';
    return 'Order';
  }

  void _openDetails(BuildContext context) {
    if (item.orderId == 0) return;
    context.pushNamed(
      AppRoutes.orderDetails,
      pathParameters: {'id': item.orderId.toString()},
      queryParameters: {'tab': 'returns'},
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => _openDetails(context),
        borderRadius: BorderRadius.circular(14),
        child: Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF161B22) : Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? Colors.white12 : Colors.black12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Icon(Icons.receipt_long_outlined,
                size: 14, color: isDark ? Colors.white54 : Colors.grey.shade600),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                _orderLabel(),
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.2,
                  color: isDark ? Colors.white70 : Colors.grey.shade700,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            Icon(Icons.chevron_right,
                size: 18, color: isDark ? Colors.white38 : Colors.grey.shade400),
          ]),
          const SizedBox(height: 8),
          Row(children: [
            Expanded(
              child: Text(item.productTitle?.isNotEmpty == true ? item.productTitle! : 'Return #${item.id}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  maxLines: 1, overflow: TextOverflow.ellipsis),
            ),
            _statusChip(item.returnStatus),
          ]),
          if (item.reasonCode.isNotEmpty || item.reasonType.isNotEmpty) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                if (item.reasonCode.isNotEmpty) _codeChip(context, item.reasonCode),
                if (item.reasonType.isNotEmpty) _reasonTypeChip(item.reasonType),
              ],
            ),
          ],
          if (item.reason != null && item.reason!.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              (context.tr('reason', fallback: 'Reason')).toUpperCase(),
              style: _microLabel,
            ),
            const SizedBox(height: 4),
            Text(item.reason!, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
          ],
          if (item.addons.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              (context.tr('addOns', fallback: 'Add-ons')).toUpperCase(),
              style: _microLabel,
            ),
            const SizedBox(height: 6),
            ...item.addons.map((a) => _addonLine(context, a)),
          ],
          if (item.images.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              (context.tr('attachments', fallback: 'Attachments')).toUpperCase(),
              style: _microLabel,
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 64,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: item.images.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, i) {
                  final url = item.images[i];
                  return GestureDetector(
                    onTap: () async {
                      final uri = Uri.tryParse(url);
                      if (uri != null && await canLaunchUrl(uri)) {
                        await launchUrl(uri, mode: LaunchMode.externalApplication);
                      }
                    },
                    child: Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: isDark ? Colors.white12 : Colors.black12,
                        ),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: CachedNetworkImage(
                        imageUrl: url,
                        fit: BoxFit.cover,
                        placeholder: (c, u) => const CustomShimmer(width: 64, height: 64),
                        errorWidget: (c, u, e) => Icon(
                          Icons.broken_image_outlined,
                          color: Colors.grey.shade400,
                          size: 22,
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
          const SizedBox(height: 12),
          Divider(height: 1, thickness: 1, color: isDark ? Colors.white12 : Colors.black12),
          const SizedBox(height: 8),
          _kv(context, label: context.tr('quantity', fallback: 'Quantity'), value: '${item.quantity}'),
          _kv(
            context,
            label: '${context.tr('refund', fallback: "Refund")} ${context.tr('amount', fallback: "amount")}'.trim(),
            value: item.refundAmountFormatted,
            emphasize: true,
          ),
          if (!item.noShippingRequired &&
              (double.tryParse(item.returnShippingCharge) ?? 0) > 0)
            _kv(
              context,
              label: context.tr('returnShippingCharge', fallback: 'Return shipping charge'),
              value: _moneyStr(item.returnShippingCharge),
            ),
          if (item.noShippingRequired)
            _kv(
              context,
              label: context.tr('returnShippingCharge', fallback: 'Return shipping'),
              value: context.tr('noShippingRequired', fallback: 'Not required'),
              muted: true,
            ),
          if (item.createdAt.isNotEmpty)
            Builder(builder: (_) {
              final dt = DateTime.tryParse(item.createdAt);
              if (dt == null) return const SizedBox.shrink();
              return _kv(
                context,
                label: 'Requested on',
                value: DateFormat('d MMM, yyyy').format(dt.toLocal()),
              );
            }),
          if ((item.returnCarrier ?? '').isNotEmpty)
            _kv(context, label: context.tr('carrierName', fallback: 'Carrier'), value: item.returnCarrier!),
          if ((item.returnTrackingNumber ?? '').isNotEmpty)
            _kv(
              context,
              label: context.tr('trackingNumber', fallback: 'Tracking number'),
              value: item.returnTrackingNumber!,
              mono: true,
            ),
          if ((item.sellerComment ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('YOUR NOTE', style: _microLabel),
            const SizedBox(height: 4),
            Text(item.sellerComment!, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
          ],
          if (_isPending) ...[
            const Divider(height: 20),
            Row(children: [
              Expanded(
                child: SecondaryButton(
                  onPressed: () {
                    if (!DemoGuard.shouldProceed(context)) return;
                    _confirm(context, approve: false);
                  },
                  text: context.tr('decline', fallback: 'Decline'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: PrimaryButton(
                  onPressed: () {
                    if (!DemoGuard.shouldProceed(context)) return;
                    _confirm(context, approve: true);
                  },
                  text: context.tr('approve', fallback: 'Approve'),
                ),
              ),
            ]),
          ],
        ],
      ),
        ),
      ),
    );
  }

  Widget _addonLine(BuildContext context, ReturnAddon a) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final indicatorColor = _indicatorColor(a.itemIndicator);
    final subLabel = [
      if (a.groupTitle.isNotEmpty) a.groupTitle,
      if (a.groupSelectionType.isNotEmpty)
        a.groupSelectionType == 'single' ? 'single choice' : 'multi choice',
      if (a.groupIsRequired) 'required',
    ].join(' · ');
    // Prefer computing from the API currency payload so all money on the
    // card uses the same symbol + grouping; fall back to the pre-formatted
    // string from the API, then to a raw formatter with the local symbol.
    final priceText = currency != null
        ? MoneyFormatter.formatPayload(a.price, currency!)
        : (a.priceFormatted.isNotEmpty
            ? a.priceFormatted
            : MoneyFormatter.format(
                a.price, HiveStorage.currencySymbol, const MoneyFormat()));
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.only(top: 4),
            width: 10,
            height: 10,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(2),
              border: Border.all(color: indicatorColor, width: 1.4),
            ),
            alignment: Alignment.center,
            child: Container(
              width: 4,
              height: 4,
              decoration: BoxDecoration(shape: BoxShape.circle, color: indicatorColor),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  a.itemTitle.isNotEmpty ? a.itemTitle : '—',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: isDark ? Colors.white : Colors.black87,
                  ),
                ),
                if (subLabel.isNotEmpty) ...[
                  const SizedBox(height: 1),
                  Text(
                    subLabel,
                    style: TextStyle(
                      fontSize: 11.5,
                      color: isDark ? Colors.white54 : Colors.grey.shade600,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 10),
          Text(
            priceText,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: isDark ? Colors.white : Colors.black87,
            ),
          ),
        ],
      ),
    );
  }

  Color _indicatorColor(String? indicator) {
    switch (indicator?.toLowerCase()) {
      case 'veg':
        return Colors.green.shade600;
      case 'non-veg':
      case 'non_veg':
      case 'nonveg':
        return Colors.red.shade600;
      default:
        return Colors.grey.shade500;
    }
  }

  static const TextStyle _microLabel = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w800,
    letterSpacing: 0.08,
    color: Color(0xFF8B8B8B),
  );

  Widget _kv(
    BuildContext context, {
    required String label,
    required String value,
    bool emphasize = false,
    bool muted = false,
    bool mono = false,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final baseColor = isDark ? Colors.white : Colors.black87;
    final labelColor = isDark ? Colors.white60 : Colors.grey.shade600;
    final valueColor = muted
        ? (isDark ? Colors.white54 : Colors.grey.shade600)
        : (emphasize ? const Color(0xFFFFB616) : baseColor);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(fontSize: 12.5, color: labelColor, fontWeight: FontWeight.w500),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(
                fontSize: 13,
                color: valueColor,
                fontWeight: emphasize ? FontWeight.w800 : FontWeight.w700,
                fontFamily: mono ? 'monospace' : null,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _codeChip(BuildContext context, String code) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: isDark ? Colors.white24 : Colors.black26, width: 1),
      ),
      child: Text(
        code.replaceAll('_', ' ').toUpperCase(),
        style: TextStyle(
          fontSize: 10.5,
          letterSpacing: 0.08,
          fontWeight: FontWeight.w800,
          color: isDark ? Colors.white70 : Colors.black87,
        ),
      ),
    );
  }

  Widget _reasonTypeChip(String reasonType) {
    final isSeller = reasonType.toUpperCase().contains('SELLER');
    final color = isSeller ? Colors.red.shade600 : Colors.blueGrey.shade600;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        reasonType.replaceAll('_', ' ').toUpperCase(),
        style: TextStyle(
          fontSize: 10.5,
          letterSpacing: 0.08,
          fontWeight: FontWeight.w800,
          color: color,
        ),
      ),
    );
  }

  void _confirm(BuildContext context, {required bool approve}) {
    // Gate approve/decline on `return.decide` — previously any signed-in
    // user could resolve a return regardless of their role.
    if (!PermissionChecker.hasPermission(AppPermissions.returnDecide)) {
      showCustomSnackbar(
        context: context,
        message: "You don't have permission to decide on returns",
        isWarning: true,
      );
      return;
    }
    final cubit = context.read<OrderReturnsCubit>();
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(approve ? (context.tr('approveReturn', fallback: 'Approve return?')) : (context.tr('declineReturn', fallback: 'Decline return?'))),
        content: Text(approve
            ? (context.tr('approveReturnHint', fallback: 'The customer will be able to send the item back.'))
            : (context.tr('declineReturnHint', fallback: 'The return request will be rejected.'))),
        actions: [
          Row(
            children: [
              Expanded(child: SecondaryButton(onPressed: () => Navigator.pop(ctx), text: context.tr('cancel', fallback: 'Cancel'))),
              SizedBox(width: 10,),
              Expanded(
                child: PrimaryButton(
                  onPressed: () {
                    Navigator.pop(ctx);
                    approve ? cubit.approve(item.id) : cubit.decline(item.id);
                  },
                  text: approve ? (context.tr('approve', fallback: 'Approve')) : (context.tr('decline', fallback: 'Decline')),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statusChip(String status) {
    Color c = Colors.grey;
    if (status == 'refunded' || status == 'approved' || status == 'received') c = Colors.green;
    if (status == 'declined' || status == 'cancelled') c = Colors.red;
    if (status == 'requested' || status == 'pending') c = Colors.orange;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: c.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(status.replaceAll('_', ' '), style: TextStyle(fontSize: 11, color: c, fontWeight: FontWeight.w600)),
    );
  }
}
