import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';

/// Thin segmented progress header for the registration flow, matching the
/// redesign. [currentStep] is 1-based; a value greater than [totalSteps]
/// (the review screen) fills every segment.
class SignUpStepHeader extends StatelessWidget {
  final int currentStep;
  final int totalSteps;
  final ScreenType screenType;
  final List<String> labels;

  const SignUpStepHeader({
    super.key,
    required this.currentStep,
    required this.totalSteps,
    required this.screenType,
    required this.labels,
  });

  static const Color _green = Color(0xFF1D9A63);

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isReview = currentStep > totalSteps;
    final caption = isReview
        ? "Review"
        : "Step $currentStep of $totalSteps · ${labels[currentStep - 1]}";

    return Padding(
      padding: EdgeInsets.fromLTRB(
        UIUtils.gapMD(screenType),
        UIUtils.gapSM(screenType),
        UIUtils.gapMD(screenType),
        UIUtils.gapMD(screenType),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: List.generate(totalSteps, (i) {
              final stepNo = i + 1;
              final Color c;
              if (isReview || stepNo < currentStep) {
                c = _green;
              } else if (stepNo == currentStep) {
                c = AppColors.primaryColor;
              } else {
                c = scheme.outline;
              }
              return Expanded(
                child: Container(
                  margin: EdgeInsets.only(right: i < totalSteps - 1 ? 6 : 0),
                  height: 5,
                  decoration: BoxDecoration(
                    color: c,
                    borderRadius: BorderRadius.circular(3),
                  ),
                ),
              );
            }),
          ),
          const SizedBox(height: 10),
          Text(
            caption,
            style: AppTextStyles.label.copyWith(
              color: Colors.grey.shade600,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.4,
            ),
          ),
        ],
      ),
    );
  }
}
