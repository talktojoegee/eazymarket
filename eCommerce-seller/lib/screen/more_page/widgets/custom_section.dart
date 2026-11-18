import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';

class SettingsSection extends StatelessWidget {
  /// Optional — omit it for a card that needs no header (the Account hub).
  final String? sectionTitle;
  final List<SettingsTileData> tiles;

  const SettingsSection({super.key, this.sectionTitle, required this.tiles});

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final containerColor = isDark ? theme.colorScheme.surfaceContainer : Colors.white;
    final dividerColor = isDark ? AppColors.darkOutline : const Color(0xFFF1F5F9);
    final sectionTitleColor = isDark ? Colors.white : const Color(0xFF1E1E1E);


    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [

        // Section Title
        if (sectionTitle != null && sectionTitle!.isNotEmpty)
          Padding(
            padding: EdgeInsets.fromLTRB(
              25,
              UIUtils.gapXL(screenType),
              UIUtils.gapLG(screenType),
              12,
            ),
            child: Text(
              sectionTitle!,
              style: TextStyle(
                fontSize: UIUtils.tileTitle(screenType),
                fontWeight: FontWeight.w600,
                color: sectionTitleColor,
              ),
            ),
          ),

        // Rounded White Card
        Container(
          margin: EdgeInsets.symmetric(horizontal: UIUtils.gapLG(screenType)),
          decoration: BoxDecoration(
            color: containerColor,
            borderRadius: BorderRadius.circular(UIUtils.radiusLG(screenType)),
            boxShadow: isDark
                ? []
                : [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.03),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
          ),
          clipBehavior: Clip.antiAlias,
          child: ListView.separated(
            padding: EdgeInsets.zero,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: tiles.length,
            separatorBuilder: (context, index) => Divider(
              height: 1,
              thickness: 0.5,
              color: dividerColor,
              indent: 16,
              endIndent: 16,
            ),
            itemBuilder: (context, index) {
              final tile = tiles[index];
              return SettingsTile(
                icon: tile.icon,
                img: tile.img,
                title: tile.title,
                subtitle: tile.subtitle,
                onTap: tile.onTap,
                color: tile.color,
                isMoreMenuItemColor: tile.isMoreMenuItemColor,
              );
            },
          ),
        ),
      ],
    );
  }
}

class SettingsTile extends StatelessWidget {
  final String img;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  final Color? color;
  final IconData? icon;
  final bool isMoreMenuItemColor;

  const SettingsTile({
    super.key,
    required this.img,
    required this.title,
    required this.subtitle,
    this.onTap,
    this.color,
    this.icon,
    this.isMoreMenuItemColor = false,
  });

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final iconColor = color ??
        (isDark
            ? Colors.white70
            : isMoreMenuItemColor
                ? AppColors.primaryColor
                : const Color(0xFF1E1E1E));
    final titleColor = color ?? (isDark ? AppColors.darkFontColor : const Color(0xFF1E1E1E));
    final subtitleColor = isDark ? Colors.white70 : Colors.grey.shade500;
    final chevronColor = isDark ? Colors.grey[400] : const Color(0xFF9CA3AF);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(50),
      child: Padding(
        padding: UIUtils.sectionPadding(screenType),
        child: Row(
          children: [
            // Icon
            SizedBox(
              width: 24,
              height: 24,
              child: Center(
                child: icon != null
                    ? Icon(
                        icon,
                        size: UIUtils.tileIcon(screenType),
                        color: iconColor,
                      )
                    : SvgPicture.asset(
                        img,
                        width: UIUtils.tileIcon(screenType),
                        height: UIUtils.tileIcon(screenType),
                        colorFilter: ColorFilter.mode(iconColor, BlendMode.srcIn),
                      ),
              ),
            ),
            SizedBox(width: UIUtils.gapMD(screenType)),

            // Text Content
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: UIUtils.body(screenType),
                      fontWeight: FontWeight.w500,
                      color: titleColor,
                    ),
                  ),
                  if (subtitle.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: TextStyle(
                        fontSize: UIUtils.tileSubtitle(screenType),
                        fontWeight: FontWeight.w400,
                        color: subtitleColor,
                      ),
                    ),
                  ],
                ],
              ),
            ),

            // Chevron
            Icon(
              Icons.chevron_right,
              color: chevronColor,
              size: UIUtils.chevronIcon(screenType),
            ),
          ],
        ),
      ),
    );
  }
}

class SettingsTileData {
  final String img;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final Color? color;
  final IconData? icon;
  final bool isMoreMenuItemColor;

  const SettingsTileData({
    required this.img,
    required this.title,
    this.subtitle = '',
    required this.onTap,
    this.color,
    this.icon,
    this.isMoreMenuItemColor = false,
  });
}

class SettingsSectionData {
  final String title;
  final List<SettingsTileData> tiles;

  const SettingsSectionData({required this.title, required this.tiles});
}
