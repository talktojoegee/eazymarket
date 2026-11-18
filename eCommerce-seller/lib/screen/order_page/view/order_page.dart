import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/bloc/settings/shipping_settings.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/screen/order_page/bloc/orders/orders_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_card.dart';
import 'package:hyper_commerce_seller/utils/debouncer.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/bloc/screen_size/screen_size_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/order_page/widgets/order_dialogs.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/screen/order_page/repo/order_repo.dart';

class OrderPage extends StatefulWidget {
  const OrderPage({super.key});

  @override
  State<OrderPage> createState() => _OrderPageState();
}

class _OrderPageState extends State<OrderPage> with SingleTickerProviderStateMixin {
  final ScrollController _scrollController = ScrollController();
  final Debouncer _debouncer = Debouncer(milliseconds: 500);
  late TabController _tabController;

  // Built from `ShippingSettings` at startup. First tab is always "All"
  // (null filter); manual + shiprocket tabs only show when the admin has
  // enabled that mode in settings. Labels + wire values also come from
  // settings, so the admin can rebrand "Manual" → "Self Ship" without a
  // rebuild of the app.
  late List<_MethodTab> _methodTabs;

  void _rebuildMethodTabs() {
    final s = HiveStorage.shippingSettings;
    _methodTabs = _buildMethodTabsFor(s);
  }

  static List<_MethodTab> _buildMethodTabsFor(ShippingSettings s) {
    return <_MethodTab>[
      const _MethodTab(value: null, label: 'All'),
      if (s.manualEnabled)
        _MethodTab(value: s.manualValue, label: s.manualTitle),
      if (s.shiprocketEnabled)
        _MethodTab(value: s.shiprocketValue, label: s.shiprocketTitle),
    ];
  }

  @override
  void initState() {
    super.initState();
    _rebuildMethodTabs();
    _scrollController.addListener(_onScroll);
    _tabController = TabController(length: _methodTabs.length, vsync: this);
    _tabController.addListener(_onTabChanged);
  }

  void _onTabChanged() {
    if (_tabController.indexIsChanging) return;
    context.read<OrdersBloc>().add(
          SetFulfillmentMethodTab(_methodTabs[_tabController.index].value),
        );
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    _tabController.removeListener(_onTabChanged);
    _tabController.dispose();
    _debouncer.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_isBottom) {
      context.read<OrdersBloc>().add(LoadMoreOrders());
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
    return BlocBuilder<ScreenSizeBloc, ScreenSizeState>(
      builder: (context, screenSizeState) {
        final screenType = screenSizeState.screenType;

        return CustomScaffold(
          title: context.tr('orders', fallback: "Orders"),
          centerTitle: true,
          showAppbar: true,
          // Hide the Order Returns shortcut entirely when the user lacks
          // `return.view` — previously the icon showed for every user.
          appBarActions: [
            if (PermissionChecker.hasPermission(AppPermissions.returnView))
              IconButton(
                tooltip: context.tr('orderReturnsTitle', fallback: 'Order Returns'),
                icon: const Icon(Icons.assignment_return_outlined),
                onPressed: () {
                  if (!PermissionChecker.hasPermission(
                      AppPermissions.returnView)) {
                    showCustomSnackbar(
                      context: context,
                      message:
                          "You don't have permission to view returns",
                      isWarning: true,
                    );
                    return;
                  }
                  context.pushNamed(AppRoutes.orderReturns);
                },
              ),
          ],
          isHaveSearch: true,
          searchHint: context.tr('search', fallback: 'Search'),
          showFilters: true,
          showStore: true,
          onSearchChanged: (value) {
            _debouncer.run(() {
              context.read<OrdersBloc>().add(SearchOrders(value));
            });
          },
          body: Padding(
            padding: EdgeInsets.only(
              bottom: MediaQuery.of(context).viewPadding.bottom + 70,
            ),
            child: Column(
              children: [
                _buildMethodTabs(context, null),
                Expanded(
                  child: BlocBuilder<OrdersBloc, OrdersState>(
              builder: (context, state) {
                // Show empty state when not loading and no items
                if (!state.isInitialLoading &&
                    !state.isRefreshing &&
                    state.items.isEmpty) {
                  return EmptyStateWidget(
                    svgPath: ImagesPath.noOrderFoundSvg,
                    title:
                        context.tr('noOrdersFound', fallback: "No Orders Found"),
                    subtitle:
                        context.tr('noOrdersMessage', fallback: "You don't have any orders yet."),
                    actionText:
                        context.tr('refresh', fallback: "Refresh"),
                    onAction: () {
                      context.read<OrdersBloc>().add(RefreshOrders());
                    },
                  );
                }

                final items = state.items;
                final hasMore = state.hasMore;
                final total = state.total ?? 0;
                final isPaginating = state.isPaginating;

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                      child:
                          (state.isInitialLoading || state.isRefreshing) &&
                              state.items.isEmpty
                          ? CustomShimmer(
                              width: 150,
                              height: UIUtils.sectionTitle(screenType),
                            )
                          : Text(
                              "${context.tr('orderItems', fallback: "Order Items")} ($total)",
                              style: Theme.of(context).textTheme.bodyLarge
                                  ?.copyWith(fontWeight: FontWeight.w600),
                            ),
                    ),
                    Expanded(
                      child: RefreshIndicator(
                        onRefresh: () async {
                          context.read<OrdersBloc>().add(RefreshOrders());
                        },
                        color: AppColors.primaryColor,
                        child: ListView.builder(
                          physics: const AlwaysScrollableScrollPhysics(),
                          controller: _scrollController,
                          padding: UIUtils.cardsPadding(screenType),
                          itemCount:
                              ((state.isInitialLoading || state.isRefreshing) &&
                                  state.items.isEmpty)
                              ? 10
                              : items.length +
                                    (hasMore ? (isPaginating ? 10 : 1) : 0),
                          itemBuilder: (context, index) {
                            if ((state.isInitialLoading || state.isRefreshing) &&
                                state.items.isEmpty) {
                              return CardShimmer(
                                type: 'order',
                                screenType: screenType,
                              );
                            }

                            if (index >= items.length) {
                              return CardShimmer(
                                type: 'order',
                                screenType: screenType,
                              );
                            }

                            final order = items[index];

                            log('Oder Status ${order.fulfillmentStatus}');
                            return Padding(
                              padding: EdgeInsets.only(
                                bottom: UIUtils.gapMD(screenType),
                              ),
                              child: CustomCard(
                                type: CardType.order,
                                screenType: screenType,
                                data: {
                                  'id': order.order.id,
                                  'title': order.product.title,
                                  'quantity': order.quantity,
                                  'subtotal': order.subtotal.formatted,
                                  'status': order.status,
                                  'image': order.order.image,
                                  'fulfillment_status': order.fulfillmentStatus,
                                  'fulfillment_method': order.fulfillmentMethod,
                                  'created_at': order.createdAt,
                                },
                                onTap: () {
                                  context.pushNamed(
                                    AppRoutes.orderDetails,
                                    pathParameters: {
                                      'id': order.sellerOrderId.toString(),
                                    },
                                  );
                                },
                                onToggleStatus: (status) {
                                  if (status == 'accept') {
                                    // Accept = create the parcel (fulfil the order).
                                    OrderDialogs.showAcceptDialog(
                                      context,
                                      () async {
                                        await context
                                            .read<OrdersRepo>()
                                            .fulfillOrder(
                                              order.sellerOrderId,
                                              {},
                                            );
                                        if (context.mounted) {
                                          context.read<OrdersBloc>().add(
                                            RefreshOrders(),
                                          );
                                        }
                                      },
                                    );
                                  } else if (status == 'reject') {
                                    OrderDialogs.showRejectDialog(
                                      context,
                                      () async {
                                        await context
                                            .read<OrdersRepo>()
                                            .cancelOrderItem(
                                              order.orderItemId,
                                              reason:
                                                  context.tr('rejectedBySeller', fallback: 'Rejected by seller'),
                                            );
                                        if (context.mounted) {
                                          context.read<OrdersBloc>().add(
                                            RefreshOrders(),
                                          );
                                        }
                                      },
                                    );
                                  } else {
                                    // Only `unfulfilled | in_progress | on_hold`
                                    // are manually settable; `fulfilled` is derived.
                                    OrderDialogs.showPreparedDialog(
                                      context,
                                      () async {
                                        await context
                                            .read<OrdersRepo>()
                                            .setFulfillmentStatus(
                                              order.sellerOrderId,
                                              'in_progress',
                                            );
                                        if (context.mounted) {
                                          context.read<OrdersBloc>().add(
                                            RefreshOrders(),
                                          );
                                        }
                                      },
                                    );
                                  }
                                },
                              ),
                            );
                          },
                        ),
                      ),
                    ),
                  ],
                );
              },
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildMethodTabs(BuildContext context, dynamic l10n) {
    // Only the first tab ("All") is localizable — the other labels are the
    // admin-configured shipping mode titles and must render verbatim.
    return Material(
      color: Theme.of(context).scaffoldBackgroundColor,
      child: Padding(
        padding: const EdgeInsetsDirectional.only(start: 15.0),
        child: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          labelColor: AppColors.primaryColor,
          indicatorColor: AppColors.primaryColor,
          unselectedLabelColor: Theme.of(context).textTheme.bodyMedium?.color,
          indicatorSize: TabBarIndicatorSize.tab,
          tabs: [
            for (int i = 0; i < _methodTabs.length; i++)
              Tab(
                text: i == 0
                    ? (context.tr('all'))
                    : _methodTabs[i].label,
              ),
          ],
        ),
      ),
    );
  }
}

/// One entry in the Orders page's shipping-method filter row.
///
/// [value] is the wire value sent to the orders endpoint (`manual`,
/// `shiprocket`, or null for "All"). [label] is what the user reads on the
/// tab — comes from `ShippingSettings.manualTitle` / `shiprocketTitle`.
class _MethodTab {
  final String? value;
  final String label;
  const _MethodTab({required this.value, required this.label});
}
