import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/permission_groups.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/bloc/store_switcher/store_switcher_cubit.dart';
import 'package:hyper_commerce_seller/screen/order_page/bloc/orders/orders_bloc.dart';

class Dashboard extends StatefulWidget {
  final int index;
  final StatefulNavigationShell navigationShell;

  const Dashboard({
    super.key,
    required this.index,
    required this.navigationShell,
  });

  @override
  State<Dashboard> createState() => _DashboardState();
}

class _DashboardState extends State<Dashboard> with TickerProviderStateMixin {
  late AnimationController _scaleController;

  /// Timestamp of the last back-press while already on the Home tab.
  DateTime? _lastBackPressedAt;

  /// How long the "Press back again to exit" window stays open.
  static const Duration _exitTimeout = Duration(seconds: 2);

  @override
  void initState() {
    super.initState();
    _scaleController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 200),
    );

    WidgetsBinding.instance.addPostFrameCallback((_) => _redirectIfBlocked());
  }

  @override
  void dispose() {
    _scaleController.dispose();
    super.dispose();
  }

  List<_NavTab> _allTabs() => [
        _NavTab(
          branchIndex: 0,
          permissions: const [], // Home / Dashboard — always accessible.
          label: context.tr('home', fallback: 'Home'),
          activeSvg: ImagesPath.homeActive,
          inactiveSvg: ImagesPath.homeInactive,
        ),
        _NavTab(
          branchIndex: 1,
          permissions: PermissionGroups.order,
          label: context.tr('orders', fallback: 'Orders'),
          activeSvg: ImagesPath.orderActive,
          inactiveSvg: ImagesPath.orderInactive,
        ),
        _NavTab(
          branchIndex: 2,
          permissions: PermissionGroups.product,
          label: context.tr('products', fallback: 'Products'),
          activeSvg: ImagesPath.productsActive,
          inactiveSvg: ImagesPath.productsInactive,
        ),
        _NavTab(
          branchIndex: 3,
          permissions: const [], 
          label: context.tr('account', fallback: 'Account'),
          activeSvg: ImagesPath.moreActive,
          inactiveSvg: ImagesPath.moreInactive,
        ),
      ];

  bool _tabAccessible(_NavTab t) =>
      t.permissions.isEmpty || PermissionChecker.hasAny(t.permissions);

  List<_NavTab> _visibleTabs() {
    return _allTabs().where(_tabAccessible).toList();
  }

  void _redirectIfBlocked() {
    if (!mounted) return;
    final current = widget.navigationShell.currentIndex;
    final all = _allTabs();
    if (current >= all.length) return;
    final currentTab = all[current];
    if (!_tabAccessible(currentTab)) {
      final firstAllowed = _visibleTabs();
      if (firstAllowed.isNotEmpty) {
        widget.navigationShell.goBranch(firstAllowed.first.branchIndex);
      }
    }
  }

  void _onTap(int branchIndex) {
    _scaleController.forward().then((_) => _scaleController.reverse());
    widget.navigationShell.goBranch(
      branchIndex,
      initialLocation: branchIndex == widget.navigationShell.currentIndex,
    );
  }

  Future<bool> _onPopInvoked() async {
    final currentIndex = widget.navigationShell.currentIndex;
    if (currentIndex != 0) {
      widget.navigationShell.goBranch(0);
      _lastBackPressedAt = null;
      return false;
    }

    final now = DateTime.now();

    if (_lastBackPressedAt != null &&
        now.difference(_lastBackPressedAt!) < _exitTimeout) {
      return true;
    }

    _lastBackPressedAt = now;

    if (mounted) {
      ScaffoldMessenger.of(context).hideCurrentSnackBar();
      showCustomSnackbar(
        context: context,
        message: context.tr('pressAgainToExit', fallback: 'Press again to exit'),
      );
    }

    return false;
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final theme = Theme.of(context);
    final activeColor = theme.colorScheme.tertiary;
    final visibleTabs = _visibleTabs();

    return PopScope<Object?>(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, Object? result) async {
        if (didPop) return;

        final shouldExit = await _onPopInvoked();
        if (shouldExit && mounted) {
          // Close the app cleanly
          SystemNavigator.pop();
        }
      },
      child: BlocListener<StoreSwitcherCubit, StoreSwitcherState>(
        listenWhen: (previous, current) =>
        previous.selectedStore?.id != current.selectedStore?.id,
        listener: (context, state) {
          if (state.selectedStore != null) {
            context.read<OrdersBloc>().add(
              RefreshOrders(storeId: state.selectedStore?.id),
            );
          }
        },
        child: Scaffold(
          extendBody: true,
          backgroundColor: theme.colorScheme.surface,
          body: widget.navigationShell,
          bottomNavigationBar: Padding(
            padding: const EdgeInsets.all(12),
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 10),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(25),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.15),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              // Only render nav items for tabs the user has access to.
              // Branch indices are preserved so navigationShell.goBranch
              // and widget.navigationShell.currentIndex still line up.
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  for (final tab in visibleTabs)
                    _navItem(
                      tab: tab,
                      activeColor: activeColor,
                      screenType: screenType,
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _navItem({
    required _NavTab tab,
    required Color activeColor,
    required ScreenType screenType,
  }) {
    final bool isSelected = widget.index == tab.branchIndex;

    return GestureDetector(
      onTap: () => _onTap(tab.branchIndex),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8.0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 250),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: isSelected ? activeColor : Colors.transparent,
                borderRadius: BorderRadius.circular(15),
              ),
              child: AnimatedScale(
                scale: isSelected ? 1.0 : 1.0,
                duration: const Duration(milliseconds: 200),
                child: SvgPicture.asset(
                  isSelected ? tab.activeSvg : tab.inactiveSvg,
                  width: isSelected
                      ? UIUtils.bottomNavIconActive(screenType)
                      : UIUtils.bottomNavIconInactive(screenType),
                  height: isSelected
                      ? UIUtils.bottomNavIconActive(screenType)
                      : UIUtils.bottomNavIconInactive(screenType),
                  colorFilter: ColorFilter.mode(
                    isSelected ? Colors.white : Colors.grey,
                    BlendMode.srcIn,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              tab.label,
              style: TextStyle(
                fontSize: UIUtils.bottomNavFontSize(screenType),
                fontWeight:
                    isSelected ? FontWeight.w600 : FontWeight.w400,
                color: isSelected ? Colors.black : Colors.grey,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// One row in the bottom nav bar. `branchIndex` is the position in the
/// StatefulShellRoute branches list (in app_routes.dart) and stays fixed
/// regardless of which tabs the current user can see. `permissions` is the
/// group of related permissions; the tab is shown when the user has ANY
/// of them (empty list = always shown).
class _NavTab {
  final int branchIndex;
  final List<String> permissions;
  final String label;
  final String activeSvg;
  final String inactiveSvg;

  const _NavTab({
    required this.branchIndex,
    required this.permissions,
    required this.label,
    required this.activeSvg,
    required this.inactiveSvg,
  });
}
