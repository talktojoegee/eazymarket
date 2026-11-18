import 'package:flutter/material.dart';
import '../../../../config/helper.dart';

class HomeAppBarSection extends StatelessWidget {
  final bool canUseTabController;
  final double appBarOpacity;
  final Color? textColor;
  final Widget title;
  final Widget flexibleSpaceBackground;
  final Widget searchField;
  final Widget? tabBar;
  final Widget? notificationsAction;
  final bool isDarkMode;
  final Color? darkBackgroundColor;

  const HomeAppBarSection({
    super.key,
    required this.canUseTabController,
    required this.appBarOpacity,
    required this.textColor,
    required this.title,
    required this.flexibleSpaceBackground,
    required this.searchField,
    required this.isDarkMode,
    this.darkBackgroundColor,
    this.tabBar,
    this.notificationsAction,
  });

  @override
  Widget build(BuildContext context) {
    if (isTablet(context)) {
      return _buildTabletAppBar(context);
    }
    return _buildMobileAppBar(context);
  }

  Widget _buildTabletAppBar(BuildContext context) {
    const double tabletToolbarHeight = 64.0;
    const double tabHeight = 50.0;
    final double expandedHeight =
        canUseTabController ? (tabletToolbarHeight + tabHeight) : tabletToolbarHeight;

    return SliverAppBar(
      toolbarHeight: tabletToolbarHeight,
      expandedHeight: expandedHeight,
      floating: false,
      pinned: true,
      elevation: 0,
      shadowColor: Colors.black.withValues(alpha: 0.3),
      surfaceTintColor: Colors.transparent,
      automaticallyImplyLeading: false,
      titleSpacing: 16.0,
      title: Row(
        children: [
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 260),
            child: title,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 520),
                child: searchField,
              ),
            ),
          ),
          const SizedBox(width: 8),
        ],
      ),
      centerTitle: false,
      flexibleSpace: Container(
        decoration: BoxDecoration(
          color: isDarkMode ? darkBackgroundColor : Colors.white,
        ),
        child: FlexibleSpaceBar(
          background: flexibleSpaceBackground,
        ),
      ),
      actions: [
        if (notificationsAction != null)
          Center(child: notificationsAction!)
        else
          const SizedBox(width: 40),
        const SizedBox(width: 12),
      ],
      bottom: canUseTabController
          ? PreferredSize(
              preferredSize: const Size.fromHeight(tabHeight),
              child: tabBar ?? const SizedBox(height: tabHeight),
            )
          : null,
    );
  }

  Widget _buildMobileAppBar(BuildContext context) {
    return SliverAppBar(
      expandedHeight: canUseTabController ? 160.0 : 120.0,
      floating: false,
      pinned: true,
      elevation: 0,
      shadowColor: Colors.black.withValues(alpha: 0.3),
      surfaceTintColor: Colors.transparent,
      automaticallyImplyLeading: false,
      title: title,
      centerTitle: false,
      flexibleSpace: Container(
        decoration: BoxDecoration(
          color: isDarkMode ? darkBackgroundColor : Colors.white,
        ),
        child: FlexibleSpaceBar(
          background: flexibleSpaceBackground,
        ),
      ),
      actions: [
        if (notificationsAction != null)
          Center(child: notificationsAction!)
        else
          const SizedBox(width: 48),
        const SizedBox(width: 10),
      ],
      bottom: canUseTabController
          ? PreferredSize(
              preferredSize: const Size.fromHeight(62),
              child: Column(
                children: [
                  searchField,
                  const SizedBox(height: 8),
                  tabBar ?? const SizedBox(height: 50),
                ],
              ),
            )
          : PreferredSize(
              preferredSize: const Size.fromHeight(30),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: searchField,
              ),
            ),
    );
  }
}
