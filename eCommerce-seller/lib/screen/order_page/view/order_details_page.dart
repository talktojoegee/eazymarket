// ignore_for_file: deprecated_member_use

import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/order_page/bloc/order_details_bloc/order_details_bloc.dart';
import 'package:hyper_commerce_seller/screen/order_page/bloc/orders/orders_bloc.dart';
import 'package:hyper_commerce_seller/screen/order_page/cubit/order_fulfillment_cubit.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_details_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_earnings_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_fulfillment_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_return_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/repo/order_repo.dart';
import 'package:hyper_commerce_seller/screen/order_page/widgets/fulfillment_sheet.dart';
import 'package:hyper_commerce_seller/screen/order_page/widgets/order_dialogs.dart';
import 'package:hyper_commerce_seller/screen/order_page/widgets/return_approve_sheet.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/service/external_link.dart';
import 'package:hyper_commerce_seller/utils/money_formatter.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:intl/intl.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:url_launcher/url_launcher.dart';

// ═════════════════════════════════════════════════════════════════════
// Typography scale for Order Detail — 6 tiers + 1 micro-xs chip variant.
// Kept file-local so restyling this screen doesn't affect the rest of
// the app. All sizes are integers, weights are one of {w500, w600, w700,
// w800}, letter-spacing is one of {0, 0.06, 0.08}. Colour is applied at
// each call site via .copyWith(color: ...) so styles stay theme-aware.
// ═════════════════════════════════════════════════════════════════════
class _T {
  _T._();

  // 28 / w800 — settlement amount only.
  static TextStyle get display => _base(28, FontWeight.w800);

  // 15 / w700 — section titles, sheet titles.
  static TextStyle get title => _base(15, FontWeight.w700);

  // 14 / w700 — item titles, address names, return card title.
  static TextStyle get subtitle => _base(14, FontWeight.w700);

  // 13 / w600 — primary values, KV emphasise, dialog / button text.
  static TextStyle get body => _base(13, FontWeight.w600);

  // 12 / w500 — secondary meta, notices, helper text, sheet subtitles.
  static TextStyle get meta => _base(12, FontWeight.w500);

  // 11 / w800 / uppercase / +0.08 — KV keys, section labels, default
  // status chips.
  static TextStyle get micro => _base(11, FontWeight.w800, letter: 0.08);

  // 10 / w800 / uppercase / +0.06 — small status chips inside dense rows.
  static TextStyle get microXs => _base(10, FontWeight.w800, letter: 0.06);

  static TextStyle _base(double size, FontWeight weight, {double letter = 0}) {
    return TextStyle(
      fontFamily: AppConstants.fontFamily,
      fontSize: size,
      fontWeight: weight,
      letterSpacing: letter,
      height: 1.35,
    );
  }
}

class OrderDetailsPage extends StatelessWidget {
  final int orderId;
  final int initialTabIndex;
  const OrderDetailsPage({
    super.key,
    required this.orderId,
    this.initialTabIndex = 0,
  });

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => OrderFulfillmentCubit(context.read<OrdersRepo>(), orderId)
        ..load(),
      child: _OrderDetailsView(
        orderId: orderId,
        initialTabIndex: initialTabIndex,
      ),
    );
  }
}

class _OrderDetailsView extends StatefulWidget {
  final int orderId;
  final int initialTabIndex;
  const _OrderDetailsView({
    required this.orderId,
    this.initialTabIndex = 0,
  });

  @override
  State<_OrderDetailsView> createState() => _OrderDetailsPageState();
}

class _OrderDetailsPageState extends State<_OrderDetailsView>
    with SingleTickerProviderStateMixin {
  final Set<int> _selectedItemIds = {};
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    final safeIndex =
        widget.initialTabIndex.clamp(0, 2).toInt();
    _tabController = TabController(
      length: 3,
      vsync: this,
      initialIndex: safeIndex,
    );
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) setState(() {});
    });
    context.read<OrderDetailsBloc>().add(FetchOrderDetails(widget.orderId));
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  String? _getSelectionMode(List<OrderItemDetail> items) {
    if (_selectedItemIds.isEmpty) return null;
    for (final item in items) {
      final itemId = item.orderItem?.id ?? item.id;
      if (_selectedItemIds.contains(itemId)) {
        final status = (item.orderItem?.status ?? '').toLowerCase();
        if (status == 'pending' || status == 'awaiting_store_response') {
          return 'pending';
        } else if (status == 'accepted') {
          return 'accepted';
        }
      }
    }
    return null;
  }

  bool _isItemSelectable(OrderItemDetail item, String? selectionMode) {
    final status = (item.orderItem?.status ?? '').toLowerCase();
    if ({'rejected', 'cancelled', 'preparing', 'ready_for_pickup', 'completed'}
        .contains(status)) {
      return false;
    }
    if (selectionMode == null) {
      return status == 'pending' ||
          status == 'awaiting_store_response' ||
          status == 'accepted';
    }
    if (selectionMode == 'pending') {
      return status == 'pending' || status == 'awaiting_store_response';
    }
    if (selectionMode == 'accepted') {
      return status == 'accepted';
    }
    return false;
  }

  bool _isItemActionable(OrderItemDetail item) {
    final status = (item.orderItem?.status ?? '').toLowerCase();
    return status == 'pending' ||
        status == 'awaiting_store_response' ||
        status == 'accepted';
  }

  void _toggleItemSelection(int itemId) {
    setState(() {
      if (_selectedItemIds.contains(itemId)) {
        _selectedItemIds.remove(itemId);
      } else {
        _selectedItemIds.add(itemId);
      }
    });
  }

  @override
  Widget build(BuildContext context) {

    return BlocConsumer<OrderDetailsBloc, OrderDetailsState>(
      listener: (context, state) {
        if (state is OrderDetailsLoaded) {
          setState(() => _selectedItemIds.clear());
        }
      },
      builder: (context, state) {
        Widget? bottomBar;
        if (state is OrderDetailsLoaded && _tabController.index == 0) {
          bottomBar = _buildBottomBar(
              context, state.orderData, state.isUpdating, null);
        }

        return CustomScaffold(
          title: context.tr('orderDetails', fallback: 'Order Details'),
          centerTitle: true,
          showAppbar: true,
          bottomNavigationBar: bottomBar,
          body: Builder(
            builder: (context) {
              if (state is OrderDetailsLoading) {
                return _buildShimmerLoading(context);
              }
              if (state is OrderDetailsError) {
                return Center(
                  child: Text(
                    state.message,
                    style: _T.body.copyWith(
                      color: Theme.of(context).colorScheme.tertiary,
                    ),
                  ),
                );
              }
              if (state is OrderDetailsLoaded) {
                final data = state.orderData;
                return Column(
                  children: [
                    _StatusHeader(data: data),
                    _SegmentedTabBar(
                      controller: _tabController,
                      labels: [
                        context.tr('overview'),
                        context.tr('returnsAndRefunds'),
                        context.tr('earningsAndSettlement'),
                      ],
                    ),
                    Expanded(
                      child: TabBarView(
                        controller: _tabController,
                        children: [
                          _buildOverviewTab(context, state),
                          _OrderReturnsTab(orderId: widget.orderId),
                          _OrderEarningsTab(orderId: widget.orderId),
                        ],
                      ),
                    ),
                  ],
                );
              }
              return const SizedBox.shrink();
            },
          ),
        );
      },
    );
  }

  Widget _buildOverviewTab(BuildContext context, OrderDetailsLoaded state) {
    final currencySymbol = HiveStorage.currencySymbol;
    final data = state.orderData;
    final theme = Theme.of(context);

    return RefreshIndicator(
      color: AppColors.primaryColor,
      onRefresh: () async {
        context.read<OrderDetailsBloc>().add(FetchOrderDetails(widget.orderId));
      },
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(12, 14, 12, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _SectionCard(
              title: context.tr('orderSummary', fallback: 'Order summary'),
              children: [
                _KvGrid(
                  items: [
                    _KvItem(
                      k: context.tr('orderId', fallback: 'Order id :'),
                      v: '#${data.orderId}',
                    ),
                    _KvItem(
                      k: context.tr('orderDate', fallback: 'Placed'),
                      v: data.createdAt,
                    ),
                    _KvItem(
                      k: context.tr('statusLabel', fallback: 'Status'),
                      chip: _StatusChip(text: data.status),
                    ),
                    _KvItem(
                      k: context.tr('paymentStatus', fallback: 'Payment status'),
                      chip: _StatusChip(text: data.paymentStatus),
                    ),
                    _KvItem(
                      k: context.tr('totalPrice', fallback: 'Total'),
                      v: data.currency != null
                          ? MoneyFormatter.formatPayload(
                              double.tryParse(data.totalPrice) ?? 0,
                              data.currency!)
                          : '$currencySymbol ${data.totalPrice}',
                      emphasise: true,
                    ),
                    _KvItem(
                      k: context.tr('paymentMethod', fallback: 'Payment method'),
                      v: data.paymentMethod
                          .toUpperCase()
                          .replaceAll('_', ' '),
                    ),
                    if (data.fulfillmentStatus.isNotEmpty)
                      _KvItem(
                        k: context.tr('fulfillmentStatusLabel', fallback: 'Fulfillment'),
                        chip: _StatusChip(text: data.fulfillmentStatus),
                      ),
                  ],
                ),
                if (data.orderNote != null && data.orderNote!.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _NoticeBox(
                    icon: TablerIcons.note,
                    text: data.orderNote!,
                    color: theme.colorScheme.tertiary.withValues(alpha: 0.8),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 12),
            _SectionCard(
              title:
                  '${context.tr('orderItems', fallback: "Order items")} (${data.items?.length ?? 0})',
              trailing: _selectedItemIds.isNotEmpty
                  ? _ClearChip(
                      onTap: () =>
                          setState(() => _selectedItemIds.clear()),
                    )
                  : null,
              padContent: false,
              children: [
                ...() {
                  final items = data.items ?? [];
                  final selectionMode = _getSelectionMode(items);
                  return items.asMap().entries.map((entry) {
                    final index = entry.key;
                    final item = entry.value;
                    final bool isSame =
                        item.product?.title == item.variant?.title;
                    final itemId = item.orderItem?.id ?? item.id;

                    return _ExpandableOrderItem(
                      index: index,
                      item: item,
                      isSame: isSame,
                      currency: data.currency,
                      isLast: index == items.length - 1,
                      isSelected: _selectedItemIds.contains(itemId),
                      isSelectable:
                          _isItemSelectable(item, selectionMode),
                      showCheckbox: _isItemActionable(item),
                      onSelectionChanged: (_) =>
                          _toggleItemSelection(itemId),
                    );
                  }).toList();
                }(),
              ],
            ),
            const _FulfillmentSection(),
            const SizedBox(height: 12),
            _SectionCard(
              title: context.tr('customerInformation', fallback: 'Customer'),
              children: [
                _KvGrid(
                  items: [
                    _KvItem(
                      k: context.tr('customerName', fallback: 'Name'),
                      v: data.billingName,
                    ),
                    _KvItem(
                      k: context.tr('phoneLabel', fallback: 'Phone'),
                      v: data.billingPhone,
                    ),
                    _KvItem(k: context.tr('emailLabel', fallback: 'Email'), v: data.email),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 12),
            _SectionCard(
              title: context.tr('shippingAddress', fallback: 'Shipping address'),
              children: [
                _AddressBlock(
                  name: data.shippingName,
                  line1: data.shippingAddress1,
                  cityStateZip:
                      '${data.shippingCity}, ${data.shippingState} ${data.shippingZip}',
                  country: data.shippingCountry,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildShimmerLoading(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          _buildSectionShimmer(context, 200),
          const SizedBox(height: 12),
          _buildSectionShimmer(context, 160),
          const SizedBox(height: 12),
          _buildSectionShimmer(context, 160),
        ],
      ),
    );
  }

  Widget _buildSectionShimmer(BuildContext context, double height) {
    return Container(
      width: double.infinity,
      height: height,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Theme.of(context)
              .colorScheme
              .outlineVariant
              .withValues(alpha: 0.6),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          CustomShimmer(width: 150, height: 20),
          SizedBox(height: 16),
          Divider(height: 1),
          SizedBox(height: 16),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    CustomShimmer(width: 100, height: 16),
                    CustomShimmer(width: 150, height: 16),
                  ],
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    CustomShimmer(width: 100, height: 16),
                    CustomShimmer(width: 150, height: 16),
                  ],
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    CustomShimmer(width: 100, height: 16),
                    CustomShimmer(width: 80, height: 16),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget? _buildBottomBar(
    BuildContext context,
    OrderDetailsData data,
    bool isUpdating,
    dynamic l10n,
  ) {
    if (data.items == null || data.items!.isEmpty) return null;
    if (_selectedItemIds.isEmpty) return null;

    final selectionMode = _getSelectionMode(data.items!);
    final selectedCount = _selectedItemIds.length;
    final theme = Theme.of(context);

    if (selectionMode != 'pending') return null;

    return Container(
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 14,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        minimum: const EdgeInsets.only(bottom: 6),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.outlineVariant
                        .withValues(alpha: 0.4),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        TablerIcons.info_circle,
                        size: 14,
                        color: theme.colorScheme.tertiary
                            .withValues(alpha: 0.7),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        '$selectedCount item${selectedCount > 1 ? 's' : ''} selected',
                        style: _T.micro.copyWith(
                          color: theme.colorScheme.tertiary
                              .withValues(alpha: 0.75),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: SizedBox(
                      height: 50,
                      child: OutlinedButton.icon(
                        onPressed: isUpdating
                            ? null
                            : () => _handleReject(context, l10n),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(
                            color: AppColors.errorColor,
                            width: 1.5,
                          ),
                          foregroundColor: AppColors.errorColor,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                          textStyle: _T.body.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        icon: isUpdating
                            ? const SizedBox(
                                height: 18,
                                width: 18,
                                child: CustomLoadingIndicator(
                                  strokeWidth: 2,
                                  color: AppColors.errorColor,
                                ),
                              )
                            : const Icon(TablerIcons.circle_x, size: 18),
                        label: Text(context.tr('reject', fallback: 'Reject')),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: SizedBox(
                      height: 50,
                      child: ElevatedButton.icon(
                        onPressed: isUpdating
                            ? null
                            : () => _handleAccept(context, l10n),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green.shade600,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                          textStyle: _T.body.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        icon: isUpdating
                            ? const SizedBox(
                                height: 18,
                                width: 18,
                                child: CustomLoadingIndicator(
                                  color: Colors.white,
                                  strokeWidth: 2,
                                ),
                              )
                            : const Icon(TablerIcons.circle_check, size: 18),
                        label: Text(context.tr('accept', fallback: 'Accept')),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _handleAccept(BuildContext context, dynamic l10n) {
    if (!PermissionChecker.hasPermission(AppPermissions.orderUpdateStatus)) {
      showCustomSnackbar(
        context: context,
        message: "You don't have permission to update order status",
        isWarning: true,
      );
      return;
    }
    if (!DemoGuard.shouldProceed(context)) return;

    OrderDialogs.showAcceptDialog(context, () async {
      final detailsBloc = context.read<OrderDetailsBloc>();
      final ordersBloc = context.read<OrdersBloc>();
      final repo = context.read<OrdersRepo>();

      detailsBloc.add(const SetOrderDetailsBusy(true));
      try {
        await repo.fulfillOrder(widget.orderId, {});
        detailsBloc.add(FetchOrderDetails(widget.orderId));
        ordersBloc.add(RefreshOrders());
      } catch (e) {
        detailsBloc.add(const SetOrderDetailsBusy(false));
        if (!mounted) return;
        showCustomSnackbar(
          context: this.context,
          message: e.toString(),
          isError: true,
        );
      }
    });
  }

  void _handleReject(BuildContext context, dynamic l10n) {
    if (!PermissionChecker.hasPermission(AppPermissions.orderUpdateStatus)) {
      showCustomSnackbar(
        context: context,
        message: "You don't have permission to update order status",
        isWarning: true,
      );
      return;
    }
    if (!DemoGuard.shouldProceed(context)) return;

    final selectedIds = _selectedItemIds.toList();
    final reason = context.tr('rejectedBySeller', fallback: 'Rejected by seller');
    OrderDialogs.showRejectDialog(context, () async {
      final detailsBloc = context.read<OrderDetailsBloc>();
      final ordersBloc = context.read<OrdersBloc>();
      final repo = context.read<OrdersRepo>();

      detailsBloc.add(const SetOrderDetailsBusy(true));
      try {
        for (final itemId in selectedIds) {
          await repo.cancelOrderItem(itemId, reason: reason);
        }
        detailsBloc.add(FetchOrderDetails(widget.orderId));
        ordersBloc.add(RefreshOrders());
      } catch (e) {
        detailsBloc.add(const SetOrderDetailsBusy(false));
        if (!mounted) return;
        showCustomSnackbar(
          context: this.context,
          message: e.toString(),
          isError: true,
        );
      }
    });
  }
}

// ═════════════════════════════════════════════════════════════════════
// Order item (expandable)
// ═════════════════════════════════════════════════════════════════════

class _ExpandableOrderItem extends StatefulWidget {
  final int index;
  final OrderItemDetail item;
  final bool isSame;
  final CurrencyPayload? currency;
  final bool isLast;
  final bool isSelected;
  final bool isSelectable;
  final bool showCheckbox;
  final ValueChanged<bool>? onSelectionChanged;

  const _ExpandableOrderItem({
    required this.index,
    required this.item,
    required this.isSame,
    required this.currency,
    required this.isLast,
    this.isSelected = false,
    this.isSelectable = false,
    this.showCheckbox = false,
    this.onSelectionChanged,
  });

  @override
  State<_ExpandableOrderItem> createState() => _ExpandableOrderItemState();
}

class _ExpandableOrderItemState extends State<_ExpandableOrderItem> {
  bool _isExpanded = false;

  String _money(num v) {
    final c = widget.currency;
    if (c != null) return MoneyFormatter.formatPayload(v, c);
    return '${HiveStorage.currencySymbol} ${v.toStringAsFixed(2)}';
  }

  String _moneyStr(String v) => _money(double.tryParse(v) ?? 0);

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;

    return Container(
      color: widget.isSelected
          ? theme.colorScheme.outlineVariant.withValues(alpha: 0.25)
          : null,
      child: Column(
        children: [
          InkWell(
            onTap: () => setState(() => _isExpanded = !_isExpanded),
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 12,
              ),
              child: Column(
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (widget.showCheckbox)
                        _SquareCheckbox(
                          value: widget.isSelected,
                          enabled: widget.isSelectable,
                          onChanged: (v) =>
                              widget.onSelectionChanged?.call(v),
                        ),
                      if (widget.showCheckbox) const SizedBox(width: 10),
                      _ItemThumb(widget.item.product?.img ?? ''),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.item.product?.title ??
                                  (context.tr('notAvailable', fallback: 'N/A')),
                              style: _T.subtitle.copyWith(
                                color: widget.isSelectable ||
                                        widget.isSelected
                                    ? tertiary
                                    : tertiary.withValues(alpha: 0.5),
                              ),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            if (!widget.isSame &&
                                (widget.item.variant?.title ?? '')
                                    .isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(
                                widget.item.variant!.title,
                                style: _T.meta.copyWith(
                                  color:
                                      tertiary.withValues(alpha: 0.55),
                                ),
                              ),
                            ],
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Text(
                                  context.tr('qtyWithCount', positionalArgs: [widget.item.quantity], fallback: 'Qty ${widget.item.quantity}'),
                                  style: _T.meta.copyWith(
                                    color:
                                        tertiary.withValues(alpha: 0.6),
                                  ),
                                ),
                                Container(
                                  margin: const EdgeInsets.symmetric(
                                    horizontal: 8,
                                  ),
                                  width: 3,
                                  height: 3,
                                  decoration: BoxDecoration(
                                    color: tertiary.withValues(alpha: 0.3),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                Text(
                                  _money(widget.item.subtotal),
                                  style: _T.body.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: tertiary,
                                  ),
                                ),
                              ],
                            ),
                            if ((widget.item.addons)?.isNotEmpty ??
                                false) ...[
                              const SizedBox(height: 6),
                              Text(
                                "${context.tr('addOns', fallback: "Add-ons")}:"
                                    .toUpperCase(),
                                style: _T.micro.copyWith(
                                  color:
                                      tertiary.withValues(alpha: 0.55),
                                ),
                              ),
                              ...List.generate(
                                widget.item.addons?.length ?? 0,
                                (i) {
                                  final addon = widget.item.addons![i];
                                  final groupTitle = addon.group?.title ??
                                      (context.tr('addOns', fallback: "Add-on"));
                                  final itemTitle =
                                      addon.item?.title ?? "";
                                  return Text(
                                    '$groupTitle: $itemTitle · ${_money(addon.price)}',
                                    style: _T.meta.copyWith(
                                      color:
                                          tertiary.withValues(alpha: 0.6),
                                    ),
                                  );
                                },
                              ),
                            ],
                            if ((widget.item.orderItem?.status ?? '')
                                .isNotEmpty) ...[
                              const SizedBox(height: 8),
                              _StatusChip(
                                text: widget.item.orderItem!.status,
                                small: true,
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      AnimatedRotation(
                        turns: _isExpanded ? 0.5 : 0,
                        duration: const Duration(milliseconds: 180),
                        child: Icon(
                          Icons.keyboard_arrow_down_rounded,
                          color: tertiary.withValues(alpha: 0.5),
                        ),
                      ),
                    ],
                  ),
                  if (_isExpanded) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: theme.colorScheme.outlineVariant
                            .withValues(alpha: 0.18),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Column(
                        children: [
                          if (!widget.isSame)
                            _detailRow(
                              context,
                              context.tr('variant', fallback: 'Variant'),
                              widget.item.variant?.title ??
                                  (context.tr('notAvailable', fallback: 'N/A')),
                            ),
                          _detailRow(
                            context,
                            context.tr('quantity', fallback: 'Quantity'),
                            widget.item.quantity.toString(),
                          ),
                          _detailRow(
                            context,
                            context.tr('price', fallback: 'Price'),
                            _moneyStr(widget.item.price),
                          ),
                          if ((widget.item.addons)?.isNotEmpty ?? false)
                            _detailRow(
                              context,
                              context.tr('addOns', fallback: "Add-ons"),
                              _money(widget.item.addonsTotal),
                            ),
                          if ((widget.item.taxAmount).isNotEmpty)
                            _detailRow(
                              context,
                              context.tr('tax', fallback: "Tax"),
                              _moneyStr(widget.item.taxAmount),
                            ),
                          _detailRow(
                            context,
                            context.tr('subtotal', fallback: 'Subtotal'),
                            _moneyStr(widget.item.subTotal),
                            isLast: true,
                          ),
                          if (widget.item.attachments != null &&
                              widget.item.attachments!.isNotEmpty) ...[
                            const SizedBox(height: 12),
                            Divider(
                              height: 1,
                              thickness: 1,
                              color: theme.colorScheme.outlineVariant
                                  .withValues(alpha: 0.4),
                            ),
                            const SizedBox(height: 12),
                            Align(
                              alignment: Alignment.centerLeft,
                              child: Text(
                                (context.tr('attachments', fallback: 'Attachments'))
                                    .toUpperCase(),
                                style: _T.micro.copyWith(
                                  color:
                                      tertiary.withValues(alpha: 0.55),
                                ),
                              ),
                            ),
                            const SizedBox(height: 8),
                            SizedBox(
                              height: 80,
                              child: ListView.separated(
                                scrollDirection: Axis.horizontal,
                                itemCount:
                                    widget.item.attachments!.length,
                                separatorBuilder: (_, __) =>
                                    const SizedBox(width: 8),
                                itemBuilder: (context, i) {
                                  final attachment =
                                      widget.item.attachments![i];
                                  final url = attachment.toString();
                                  final isImage = url
                                              .toLowerCase()
                                              .endsWith('.jpg') ||
                                          url
                                              .toLowerCase()
                                              .endsWith('.jpeg') ||
                                          url
                                              .toLowerCase()
                                              .endsWith('.png') ||
                                          url
                                              .toLowerCase()
                                              .endsWith('.gif') ||
                                          url
                                              .toLowerCase()
                                              .endsWith('.webp');
                                  return GestureDetector(
                                    onTap: () async {
                                      final uri = Uri.parse(url);
                                      if (await canLaunchUrl(uri)) {
                                        await launchUrl(uri);
                                      }
                                    },
                                    child: Container(
                                      width: 80,
                                      height: 80,
                                      decoration: BoxDecoration(
                                        borderRadius:
                                            BorderRadius.circular(10),
                                        border: Border.all(
                                          color: theme.colorScheme
                                              .outlineVariant,
                                        ),
                                      ),
                                      clipBehavior: Clip.antiAlias,
                                      child: isImage
                                          ? CachedNetworkImage(
                                              imageUrl: url,
                                              fit: BoxFit.cover,
                                              placeholder: (c, u) =>
                                                  const Center(
                                                child: CustomShimmer(
                                                    width: 80, height: 80),
                                              ),
                                              errorWidget: (c, u, e) =>
                                                  const Icon(Icons.error),
                                            )
                                          : Center(
                                              child: Column(
                                                mainAxisAlignment:
                                                    MainAxisAlignment
                                                        .center,
                                                children: [
                                                  Icon(
                                                    TablerIcons.file,
                                                    color: tertiary
                                                        .withValues(
                                                            alpha: 0.6),
                                                  ),
                                                  const SizedBox(height: 4),
                                                  Text(
                                                    url.split('/').last,
                                                    style: _T.microXs
                                                        .copyWith(
                                                      letterSpacing: 0,
                                                      color: tertiary
                                                          .withValues(
                                                              alpha: 0.7),
                                                    ),
                                                    maxLines: 1,
                                                    overflow: TextOverflow
                                                        .ellipsis,
                                                  ),
                                                ],
                                              ),
                                            ),
                                    ),
                                  );
                                },
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          if (!widget.isLast)
            Divider(
              height: 1,
              thickness: 1,
              color: theme.colorScheme.outlineVariant
                  .withValues(alpha: 0.35),
              indent: 16,
              endIndent: 16,
            ),
        ],
      ),
    );
  }

  Widget _detailRow(BuildContext context, String label, String value,
      {bool isLast = false}) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Padding(
      padding: EdgeInsets.only(bottom: isLast ? 0 : 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: _T.meta.copyWith(color: tertiary.withValues(alpha: 0.7)),
          ),
          Text(
            value,
            style: _T.body.copyWith(color: tertiary),
          ),
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════════
// Returns tab
// ═════════════════════════════════════════════════════════════════════

class _OrderReturnsTab extends StatefulWidget {
  final int orderId;
  const _OrderReturnsTab({required this.orderId});

  @override
  State<_OrderReturnsTab> createState() => _OrderReturnsTabState();
}

class _OrderReturnsTabState extends State<_OrderReturnsTab> {
  late Future<OrderReturnsResponse> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<OrderReturnsResponse> _load() async {
    final res = await context.read<OrdersRepo>().getOrderReturns(widget.orderId);
    return OrderReturnsResponse.fromJson(res);
  }

  void _refresh() => setState(() => _future = _load());

  bool _isFinal(String s) =>
      {'refunded', 'declined', 'cancelled', 'closed'}.contains(s.toLowerCase());

  Future<void> _decide(OrderReturn r, bool approve) async {
    if (!DemoGuard.shouldProceed(context)) return;
    final repo = context.read<OrdersRepo>();

    String? reason;
    ReturnApproveResult? approval;
    if (approve) {
      approval = await ReturnApproveSheet.show(context, r);
      if (approval == null) return;
    } else {
      reason = await _askReason();
      if (reason == null) return;
    }

    try {
      final res = approve
          ? (approval!.labelPath != null
              ? await repo.approveReturnWithLabel(
                  r.id, approval.body, approval.labelPath!)
              : await repo.approveReturn(r.id, approval.body))
          : await repo.declineReturn(r.id, reason: reason);
      if (!mounted) return;
      showCustomSnackbar(
        context: context,
        message: res is Map ? (res['message']?.toString() ?? '') : '',
        isError: res is Map && res['success'] == false,
      );
      _refresh();
    } catch (e) {
      if (mounted) {
        showCustomSnackbar(
            context: context, message: e.toString(), isError: true);
      }
    }
  }

  Future<String?> _askReason() async {
    final controller = TextEditingController();
    final theme = Theme.of(context);
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        title: Text(
          context.tr('declineReturn', fallback: 'Decline return'),
          style: _T.title.copyWith(
            fontWeight: FontWeight.w800,
            color: theme.colorScheme.tertiary,
          ),
        ),
        content: TextField(
          controller: controller,
          decoration: InputDecoration(
            hintText: context.tr('reason', fallback: 'Reason'),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          maxLines: 2,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(
              context.tr('cancel', fallback: 'Cancel'),
              style: _T.body.copyWith(
                color:
                    theme.colorScheme.tertiary.withValues(alpha: 0.6),
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.errorColor,
            ),
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            child: Text(
              context.tr('confirm', fallback: 'Confirm'),
              style: _T.body.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<OrderReturnsResponse>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CustomLoadingIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: Text(
              snapshot.error.toString(),
              style: _T.body.copyWith(
                color: Theme.of(context).colorScheme.tertiary,
              ),
            ),
          );
        }
        final returns = snapshot.data?.returns ?? const [];
        if (returns.isEmpty) {
          return _EmptyState(
            icon: TablerIcons.package_off,
            text: context.tr('noReturnsFound', fallback: 'No returns for this order.'),
          );
        }
        final currency = snapshot.data?.currency;
        return RefreshIndicator(
          color: AppColors.primaryColor,
          onRefresh: () async => _refresh(),
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
            itemCount: returns.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) =>
                _returnCard(context, returns[i], null, currency),
          ),
        );
      },
    );
  }

  String _money(num v, CurrencyPayload? c) {
    if (c != null) return MoneyFormatter.formatPayload(v, c);
    return '${HiveStorage.currencySymbol} ${v.toStringAsFixed(2)}';
  }

  String _moneyStr(String v, CurrencyPayload? c) =>
      _money(double.tryParse(v) ?? 0, c);

  Widget _timestampRow(String label, String? iso) {
    if (iso == null || iso.isEmpty) return const SizedBox.shrink();
    final dt = DateTime.tryParse(iso);
    if (dt == null) return const SizedBox.shrink();
    return _KVRow(
      label: label,
      value: DateFormat('d MMM, yyyy · h:mm a').format(dt.toLocal()),
    );
  }

  Widget _returnCard(
    BuildContext context,
    OrderReturn r,
    dynamic l10n,
    CurrencyPayload? currency,
  ) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    final canDecide =
        !_isFinal(r.returnStatus) && r.returnStatus.toLowerCase() == 'requested';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if ((r.productImage ?? '').isNotEmpty) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: CachedNetworkImage(
                    imageUrl: r.productImage!,
                    width: 52,
                    height: 52,
                    fit: BoxFit.cover,
                    placeholder: (c, u) =>
                        const CustomShimmer(width: 52, height: 52),
                    errorWidget: (c, u, e) => Container(
                      width: 52,
                      height: 52,
                      color: theme.colorScheme.outlineVariant
                          .withValues(alpha: 0.4),
                      child: Icon(
                        Icons.image_not_supported_outlined,
                        color: tertiary.withValues(alpha: 0.5),
                        size: 20,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      r.productTitle ?? '#${r.id}',
                      style: _T.subtitle.copyWith(color: tertiary),
                    ),
                    if ((r.variantTitle ?? '').isNotEmpty &&
                        r.variantTitle != r.productTitle) ...[
                      const SizedBox(height: 2),
                      Text(
                        r.variantTitle!,
                        style: _T.meta.copyWith(
                          color: tertiary.withValues(alpha: 0.6),
                        ),
                      ),
                    ],
                    if ((r.sku ?? '').isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        'SKU: ${r.sku}',
                        style: _T.meta.copyWith(
                          color: tertiary.withValues(alpha: 0.55),
                          fontFamily: 'monospace',
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _StatusChip(text: r.returnStatus, small: true),
            ],
          ),
          if (r.reasonCode.isNotEmpty || r.reasonType.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                if (r.reasonCode.isNotEmpty) _ReasonCodeChip(reasonCode: r.reasonCode),
                if (r.reasonType.isNotEmpty) _ReasonTypeChip(reasonType: r.reasonType),
              ],
            ),
          ],
          if (r.reason != null && r.reason!.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              (context.tr('reason', fallback: 'Reason')).toUpperCase(),
              style: _T.micro.copyWith(
                color: tertiary.withValues(alpha: 0.55),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              r.reason!,
              style: _T.body.copyWith(
                fontWeight: FontWeight.w500,
                color: tertiary,
              ),
            ),
          ],
          if (r.addons.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              (context.tr('addOns', fallback: 'Add-ons')).toUpperCase(),
              style: _T.micro.copyWith(
                color: tertiary.withValues(alpha: 0.55),
              ),
            ),
            const SizedBox(height: 6),
            ...r.addons.map((a) => _AddonLine(addon: a, currency: currency)),
          ],
          if (r.images.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              (context.tr('attachments', fallback: 'Attachments')).toUpperCase(),
              style: _T.micro.copyWith(
                color: tertiary.withValues(alpha: 0.55),
              ),
            ),
            const SizedBox(height: 6),
            SizedBox(
              height: 64,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: r.images.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, i) {
                  final url = r.images[i];
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
                          color: theme.colorScheme.outlineVariant,
                        ),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: CachedNetworkImage(
                        imageUrl: url,
                        fit: BoxFit.cover,
                        placeholder: (c, u) => const CustomShimmer(width: 64, height: 64),
                        errorWidget: (c, u, e) => Icon(
                          Icons.broken_image_outlined,
                          color: tertiary.withValues(alpha: 0.5),
                          size: 22,
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
          if (r.customer != null) ...[
            const SizedBox(height: 12),
            Text(
              'CUSTOMER',
              style: _T.micro.copyWith(
                color: tertiary.withValues(alpha: 0.55),
              ),
            ),
            const SizedBox(height: 6),
            _CustomerLine(customer: r.customer!),
          ],
          const SizedBox(height: 12),
          Divider(
            height: 1,
            thickness: 1,
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
          ),
          const SizedBox(height: 10),
          _KVRow(
            label: context.tr('quantity', fallback: 'Quantity'),
            value: '${r.quantity}',
          ),
          _KVRow(
            label: '${context.tr('refund', fallback: "Refund")} ${context.tr('amount', fallback: "amount")}'.trim(),
            value: _moneyStr(r.refundAmount, currency),
            emphasize: true,
          ),
          if (!r.noShippingRequired &&
              (double.tryParse(r.returnShippingCharge) ?? 0) > 0)
            _KVRow(
              label: context.tr('returnShippingCharge', fallback: 'Return shipping charge'),
              value: _moneyStr(r.returnShippingCharge, currency),
            ),
          if (r.noShippingRequired)
            _KVRow(
              label: context.tr('returnShippingCharge', fallback: 'Return shipping'),
              value: context.tr('noShippingRequired', fallback: 'Not required'),
              muted: true,
            ),
          if (r.shippingCostPayer.isNotEmpty)
            _KVRow(
              label: 'Shipping paid by',
              value: r.shippingCostPayer.replaceAll('_', ' '),
            ),
          if ((r.reimbursementAmount ?? '').isNotEmpty &&
              (double.tryParse(r.reimbursementAmount!) ?? 0) > 0)
            _KVRow(
              label: 'Reimbursement',
              value: _moneyStr(r.reimbursementAmount!, currency),
            ),
          _timestampRow('Requested on', r.createdAt),
          _timestampRow('Seller approved', r.sellerApprovedAt),
          _timestampRow('Picked up', r.pickedUpAt),
          _timestampRow('Received', r.receivedAt),
          _timestampRow('Refund processed', r.refundProcessedAt),
          if ((r.returnCarrier ?? '').isNotEmpty)
            _KVRow(
              label: context.tr('carrierName', fallback: 'Carrier'),
              value: r.returnCarrier!,
            ),
          if ((r.returnTrackingNumber ?? '').isNotEmpty)
            _KVRow(
              label: context.tr('trackingNumber', fallback: 'Tracking number'),
              value: r.returnTrackingNumber!,
              mono: true,
            ),
          if ((r.returnLabelUrl ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            InkWell(
              onTap: () async {
                final uri = Uri.tryParse(r.returnLabelUrl!);
                if (uri != null && await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
              child: Row(
                children: [
                  Icon(Icons.picture_as_pdf_outlined,
                      size: 16, color: AppColors.primaryColor),
                  const SizedBox(width: 6),
                  Text(
                    'View return label',
                    style: _T.body.copyWith(
                      color: AppColors.primaryColor,
                      fontWeight: FontWeight.w700,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                ],
              ),
            ),
          ],
          if ((r.sellerComment ?? '').isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              'YOUR NOTE',
              style: _T.micro.copyWith(
                color: tertiary.withValues(alpha: 0.55),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              r.sellerComment!,
              style: _T.body.copyWith(
                fontWeight: FontWeight.w500,
                color: tertiary,
              ),
            ),
          ],
          if ((r.adminNotes ?? '').isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              'ADMIN NOTE',
              style: _T.micro.copyWith(
                color: tertiary.withValues(alpha: 0.55),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              r.adminNotes!,
              style: _T.body.copyWith(
                fontWeight: FontWeight.w500,
                color: tertiary,
              ),
            ),
          ],
          if (canDecide) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: SizedBox(
                    height: 46,
                    child: OutlinedButton(
                      onPressed: () => _decide(r, false),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.errorColor,
                        side: const BorderSide(
                          color: AppColors.errorColor,
                          width: 1.5,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        textStyle:
                            _T.body.copyWith(fontWeight: FontWeight.w800),
                      ),
                      child: Text(context.tr('decline', fallback: 'Decline')),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: SizedBox(
                    height: 46,
                    child: ElevatedButton(
                      onPressed: () => _decide(r, true),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green.shade600,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        textStyle:
                            _T.body.copyWith(fontWeight: FontWeight.w800),
                      ),
                      child: Text(context.tr('approve', fallback: 'Approve')),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════════
// Earnings tab
// ═════════════════════════════════════════════════════════════════════

class _OrderEarningsTab extends StatefulWidget {
  final int orderId;
  const _OrderEarningsTab({required this.orderId});

  @override
  State<_OrderEarningsTab> createState() => _OrderEarningsTabState();
}

class _OrderEarningsTabState extends State<_OrderEarningsTab> {
  late Future<OrderEarnings> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<OrderEarnings> _load() async {
    final res = await context.read<OrdersRepo>().getOrderEarnings(widget.orderId);
    return OrderEarnings.fromJson(res);
  }

  String _money(double v, OrderEarnings e) {
    final currency = e.currency;
    if (currency != null) return MoneyFormatter.formatPayload(v, currency);
    return '${HiveStorage.currencySymbol} ${v.toStringAsFixed(2)}';
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<OrderEarnings>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CustomLoadingIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: Text(
              snapshot.error.toString(),
              style: _T.body.copyWith(
                color: Theme.of(context).colorScheme.tertiary,
              ),
            ),
          );
        }
        final e = snapshot.data;
        if (e == null || e.groups.isEmpty) {
          return _EmptyState(
            icon: TablerIcons.coins,
            text: context.tr('noEarningsYet', fallback: 'No earnings for this order yet.'),
          );
        }
        final g = e.groups.first;
        return RefreshIndicator(
          color: AppColors.primaryColor,
          onRefresh: () async => setState(() => _future = _load()),
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _settlementBanner(context, e, g, null),
                const SizedBox(height: 12),
                _breakdownCard(context, e, g, null),
                const SizedBox(height: 12),
                _linesCard(context, e, g, null),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _settlementBanner(BuildContext context, OrderEarnings e,
      EarningsGroup g, dynamic l10n) {
    final owes = g.platformOwesSeller;
    // Amber earns its place here — this is where the seller sees the
    // money they will actually receive. When the seller OWES the
    // platform, we drop to a neutral orange to avoid celebrating a debt.
    final Color accent =
        owes ? AppColors.primaryColor : Colors.orange.shade600;
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            accent.withValues(alpha: 0.16),
            accent.withValues(alpha: 0.03),
          ],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: accent.withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(9),
                ),
                alignment: Alignment.center,
                child: Icon(
                  owes
                      ? TablerIcons.arrow_down_left
                      : TablerIcons.arrow_up_right,
                  size: 18,
                  color: accent,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                (owes ? 'Platform owes you' : 'You owe platform')
                    .toUpperCase(),
                style: _T.micro.copyWith(color: accent),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            _money(g.settlementAmount, e),
            style: _T.display.copyWith(
              color: theme.colorScheme.tertiary,
              letterSpacing: -0.4,
              height: 1.0,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            e.settled
                ? (context.tr('settled', fallback: 'Settled'))
                : (e.archive?.eligibleNow ?? false)
                    ? 'Eligible for settlement'
                    : 'Pending settlement',
            style: _T.meta.copyWith(
              color: theme.colorScheme.tertiary.withValues(alpha: 0.6),
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _breakdownCard(BuildContext context, OrderEarnings e,
      EarningsGroup g, dynamic l10n) {
    return _SectionCard(
      title: 'Breakdown',
      children: [
        _row(context, context.tr('subtotal', fallback: 'Subtotal'), _money(g.itemsSubtotal, e)),
        _row(context, 'Tax', _money(g.taxTotal, e)),
        _row(context, 'Commission', '- ${_money(g.commissionTotal, e)}',
            negative: true),
        if (g.shippingToSeller != 0)
          _row(context, 'Shipping', _money(g.shippingToSeller, e)),
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Divider(
            height: 1,
            thickness: 1,
            color: Theme.of(context)
                .colorScheme
                .outlineVariant
                .withValues(alpha: 0.6),
          ),
        ),
        _row(context, 'Net earning', _money(g.sellerNet, e), bold: true),
      ],
    );
  }

  Widget _linesCard(BuildContext context, OrderEarnings e, EarningsGroup g,
      dynamic l10n) {
    if (g.items.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return _SectionCard(
      title: 'Per-item breakdown',
      children: [
        ...g.items.map(
          (line) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${line.title}${line.variant.isNotEmpty ? " · ${line.variant}" : ""}  ×${line.quantity}',
                  style: _T.body.copyWith(
                    fontWeight: FontWeight.w700,
                    color: tertiary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${context.tr('subtotal', fallback: "Subtotal")} ${_money(line.subtotal, e)}  ·  '
                  'Commission ${_money(line.commission, e)} '
                  '(${line.commissionRate.toStringAsFixed(1)}%)  ·  '
                  'Earning ${_money(line.lineEarning, e)}',
                  style: _T.meta.copyWith(
                    color: tertiary.withValues(alpha: 0.6),
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _row(BuildContext context, String k, String v,
      {bool bold = false, bool negative = false}) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            k,
            style: _T.body.copyWith(
              fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
              color:
                  bold ? tertiary : tertiary.withValues(alpha: 0.7),
            ),
          ),
          Text(
            v,
            style: (bold ? _T.title : _T.body).copyWith(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
              color: negative ? AppColors.errorColor : tertiary,
            ),
          ),
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════════
// Fulfillment section
// ═════════════════════════════════════════════════════════════════════

class _FulfillmentSection extends StatelessWidget {
  const _FulfillmentSection();

  Future<void> _act(
    BuildContext context,
    OrderFulfillmentCubit cubit,
    Future<bool> Function() action,
  ) async {
    if (!DemoGuard.shouldProceed(context)) return;
    await action();
    if (!context.mounted) return;
    final s = cubit.state;
    if ((s.message ?? '').isNotEmpty) {
      showCustomSnackbar(
          context: context, message: s.message!, isError: s.messageIsError);
    }
    if (context.mounted) {
      context
          .read<OrderDetailsBloc>()
          .add(FetchOrderDetails(cubit.sellerOrderId));
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;

    return BlocBuilder<OrderFulfillmentCubit, OrderFulfillmentState>(
      builder: (context, state) {
        final cubit = context.read<OrderFulfillmentCubit>();
        final f = state.data;
        log('Order Fulfillment Checking ${f?.shipments}');

        if (state.isLoading && f == null) {
          return _SectionCard(
            title: context.tr('fulfillmentLabel', fallback: 'Fulfillment'),
            children: const [
              Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: Center(child: CustomLoadingIndicator()),
              ),
            ],
          );
        }
        if (f == null) {
          if (state.error == null) return const SizedBox.shrink();
          return _SectionCard(
            title: context.tr('fulfillmentLabel', fallback: 'Fulfillment'),
            children: [
              Text(
                state.error!,
                style: _T.body.copyWith(color: AppColors.errorColor),
              ),
            ],
          );
        }

        // Always render the fulfillment section — the "Create parcel"
        // chip needs to be visible BEFORE any parcels exist, otherwise
        // the seller has no way to fulfil the order. The `if
        // (f.shipments.isNotEmpty)` guard on the parcels list further
        // down already hides that sub-list when there aren't any yet.
        return Column(
          children: [
            const SizedBox(height: 12),
            _SectionCard(
              title:
                  '${context.tr('fulfillmentLabel', fallback: "Fulfillment")} · ${f.methodLabel}',
              trailing: state.isBusy
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CustomLoadingIndicator(strokeWidth: 2),
                    )
                  : null,
              children: [
                if (!f.supported)
                  _NoticeBox(
                    icon: TablerIcons.alert_triangle,
                    text: context.tr('methodNotAvailableYet', fallback: 'This fulfillment method is not available yet.'),
                    color: Colors.orange.shade700,
                  ),
                if (f.shipBy != null) ...[
                  Row(
                    children: [
                      Icon(
                        TablerIcons.clock,
                        size: 14,
                        color: f.shipByOverdue
                            ? AppColors.errorColor
                            : tertiary.withValues(alpha: 0.6),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        '${context.tr('shipBy', fallback: "Ship by")}: ${f.shipBy}',
                        style: _T.meta.copyWith(
                          fontWeight: FontWeight.w700,
                          color: f.shipByOverdue
                              ? AppColors.errorColor
                              : tertiary.withValues(alpha: 0.75),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                ],
                if (f.lines.isNotEmpty) ...[
                  ...f.lines.map(
                    (l) => Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${l.title}${l.variant.isNotEmpty ? " · ${l.variant}" : ""}',
                              style: _T.meta.copyWith(color: tertiary),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: theme.colorScheme.outlineVariant
                                  .withValues(alpha: 0.4),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              '${l.shipped}/${l.ordered} ${context.tr('shipped', fallback: "shipped")}',
                              style: _T.microXs.copyWith(
                                color:
                                    tertiary.withValues(alpha: 0.75),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                ],
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (f.canFulfill && f.supported)
                      _AmberPrimaryChip(
                        icon: TablerIcons.package,
                        label: context.tr('createParcel', fallback: 'Create parcel'),
                        onTap: state.isBusy
                            ? null
                            : () async {
                                if (f.isPickup) {
                                  await _act(context, cubit,
                                      () => cubit.fulfill({}));
                                  return;
                                }
                                final body = await FulfillmentSheet.show(context,
                                    fulfillment: f);
                                if (body == null || !context.mounted) return;
                                await _act(
                                    context, cubit, () => cubit.fulfill(body));
                              },
                      ),
                    if (f.canSetStatus && f.isManual)
                      _AmberOutlineChip(
                        icon: TablerIcons.adjustments,
                        label: context.tr('setStatus', fallback: 'Set status'),
                        onTap: state.isBusy
                            ? null
                            : () async {
                                final status = await _pickStatus(
                                    context, f.fulfillmentStatus);
                                if (status == null || !context.mounted) return;
                                await _act(context, cubit,
                                    () => cubit.setFulfillmentStatus(status));
                              },
                      ),
                  ],
                ),
                if (f.shipments.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Text(
                        (context.tr('shipmentsLabel', fallback: 'Parcels')).toUpperCase(),
                        style: _T.micro.copyWith(
                          color: tertiary.withValues(alpha: 0.55),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: theme.colorScheme.outlineVariant
                              .withValues(alpha: 0.4),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          '${f.shipments.length}',
                          style: _T.microXs.copyWith(
                            color: tertiary.withValues(alpha: 0.7),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ...f.shipments.map(
                    (s) => _ParcelCard(fulfillment: f, shipment: s, onAct: _act),
                  ),
                ],
              ],
            ),
          ],
        );
      },
    );
  }

  Future<String?> _pickStatus(BuildContext context, String current) {
    final theme = Theme.of(context);
    const options = ['unfulfilled', 'in_progress', 'on_hold'];
    return showModalBottomSheet<String>(
      context: context,
      backgroundColor: theme.scaffoldBackgroundColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 10, bottom: 4),
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: theme.colorScheme.outlineVariant,
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
              child: Row(
                children: [
                  Text(
                    context.tr('setStatus', fallback: 'Set status'),
                    style: _T.title.copyWith(
                      color: theme.colorScheme.tertiary,
                    ),
                  ),
                ],
              ),
            ),
            ...options.map(
              (o) => ListTile(
                title: Text(
                  o.replaceAll('_', ' ').toUpperCase(),
                  style: _T.micro.copyWith(
                    color: theme.colorScheme.tertiary,
                  ),
                ),
                trailing: o == current
                    ? Icon(
                        Icons.check_rounded,
                        color: theme.colorScheme.tertiary,
                      )
                    : null,
                onTap: () => Navigator.pop(ctx, o),
              ),
            ),
            const SizedBox(height: 10),
          ],
        ),
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════════
// Parcel card — neutral shell (was amber-tinted); amber reserved for
// the create-parcel / set-status CTAs above and the settlement banner.
// ═════════════════════════════════════════════════════════════════════

class _ParcelCard extends StatelessWidget {
  final OrderFulfillment fulfillment;
  final ParcelShipment shipment;
  final Future<void> Function(BuildContext, OrderFulfillmentCubit,
      Future<bool> Function()) onAct;

  const _ParcelCard({
    required this.fulfillment,
    required this.shipment,
    required this.onAct,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    final cubit = context.read<OrderFulfillmentCubit>();
    final busy = context.select<OrderFulfillmentCubit, bool>(
        (c) => c.state.isBusy);
    final s = shipment;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: theme.colorScheme.outlineVariant.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.55),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 30,
                height: 30,
                decoration: BoxDecoration(
                  color: theme.colorScheme.outlineVariant
                      .withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(8),
                ),
                alignment: Alignment.center,
                child: Icon(
                  TablerIcons.package,
                  size: 16,
                  color: tertiary.withValues(alpha: 0.75),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '#${s.id}  ${s.carrierName ?? s.providerCode ?? ""}',
                      style: _T.body.copyWith(
                        fontWeight: FontWeight.w800,
                        color: tertiary,
                      ),
                    ),
                    if (s.awbNumber != null || s.trackingNumber != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(
                          '${context.tr('awbLabel', fallback: "AWB")}: ${s.awbNumber ?? s.trackingNumber}',
                          style: _T.meta.copyWith(
                            fontWeight: FontWeight.w600,
                            color: tertiary.withValues(alpha: 0.6),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              _StatusChip(
                text: s.statusLabel.isNotEmpty ? s.statusLabel : s.status,
                small: true,
              ),
            ],
          ),
          if (s.products.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              s.products.map((p) => '${p.title} ×${p.quantity}').join(', '),
              style: _T.meta.copyWith(
                color: tertiary.withValues(alpha: 0.6),
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          if (s.bookingError != null) ...[
            const SizedBox(height: 8),
            _NoticeBox(
              icon: TablerIcons.alert_circle,
              text: s.bookingError!,
              color: AppColors.errorColor,
              dense: true,
            ),
          ] else if (s.bookingWarning != null) ...[
            const SizedBox(height: 8),
            _NoticeBox(
              icon: TablerIcons.alert_triangle,
              text: s.bookingWarning!,
              color: Colors.orange.shade700,
              dense: true,
            ),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if (s.canDeliver)
                _ActionChip(
                  icon: TablerIcons.circle_check,
                  label: context.tr('markDelivered', fallback: 'Delivered'),
                  onTap: busy
                      ? null
                      : () => onAct(context, cubit,
                          () => cubit.transition(s.id, 'delivered')),
                ),
              if (s.canCancel)
                _ActionChip(
                  icon: TablerIcons.x,
                  label: context.tr('cancel', fallback: 'Cancel'),
                  color: AppColors.errorColor,
                  onTap: busy
                      ? null
                      : () => onAct(context, cubit,
                          () => cubit.transition(s.id, 'cancelled')),
                ),
              if (s.canUpdateLines)
                _ActionChip(
                  icon: TablerIcons.list_check,
                  label: context.tr('editItems', fallback: 'Edit items'),
                  onTap: busy
                      ? null
                      : () async {
                          final body = await FulfillmentSheet.show(
                            context,
                            fulfillment: fulfillment,
                            editing: s,
                          );
                          if (body == null || !context.mounted) return;
                          await onAct(context, cubit,
                              () => cubit.updateShipmentLines(s.id, body));
                        },
                ),
              if (s.canEdit)
                _ActionChip(
                  icon: TablerIcons.edit,
                  label: context.tr('editFulfillment', fallback: 'Edit'),
                  onTap: busy
                      ? null
                      : () async {
                          final body = await EditShipmentSheet.show(context, s);
                          if (body == null || !context.mounted) return;
                          await onAct(context, cubit,
                              () => cubit.editShipment(s.id, body));
                        },
                ),
              if (s.canNdr)
                _ActionChip(
                  icon: TablerIcons.alert_triangle,
                  label: context.tr('ndrAction', fallback: 'NDR'),
                  onTap: busy
                      ? null
                      : () async {
                          final res = await NdrActionSheet.show(context);
                          if (res == null || !context.mounted) return;
                          await onAct(
                            context,
                            cubit,
                            () => cubit.ndrAction(
                                s.id, res['action']!, res['comments']!),
                          );
                        },
                ),
              if (s.canSync)
                _ActionChip(
                  icon: TablerIcons.refresh,
                  label: context.tr('shiprocketSyncLabel', fallback: 'Sync'),
                  onTap: busy
                      ? null
                      : () => onAct(context, cubit, () => cubit.sync(s.id)),
                ),
              if (s.canRetry)
                _ActionChip(
                  icon: TablerIcons.reload,
                  label: context.tr('retryBooking', fallback: 'Retry'),
                  onTap: busy
                      ? null
                      : () =>
                          onAct(context, cubit, () => cubit.retryBooking(s.id)),
                ),
              if (s.trackingUrl != null)
                _ActionChip(
                  icon: TablerIcons.map_pin,
                  label: context.tr('track', fallback: 'Track'),
                  onTap: () => ExternalLink.launchAdminUrl(s.trackingUrl!,
                      context: context),
                ),
              if (s.labelUrl != null)
                _ActionChip(
                  icon: TablerIcons.tag,
                  label: context.tr('label', fallback: 'Label'),
                  onTap: () => ExternalLink.launchAdminUrl(s.labelUrl!,
                      context: context),
                ),
              if (s.manifestUrl != null)
                _ActionChip(
                  icon: TablerIcons.file_text,
                  label: context.tr('manifest', fallback: 'Manifest'),
                  onTap: () => ExternalLink.launchAdminUrl(s.manifestUrl!,
                      context: context),
                ),
              if (s.invoiceUrl != null)
                _ActionChip(
                  icon: TablerIcons.receipt,
                  label: context.tr('invoice', fallback: 'Invoice'),
                  onTap: () => ExternalLink.launchAdminUrl(s.invoiceUrl!,
                      context: context),
                ),
              if (s.canPrintSlip && !s.isShiprocket)
                _ActionChip(
                  icon: TablerIcons.file,
                  label: context.tr('packingSlip', fallback: 'Packing slip'),
                  onTap: () => ExternalLink.launchAdminUrl(
                    ApiRoutes.shipmentPackingSlipApi(s.id),
                    context: context,
                    isTokenNeeded: true,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════════
// Local widgets
// ═════════════════════════════════════════════════════════════════════

class _StatusHeader extends StatelessWidget {
  final OrderDetailsData data;
  const _StatusHeader({required this.data});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
      decoration: BoxDecoration(
        // Was amber @0.14 — softened to a neutral tint so the amber isn't
        // repeated all over the screen.
        color: AppColors.primaryColor.withValues(alpha: 0.10),
        /*border: Border(
          bottom: BorderSide(
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
          ),
        ),*/
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Order ID : #${data.orderId}',
                  style: _T.subtitle.copyWith(
                    fontWeight: FontWeight.w800,
                    color: tertiary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Text(
                  data.createdAt,
                  style: _T.meta.copyWith(
                    color: tertiary.withValues(alpha: 0.55),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          _StatusChip(text: data.status),
        ],
      ),
    );
  }
}

/// Segmented pill tab bar — content-sized, horizontally scrollable.
class _SegmentedTabBar extends StatelessWidget {
  final TabController controller;
  final List<String> labels;

  const _SegmentedTabBar({
    required this.controller,
    required this.labels,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      color: theme.scaffoldBackgroundColor,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      child: AnimatedBuilder(
        animation: controller,
        builder: (context, _) {
          return LayoutBuilder(
            builder: (context, constraints) {
              final trackWidth = constraints.maxWidth;
              return Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: theme.colorScheme.outlineVariant
                      .withValues(alpha: 0.35),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  physics: const BouncingScrollPhysics(),
                  child: ConstrainedBox(
                    constraints: BoxConstraints(
                      minWidth: trackWidth - 8, // minus 2×4 padding
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: List.generate(labels.length, (i) {
                        final isActive = controller.index == i;
                        return Padding(
                          padding: EdgeInsets.only(
                            right: i == labels.length - 1 ? 0 : 4,
                          ),
                          child: _SegmentedTabPill(
                            label: labels[i],
                            isActive: isActive,
                            onTap: () => controller.animateTo(i),
                          ),
                        );
                      }),
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _SegmentedTabPill extends StatelessWidget {
  final String label;
  final bool isActive;
  final VoidCallback onTap;

  const _SegmentedTabPill({
    required this.label,
    required this.isActive,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: isActive ? AppColors.primaryColor : Colors.transparent,
      borderRadius: BorderRadius.circular(9),
      child: InkWell(
        borderRadius: BorderRadius.circular(9),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Text(
            label,
            style: _T.body.copyWith(
              fontWeight: FontWeight.w700,
              color: isActive
                  ? Colors.black
                  : theme.colorScheme.tertiary.withValues(alpha: 0.65),
            ),
            maxLines: 1,
            softWrap: false,
          ),
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final List<Widget> children;
  final Widget? trailing;
  final bool padContent;

  const _SectionCard({
    required this.title,
    required this.children,
    this.trailing,
    this.padContent = true,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 12, 12),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: _T.title.copyWith(
                      color: theme.colorScheme.tertiary,
                    ),
                  ),
                ),
                if (trailing != null) trailing!,
              ],
            ),
          ),
          Divider(
            height: 1,
            thickness: 1,
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
          ),
          Padding(
            padding: padContent
                ? const EdgeInsets.fromLTRB(16, 14, 16, 14)
                : EdgeInsets.zero,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: children,
            ),
          ),
        ],
      ),
    );
  }
}

class _KvItem {
  final String k;
  final String? v;
  final Widget? chip;
  final bool emphasise;

  const _KvItem({
    required this.k,
    this.v,
    this.chip,
    this.emphasise = false,
  });
}

/// Stacked KV pairs laid out in a 2-column grid.
class _KvGrid extends StatelessWidget {
  final List<_KvItem> items;
  const _KvGrid({required this.items});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final oneCol = constraints.maxWidth < 320;
        final colCount = oneCol ? 1 : 2;
        return Wrap(
          spacing: 12,
          runSpacing: 14,
          children: items.map((item) {
            final w =
                (constraints.maxWidth - (colCount - 1) * 12) / colCount;
            return SizedBox(width: w, child: _kv(context, item));
          }).toList(),
        );
      },
    );
  }

  Widget _kv(BuildContext context, _KvItem item) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          item.k.toUpperCase(),
          style: _T.micro.copyWith(
            color: tertiary.withValues(alpha: 0.55),
          ),
        ),
        const SizedBox(height: 5),
        if (item.chip != null)
          item.chip!
        else
          Text(
            (item.v == null || item.v!.isEmpty) ? '—' : item.v!,
            // Emphasis via WEIGHT (was via amber colour). Total etc still
            // stand out against surrounding rows.
            style: (item.emphasise ? _T.subtitle : _T.body).copyWith(
              fontWeight:
                  item.emphasise ? FontWeight.w800 : FontWeight.w700,
              color: tertiary,
            ),
          ),
      ],
    );
  }
}

class _AddressBlock extends StatelessWidget {
  final String? name;
  final String? line1;
  final String? cityStateZip;
  final String? country;

  const _AddressBlock({
    this.name,
    this.line1,
    this.cityStateZip,
    this.country,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if ((name ?? '').isNotEmpty)
          Text(
            name!,
            style: _T.subtitle.copyWith(color: tertiary),
          ),
        const SizedBox(height: 4),
        if ((line1 ?? '').isNotEmpty)
          Text(
            line1!,
            style: _T.body.copyWith(
              fontWeight: FontWeight.w500,
              color: tertiary.withValues(alpha: 0.85),
              height: 1.6,
            ),
          ),
        if ((cityStateZip ?? '').isNotEmpty)
          Text(
            cityStateZip!,
            style: _T.body.copyWith(
              fontWeight: FontWeight.w500,
              color: tertiary.withValues(alpha: 0.85),
              height: 1.6,
            ),
          ),
        if ((country ?? '').isNotEmpty)
          Text(
            country!,
            style: _T.body.copyWith(
              fontWeight: FontWeight.w500,
              color: tertiary.withValues(alpha: 0.85),
              height: 1.6,
            ),
          ),
      ],
    );
  }
}

/// Placeholder thumbnail for order items.
class _ItemThumb extends StatelessWidget {
  final String img;
  const _ItemThumb(this.img);

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: 54,
      height: 54,
      decoration: BoxDecoration(
        color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(10),
      ),
      clipBehavior: Clip.antiAlias,
      alignment: Alignment.center,
      child: img.isNotEmpty ? CustomNetworkImage(
        imageUrl: img,
        fit: BoxFit.contain,
      ) : Icon(
        TablerIcons.package,
        size: 22,
        color: theme.colorScheme.tertiary.withValues(alpha: 0.5),
      ),
    );
  }
}

class _SquareCheckbox extends StatelessWidget {
  final bool value;
  final bool enabled;
  final ValueChanged<bool> onChanged;

  const _SquareCheckbox({
    required this.value,
    required this.enabled,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return GestureDetector(
      onTap: enabled ? () => onChanged(!value) : null,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        margin: const EdgeInsets.only(top: 2),
        width: 22,
        height: 22,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(6),
          color: value ? AppColors.primaryColor : Colors.transparent,
          border: Border.all(
            color: !enabled
                ? theme.colorScheme.outlineVariant
                    .withValues(alpha: 0.4)
                : (value
                    ? AppColors.primaryColor
                    : theme.colorScheme.outlineVariant),
            width: 1.5,
          ),
        ),
        child: value
            ? const Icon(Icons.check_rounded, size: 15, color: Colors.black)
            : null,
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String text;
  final bool small;

  const _StatusChip({required this.text, this.small = false});

  @override
  Widget build(BuildContext context) {
    final v = text.toLowerCase().replaceAll('_', ' ');

    Color color;
    if (v.contains('pending') ||
        v.contains('awaiting') ||
        v.contains('partially')) {
      color = Colors.orange.shade700;
    } else if (v.contains('accepted') ||
        v.contains('completed') ||
        v.contains('delivered') ||
        v.contains('refunded') ||
        v.contains('received')) {
      color = Colors.green.shade600;
    } else if (v.contains('preparing') || v.contains('in transit')) {
      color = Colors.blue.shade600;
    } else if (v.contains('ready')) {
      color = Colors.purple.shade500;
    } else if (v.contains('rejected') ||
        v.contains('cancelled') ||
        v.contains('declined')) {
      color = AppColors.errorColor;
    } else {
      color = Colors.grey.shade600;
    }

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: small ? 8 : 10,
        vertical: small ? 3 : 5,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        v.toUpperCase(),
        style: (small ? _T.microXs : _T.micro).copyWith(color: color),
      ),
    );
  }
}

class _ReasonTypeChip extends StatelessWidget {
  final String reasonType;
  const _ReasonTypeChip({required this.reasonType});

  @override
  Widget build(BuildContext context) {
    final isSeller = reasonType.toUpperCase().contains('SELLER');
    final color = isSeller ? AppColors.errorColor : Colors.blueGrey.shade600;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        reasonType.replaceAll('_', ' ').toUpperCase(),
        style: _T.micro.copyWith(color: color),
      ),
    );
  }
}

class _ReasonCodeChip extends StatelessWidget {
  final String reasonCode;
  const _ReasonCodeChip({required this.reasonCode});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(
          color: theme.colorScheme.outlineVariant,
          width: 1,
        ),
      ),
      child: Text(
        reasonCode.replaceAll('_', ' ').toUpperCase(),
        style: _T.micro.copyWith(color: tertiary.withValues(alpha: 0.75)),
      ),
    );
  }
}

class _AddonLine extends StatelessWidget {
  final ReturnAddon addon;
  final CurrencyPayload? currency;
  const _AddonLine({required this.addon, required this.currency});

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

  String _priceText() {
    if (addon.priceFormatted.isNotEmpty) return addon.priceFormatted;
    if (currency != null) return MoneyFormatter.formatPayload(addon.price, currency!);
    return '${HiveStorage.currencySymbol} ${addon.price.toStringAsFixed(2)}';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    final subLabel = [
      if (addon.groupTitle.isNotEmpty) addon.groupTitle,
      if (addon.groupSelectionType.isNotEmpty)
        addon.groupSelectionType == 'single' ? 'single choice' : 'multi choice',
      if (addon.groupIsRequired) 'required',
    ].join(' · ');
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.only(top: 5),
            width: 10,
            height: 10,
            decoration: BoxDecoration(
              shape: BoxShape.rectangle,
              borderRadius: BorderRadius.circular(2),
              border: Border.all(
                color: _indicatorColor(addon.itemIndicator),
                width: 1.5,
              ),
            ),
            alignment: Alignment.center,
            child: Container(
              width: 4,
              height: 4,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: _indicatorColor(addon.itemIndicator),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  addon.itemTitle.isNotEmpty ? addon.itemTitle : '—',
                  style: _T.body.copyWith(
                    fontWeight: FontWeight.w700,
                    color: tertiary,
                  ),
                ),
                if (subLabel.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    subLabel,
                    style: _T.meta.copyWith(
                      color: tertiary.withValues(alpha: 0.55),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 12),
          Text(
            _priceText(),
            style: _T.body.copyWith(
              fontWeight: FontWeight.w700,
              color: tertiary,
            ),
          ),
        ],
      ),
    );
  }
}

class _CustomerLine extends StatelessWidget {
  final ReturnCustomer customer;
  const _CustomerLine({required this.customer});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    final hasMobile = (customer.mobile ?? '').isNotEmpty;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        CircleAvatar(
          radius: 18,
          backgroundColor:
              theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
          backgroundImage: (customer.profileImage ?? '').isNotEmpty
              ? CachedNetworkImageProvider(customer.profileImage!)
              : null,
          child: (customer.profileImage ?? '').isEmpty
              ? Icon(
                  Icons.person_outline,
                  color: tertiary.withValues(alpha: 0.6),
                  size: 20,
                )
              : null,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                (customer.name ?? '').isNotEmpty ? customer.name! : 'Customer',
                style: _T.body.copyWith(
                  fontWeight: FontWeight.w700,
                  color: tertiary,
                ),
              ),
              if ((customer.email ?? '').isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(
                  customer.email!,
                  style: _T.meta.copyWith(
                    color: tertiary.withValues(alpha: 0.6),
                  ),
                ),
              ],
            ],
          ),
        ),
        if (hasMobile)
          InkWell(
            borderRadius: BorderRadius.circular(20),
            onTap: () => ExternalLink.makePhoneCall(context, customer.mobile),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: AppColors.primaryColor.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                children: [
                  Icon(Icons.call_outlined,
                      size: 14, color: AppColors.primaryColor),
                  const SizedBox(width: 6),
                  Text(
                    customer.mobile!,
                    style: _T.meta.copyWith(
                      color: AppColors.primaryColor,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _KVRow extends StatelessWidget {
  final String label;
  final String value;
  final bool emphasize;
  final bool muted;
  final bool mono;

  const _KVRow({
    required this.label,
    required this.value,
    this.emphasize = false,
    this.muted = false,
    this.mono = false,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    final valueColor = muted
        ? tertiary.withValues(alpha: 0.55)
        : (emphasize ? AppColors.primaryColor : tertiary);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              label,
              style: _T.meta.copyWith(
                color: tertiary.withValues(alpha: 0.65),
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: _T.body.copyWith(
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
}

class _NoticeBox extends StatelessWidget {
  final IconData icon;
  final String text;
  final Color color;
  final bool dense;

  const _NoticeBox({
    required this.icon,
    required this.text,
    required this.color,
    this.dense = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(bottom: dense ? 0 : 10),
      padding: EdgeInsets.symmetric(
        horizontal: dense ? 10 : 12,
        vertical: dense ? 8 : 10,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 15, color: color),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: _T.meta.copyWith(
                fontWeight: FontWeight.w600,
                color: color,
                height: 1.5,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ClearChip extends StatelessWidget {
  final VoidCallback onTap;
  const _ClearChip({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.errorColor.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: AppColors.errorColor.withValues(alpha: 0.35),
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                TablerIcons.circle_x,
                size: 12,
                color: AppColors.errorColor,
              ),
              const SizedBox(width: 4),
              Text(
                context.tr('clear', fallback: 'Clear'),
                style: _T.micro.copyWith(
                  color: AppColors.errorColor,
                  letterSpacing: 0.04,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AmberPrimaryChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  const _AmberPrimaryChip({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final bool enabled = onTap != null;
    return Material(
      color: enabled
          ? AppColors.primaryColor
          : AppColors.primaryColor.withValues(alpha: 0.4),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 15, color: Colors.black),
              const SizedBox(width: 6),
              Text(
                label,
                style: _T.meta.copyWith(
                  fontWeight: FontWeight.w800,
                  color: Colors.black,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AmberOutlineChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  const _AmberOutlineChip({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: AppColors.primaryColor,
              width: 1.2,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 15, color: AppColors.primaryColor),
              const SizedBox(width: 6),
              Text(
                label,
                style: _T.meta.copyWith(
                  fontWeight: FontWeight.w800,
                  color: AppColors.primaryColor,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Compact action chip used inside parcel cards.
/// Default is neutral (tertiary muted); pass `color` for a semantic
/// action (e.g. red for cancel).
class _ActionChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color? color;
  final VoidCallback? onTap;

  const _ActionChip({
    required this.icon,
    required this.label,
    this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final Color c = color ?? theme.colorScheme.tertiary;
    final bool enabled = onTap != null;
    final Color effective = enabled
        ? (color == null ? c.withValues(alpha: 0.85) : c)
        : c.withValues(alpha: 0.4);
    return Material(
      color: theme.cardColor,
      borderRadius: BorderRadius.circular(9),
      child: InkWell(
        borderRadius: BorderRadius.circular(9),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(9),
            border: Border.all(
              color: effective.withValues(alpha: 0.5),
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 13, color: effective),
              const SizedBox(width: 5),
              Text(
                label,
                style: _T.meta.copyWith(
                  fontWeight: FontWeight.w700,
                  color: effective,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String text;

  const _EmptyState({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: theme.colorScheme.outlineVariant.withValues(alpha: 0.3),
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Icon(
                icon,
                size: 30,
                color: tertiary.withValues(alpha: 0.5),
              ),
            ),
            const SizedBox(height: 14),
            Text(
              text,
              textAlign: TextAlign.center,
              style: _T.body.copyWith(
                color: tertiary.withValues(alpha: 0.6),
                height: 1.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
