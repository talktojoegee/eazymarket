import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';

/// Sticky header for the Add/Edit Product wizard.
///
/// Redesign: horizontal segmented progress bar (one filled amber pill per
/// completed/current step, muted pill for pending) sitting above the step
/// title. Tapping anywhere on the card opens the step-picker bottom sheet.
///
/// Previous design used a circular ring + "N/M" text; the horizontal segments
/// scale better on narrow phones and match the customer-app checkout
/// language.
class AddProductHeader extends StatelessWidget {
  final int currentStep;
  final int totalSteps;
  final String stepTitle;
  final VoidCallback onStepTap;
  final ScreenType screenType;

  const AddProductHeader({
    super.key,
    required this.currentStep,
    required this.totalSteps,
    required this.stepTitle,
    required this.onStepTap,
    required this.screenType,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final outline =
        theme.colorScheme.outlineVariant.withValues(alpha: 0.6);

    return Padding(
      padding: EdgeInsets.fromLTRB(
        UIUtils.gapMD(screenType),
        UIUtils.gapMD(screenType),
        UIUtils.gapMD(screenType),
        UIUtils.gapSM(screenType),
      ),
      child: Material(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onStepTap,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
            decoration: BoxDecoration(
              color: theme.cardColor,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: outline, width: 1),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            stepTitle,
                            style: TextStyle(
                              fontSize: UIUtils.sectionTitle(screenType),
                              fontWeight: FontWeight.w700,
                              color: theme.colorScheme.tertiary,
                              height: 1.2,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 3),
                          Text(
                            "Step $currentStep of $totalSteps",
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: theme.colorScheme.tertiary
                                  .withValues(alpha: 0.55),
                              letterSpacing: 0.02,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    const _JumpChip(),
                  ],
                ),
                const SizedBox(height: 12),
                _SegmentedProgress(
                  currentStep: currentStep,
                  totalSteps: totalSteps,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Small amber pill that hints "tap to open step picker".
class _JumpChip extends StatelessWidget {
  const _JumpChip();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(10, 5, 6, 5),
      decoration: BoxDecoration(
        color: AppColors.primaryColor.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            "Jump",
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: AppColors.primaryColor,
              letterSpacing: 0.02,
            ),
          ),
          const Icon(
            Icons.keyboard_arrow_down_rounded,
            size: 18,
            color: AppColors.primaryColor,
          ),
        ],
      ),
    );
  }
}

/// Horizontal segmented bar: one capsule per step.
/// - `n <  currentStep` → solid amber (done)
/// - `n == currentStep` → solid amber with a soft glow (current)
/// - `n >  currentStep` → outline-variant grey (pending)
class _SegmentedProgress extends StatelessWidget {
  final int currentStep;
  final int totalSteps;

  const _SegmentedProgress({
    required this.currentStep,
    required this.totalSteps,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final pendingColor = theme.colorScheme.outlineVariant
        .withValues(alpha: theme.brightness == Brightness.dark ? 0.5 : 1.0);

    return Row(
      children: List.generate(totalSteps, (i) {
        final n = i + 1;
        final isDone = n < currentStep;
        final isCurrent = n == currentStep;

        Color color;
        List<BoxShadow>? shadow;
        if (isDone) {
          color = AppColors.primaryColor;
        } else if (isCurrent) {
          color = AppColors.primaryColor;
          shadow = [
            BoxShadow(
              color: AppColors.primaryColor.withValues(alpha: 0.35),
              blurRadius: 6,
              spreadRadius: 0.5,
              offset: const Offset(0, 1),
            ),
          ];
        } else {
          color = pendingColor;
        }

        return Expanded(
          child: Container(
            margin: EdgeInsets.only(right: i < totalSteps - 1 ? 6 : 0),
            height: 6,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(3),
              boxShadow: shadow,
            ),
          ),
        );
      }),
    );
  }
}
