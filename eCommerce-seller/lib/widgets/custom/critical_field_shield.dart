import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';

/// Small amber outline shield icon indicating that editing the associated product
/// field requires administrator review/approval.
///
/// If [fieldKey] is not present in [HiveStorage.productApprovalCriticalFields],
/// this widget renders [SizedBox.shrink].
/// When tapped, it displays an informative modal dialog explaining the approval requirement.
class CriticalFieldShield extends StatelessWidget {
  final String fieldKey;
  final double size;
  final EdgeInsetsGeometry padding;

  const CriticalFieldShield({
    super.key,
    required this.fieldKey,
    this.size = 15,
    this.padding = const EdgeInsets.symmetric(horizontal: 3, vertical: 2),
  });

  /// Displays the admin approval notice dialog.
  static void showInfoDialog(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final screenType = context.screenTypeRead;

    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: isDark
              ? AppColors.mainDarkContainerBgColor
              : AppColors.mainLightContainerBgColor,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(UIUtils.radiusXL(screenType)),
          ),
          titlePadding: EdgeInsets.fromLTRB(
            UIUtils.gapLG(screenType),
            UIUtils.gapXL(screenType),
            UIUtils.gapLG(screenType),
            0,
          ),
          contentPadding: EdgeInsets.fromLTRB(
            UIUtils.gapLG(screenType),
            12,
            UIUtils.gapLG(screenType),
            16,
          ),
          actionsPadding: EdgeInsets.fromLTRB(
            UIUtils.gapXL(screenType),
            8,
            UIUtils.gapXL(screenType),
            20,
          ),
          title: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                decoration: BoxDecoration(
                  color: const Color(0xFFE08A00).withValues(alpha: 0.12),
                  shape: BoxShape.circle,
                ),
                padding: const EdgeInsets.all(14),
                margin: const EdgeInsets.only(bottom: 12),
                child: const Icon(
                  Icons.shield_outlined,
                  size: 36,
                  color: Color(0xFFE08A00),
                ),
              ),
              Text(
                context.tr(
                  'adminApprovalRequired',
                  fallback: 'Admin Approval Required',
                ),
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: UIUtils.sectionTitle(screenType),
                  fontWeight: UIUtils.bold,
                  color: isDark ? Colors.white : AppColors.lightTertiary,
                ),
              ),
            ],
          ),
          content: Text(
            context.tr(
              'adminApprovalFieldNotice',
              fallback:
                  'Changes to this field will require administrator review and approval before becoming visible to customers.',
            ),
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: UIUtils.body(screenType),
              height: 1.45,
              color: isDark ? Colors.white70 : Colors.grey.shade800,
            ),
          ),
          actions: [
            SizedBox(
              width: double.infinity,
              child: PrimaryButton(
                text: context.tr('gotIt', fallback: 'Got it'),
                backgroundColor: AppColors.primaryColor,
                borderRadius: UIUtils.radiusMD(screenType),
                onPressed: () => Navigator.pop(ctx),
              ),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!HiveStorage.isCriticalField(fieldKey)) {
      return const SizedBox.shrink();
    }

    return Tooltip(
      message: context.tr(
        'adminApprovalRequired',
        fallback: 'Admin Approval Required',
      ),
      child: MouseRegion(
        cursor: SystemMouseCursors.click,
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () => showInfoDialog(context),
          child: Padding(
            padding: padding,
            child: Icon(
              Icons.shield_outlined,
              size: size,
              color: const Color(0xFFE08A00),
            ),
          ),
        ),
      ),
    );
  }
}
