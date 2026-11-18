import 'package:flutter/material.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';

class CustomScaffoldDefaultAppBar extends StatelessWidget
    implements PreferredSizeWidget {
  final String? title;
  final List<Widget>? actions;
  final Color? appBarBgColor;
  final Color? appBarBgTitleColor;

  const CustomScaffoldDefaultAppBar({
    super.key,
    this.title,
    this.actions,
    this.appBarBgColor,
    this.appBarBgTitleColor,
  });

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      title: title != null ? Text(title!) : null,
      titleTextStyle: AppTextStyles.titleMedium.copyWith(
        color: appBarBgTitleColor ?? Theme.of(context).colorScheme.tertiary,
      ),
      actions: actions,
      backgroundColor: appBarBgColor ?? Theme.of(context).colorScheme.surface,
      shadowColor:
          Theme.of(context).colorScheme.surfaceContainer.withValues(alpha: 0.2),
    );
  }
}
