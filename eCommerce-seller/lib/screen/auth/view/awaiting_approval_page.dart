import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';

/// Confirmation shown once, right after a successful registration. It only tells
/// the seller their account is under review — no status polling, no API calls.
/// Reachable only from the register flow; "Back to login" ends the session so
/// the seller signs in again once they're notified that they're verified.
class AwaitingApprovalPage extends StatelessWidget {
  const AwaitingApprovalPage({super.key});

  Future<void> _backToLogin(BuildContext context) async {
    await HiveStorage.clearAll();
    if (context.mounted) context.go(AppRoutes.login);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final screenType = context.screenType;

    return PopScope(
      canPop: false,
      child: CustomScaffold(
        showAppbar: false,
        backgroundColor: scheme.surface,
        body: Stack(
          fit: StackFit.expand,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Colors.transparent, AppColors.primaryColor.withValues(alpha: 0.15)],
                ),
              ),
            ),
            SafeArea(
              child: Padding(
                padding: UIUtils.pagePadding(screenType),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    const Spacer(),
                    Container(
                      width: 108,
                      height: 108,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: AppColors.primaryColor.withValues(alpha: 0.14),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(Icons.mark_email_read_outlined, size: 50, color: AppColors.primaryColor),
                    ),
                    const SizedBox(height: 24),
                    Text(
                      "Registration submitted",
                      textAlign: TextAlign.center,
                      style: AppTextStyles.displayLarge.copyWith(
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.5,
                        color: scheme.tertiary,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      "Thanks for signing up! Your account is now under review. "
                      "You'll be notified as soon as your account is verified.",
                      textAlign: TextAlign.center,
                      style: AppTextStyles.body.copyWith(color: Colors.grey.shade600, height: 1.6),
                    ),
                    const Spacer(),
                    SizedBox(
                      width: double.infinity,
                      height: screenType == ScreenType.tablet ? 56 : 52,
                      child: ElevatedButton(
                        onPressed: () => _backToLogin(context),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primaryColor,
                          foregroundColor: Colors.black,
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
                        ),
                        child: Text(
                          "Back to login",
                          style: AppTextStyles.titleMedium.copyWith(fontWeight: FontWeight.w700, color: Colors.black),
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
