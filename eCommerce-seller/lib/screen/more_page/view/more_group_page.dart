import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/screen/more_page/model/more_menu.dart';
import 'package:hyper_commerce_seller/screen/more_page/widgets/custom_section.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';

/// Renders any one group from the Account hub. There is a single page for all
/// of them — the group is resolved from [groupId] against [buildMoreGroups].
class MoreGroupPage extends StatelessWidget {
  final String groupId;

  const MoreGroupPage({super.key, required this.groupId});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final group = findMoreGroup(context, groupId);

    if (group == null) {
      return CustomScaffold(
        showAppbar: true,
        title: context.tr('moreSettings', fallback: 'Settings'),
        body: Center(
          child: Text(
            context.tr('somethingWentWrong', fallback: 'Something went wrong'),
            style: TextStyle(color: theme.colorScheme.tertiary),
          ),
        ),
      );
    }

    return CustomScaffold(
      showAppbar: true,
      title: group.title,
      backgroundColor:
          isDark ? theme.colorScheme.surface : const Color(0xFFF5F5F7),
      body: SingleChildScrollView(
        child: Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewPadding.bottom + 32,
          ),
          child: SettingsSection(
            sectionTitle: group.subtitle,
            tiles: group.tiles,
          ),
        ),
      ),
    );
  }
}
