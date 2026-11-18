import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/config/colors.dart';

/// Step-picker bottom sheet for the Add/Edit Product wizard.
///
/// Redesign: drag handle (no dark circular close button), sheet header,
/// step cards with three distinct states —
///   done    → check icon on tinted circle, muted "Completed" caption
///   current → amber background card with filled amber number circle
///   pending → hollow number circle, muted caption
///
/// The parent decides which step is tappable via the callback; this widget
/// doesn't gate navigation itself.
class AddProductProcessBottomSheet extends StatelessWidget {
  final int currentStep;
  final int totalSteps;
  final Function(int) onStepSelected;

  const AddProductProcessBottomSheet({
    super.key,
    required this.currentStep,
    required this.totalSteps,
    required this.onStepSelected,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return DraggableScrollableSheet(
      initialChildSize: 0.72,
      minChildSize: 0.45,
      maxChildSize: 0.92,
      builder: (context, scrollController) {
        return Container(
          decoration: BoxDecoration(
            color: theme.scaffoldBackgroundColor,
            borderRadius:
                const BorderRadius.vertical(top: Radius.circular(22)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Handle
              Padding(
                padding: const EdgeInsets.only(top: 10, bottom: 4),
                child: Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: theme.colorScheme.outlineVariant,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                ),
              ),

              // Header
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 4),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            context.tr('addProductsProcess', fallback: 'Add product · steps'),
                            style: TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w700,
                              color: theme.colorScheme.tertiary,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            'Tap a completed step to review, '
                            'or jump ahead once required fields are done.',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w500,
                              color: theme.colorScheme.tertiary
                                  .withValues(alpha: 0.55),
                              height: 1.35,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const _CloseButton(),
                  ],
                ),
              ),
              const SizedBox(height: 12),

              // Step list
              Expanded(
                child: ListView.separated(
                  controller: scrollController,
                  padding:
                      const EdgeInsets.fromLTRB(20, 0, 20, 24),
                  itemCount: totalSteps,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final step = index + 1;
                    final isCompleted = step < currentStep;
                    final isCurrent = step == currentStep;

                    return _StepCard(
                      stepNumber: step,
                      title: _getStepTitle(context, step),
                      status: isCompleted
                          ? _StepStatus.done
                          : isCurrent
                              ? _StepStatus.current
                              : _StepStatus.pending,
                      statusLabel: isCompleted
                          ? (context.tr('stepCompleted', fallback: 'Completed'))
                          : isCurrent
                              ? (context.tr('stepCurrent', fallback: 'Current'))
                              : (context.tr('stepPending', fallback: 'Pending')),
                      onTap: () => onStepSelected(step),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  String _getStepTitle(BuildContext context, int step) {
    switch (step) {
      case 1:
        return context.tr('selectCategory');
      case 2:
        return context.tr('productInformation');
      case 3:
        return context.tr('policiesAndFeatures');
      case 4:
        return context.tr('productVariation');
      case 5:
        return context.tr('productImages');
      case 6:
        return context.tr('productsDescription');
      case 7:
        return context.tr('pricingAndTaxes');
      default:
        return "";
    }
  }
}

enum _StepStatus { done, current, pending }

class _StepCard extends StatelessWidget {
  final int stepNumber;
  final String title;
  final _StepStatus status;
  final String statusLabel;
  final VoidCallback onTap;

  const _StepCard({
    required this.stepNumber,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    Color cardBg;
    Color borderColor;
    Color numberBg;
    Color numberFg;
    Border? numberBorder;
    Color captionColor;
    Widget numberChild;

    switch (status) {
      case _StepStatus.done:
        cardBg = theme.cardColor;
        borderColor = theme.colorScheme.outlineVariant.withValues(alpha: 0.6);
        numberBg = AppColors.stepCompletedColor.withValues(alpha: 0.10);
        numberFg = AppColors.stepCompletedColor;
        numberBorder = Border.all(
          color: AppColors.stepCompletedColor,
          width: 1.6,
        );
        captionColor = AppColors.stepCompletedColor;
        numberChild = Icon(
          Icons.check_rounded,
          size: 20,
          color: AppColors.stepCompletedColor,
        );
        break;
      case _StepStatus.current:
        cardBg = AppColors.primaryColor.withValues(alpha: 0.10);
        borderColor = AppColors.primaryColor;
        numberBg = AppColors.primaryColor;
        numberFg = Colors.black;
        captionColor = AppColors.primaryColor;
        numberChild = Text(
          "$stepNumber",
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w800,
            color: numberFg,
          ),
        );
        break;
      case _StepStatus.pending:
        cardBg = theme.cardColor;
        borderColor = theme.colorScheme.outlineVariant.withValues(alpha: 0.6);
        numberBg = theme.colorScheme.outlineVariant.withValues(alpha: 0.4);
        numberFg =
            theme.colorScheme.tertiary.withValues(alpha: 0.55);
        captionColor =
            theme.colorScheme.tertiary.withValues(alpha: 0.55);
        numberChild = Text(
          "$stepNumber",
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w700,
            color: numberFg,
          ),
        );
        break;
    }

    return Material(
      color: cardBg,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: borderColor, width: 1),
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: numberBg,
                  shape: BoxShape.circle,
                  border: numberBorder,
                ),
                alignment: Alignment.center,
                child: numberChild,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                        color: theme.colorScheme.tertiary,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      statusLabel.toUpperCase(),
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                        color: captionColor,
                        letterSpacing: 0.06,
                      ),
                    ),
                  ],
                ),
              ),
              if (status != _StepStatus.pending)
                Icon(
                  Icons.arrow_forward_ios_rounded,
                  size: 14,
                  color: theme.colorScheme.tertiary.withValues(alpha: 0.35),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CloseButton extends StatelessWidget {
  const _CloseButton();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: theme.colorScheme.outlineVariant.withValues(alpha: 0.35),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: () => Navigator.pop(context),
        child: SizedBox(
          width: 34,
          height: 34,
          child: Icon(
            Icons.close_rounded,
            size: 18,
            color: theme.colorScheme.tertiary.withValues(alpha: 0.75),
          ),
        ),
      ),
    );
  }
}
