import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/screens/cart_page/bloc/share_cart/share_cart_bloc.dart';
import 'package:hyper_commerce/screens/cart_page/bloc/share_cart/share_cart_event.dart';
import 'package:hyper_commerce/screens/cart_page/bloc/share_cart/share_cart_state.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:hyper_commerce/utils/widgets/custom_toast.dart';
import 'package:share_plus/share_plus.dart';

class ShareCartBannerWidget extends StatefulWidget {
  final int itemsCount;
  final double totalAmount;
  final String currencySymbol;
  final bool isEnabled;

  const ShareCartBannerWidget({
    super.key,
    required this.itemsCount,
    required this.totalAmount,
    this.currencySymbol = '\$',
    this.isEnabled = true,
  });

  @override
  State<ShareCartBannerWidget> createState() => _ShareCartBannerWidgetState();
}

class _ShareCartBannerWidgetState extends State<ShareCartBannerWidget> {
  late final ShareCartBloc _shareCartBloc;

  @override
  void initState() {
    super.initState();
    _shareCartBloc = ShareCartBloc();
  }

  @override
  void dispose() {
    _shareCartBloc.close();
    super.dispose();
  }

  Future<void> _shareCart(String shareUrl) async {
    final String countStr = widget.itemsCount.toString();
    final String formattedTotal = widget.totalAmount.toStringAsFixed(2);
    final String currency = widget.currencySymbol;

    final String message =
        '🛒 Check out this cart! Picked out $countStr items worth $currency$formattedTotal.\n'
        'Tap the link to add them to your cart and place the order together:\n'
        '👉 $shareUrl';

    Rect? sharePositionOrigin;
    try {
      final box = context.findRenderObject() as RenderBox?;
      if (box != null && box.hasSize) {
        final position = box.localToGlobal(Offset.zero);
        final size = box.size;
        final mediaQuery = MediaQuery.maybeOf(context);
        if (mediaQuery != null) {
          final screen = mediaQuery.size;
          final left = position.dx
              .clamp(0.0, (screen.width - 20.0).clamp(0.0, double.infinity));
          final top = position.dy
              .clamp(0.0, (screen.height - 20.0).clamp(0.0, double.infinity));
          final width = size.width
              .clamp(1.0, (screen.width - left).clamp(1.0, double.infinity));
          final height = size.height
              .clamp(1.0, (screen.height - top).clamp(1.0, double.infinity));
          sharePositionOrigin = Rect.fromLTWH(left, top, width, height);
        } else {
          sharePositionOrigin = position & size;
        }
      }
    } catch (_) {}

    if (sharePositionOrigin == null || sharePositionOrigin.isEmpty) {
      final mediaQuery = MediaQuery.maybeOf(context);
      if (mediaQuery != null) {
        final screen = mediaQuery.size;
        sharePositionOrigin = Rect.fromCenter(
          center: Offset(screen.width / 2, screen.height / 2),
          width: 20,
          height: 20,
        );
      }
    }

    try {
      await SharePlus.instance.share(
        ShareParams(
          text: message,
          title: 'Share Cart',
          subject: 'Share Cart',
          sharePositionOrigin: sharePositionOrigin,
        ),
      );
    } catch (e) {
      debugPrint('SharePlus error: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = isDarkMode(context);

    return Container(
      margin: EdgeInsets.symmetric(vertical: 6.h),
      padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 10.h),
      decoration: BoxDecoration(
        color: AppTheme.primaryColor.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10.r),
        border: Border.all(
          color: AppTheme.primaryColor.withValues(alpha: 0.25),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          CircleAvatar(
            radius: 18.r,
            backgroundColor: AppTheme.primaryColor.withValues(alpha: 0.15),
            child: Icon(
              Icons.share_outlined,
              size: 18.sp,
              color: AppTheme.primaryColor,
            ),
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  context.tr('shareYourCart', fallback: 'Share Your Cart'),
                  style: AppTextStyles.bodySmall.copyWith(
                    fontSize: 13.sp,
                    fontWeight: FontWeight.w600,
                    color: isDark ? Colors.white : Colors.black87,
                  ),
                ),
                SizedBox(height: 2.h),
                Text(
                  context.tr(
                    'shareCartSubtitle',
                    fallback: 'Let friends & family review your cart before checkout',
                  ),
                  style: AppTextStyles.caption.copyWith(
                    fontSize: 10.5.sp,
                    color: isDark ? Colors.white70 : Colors.black54,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 10.w),
          BlocConsumer<ShareCartBloc, CartState>(
            bloc: _shareCartBloc,
            listener: (context, state) {
              if (state is ShareCartSuccess) {
                final token = state.model.data?.token ?? state.model.data?.code;
                final serverUrl = state.model.data?.shareUrl?.trim();
                final shareUrl = (serverUrl != null && serverUrl.isNotEmpty)
                    ? serverUrl
                    : (token != null && token.isNotEmpty
                        ? 'https://dev-hypercommerce.vercel.app/cart/shared-lists/$token'
                        : null);

                if (shareUrl != null && shareUrl.isNotEmpty) {
                  _shareCart(shareUrl);
                } else {
                  ToastManager.show(
                    context: context,
                    message: context.tr('unableToGetShareLink',
                        fallback: 'Unable to get share link'),
                    type: ToastType.error,
                  );
                }
              } else if (state is ShareCartFailure) {
                ToastManager.show(
                  context: context,
                  message: state.message,
                  type: ToastType.error,
                );
              }
            },
            builder: (context, state) {
              final bool isLoading = state is ShareCartLoading;
              final bool canTap = widget.isEnabled && !isLoading;

              return AnimatedOpacity(
                opacity: canTap ? 1.0 : 0.5,
                duration: const Duration(milliseconds: 200),
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(20.r),
                    onTap: canTap
                        ? () {
                            _shareCartBloc.add(ShareCart());
                          }
                        : null,
                    child: Container(
                      padding: EdgeInsets.symmetric(
                        horizontal: 14.w,
                        vertical: 6.h,
                      ),
                      decoration: BoxDecoration(
                        color: AppTheme.primaryColor,
                        borderRadius: BorderRadius.circular(20.r),
                      ),
                      child: isLoading
                          ? SizedBox(
                              width: 14.w,
                              height: 14.w,
                              child: const CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor:
                                    AlwaysStoppedAnimation<Color>(Colors.white),
                              ),
                            )
                          : Text(
                              context.tr('share', fallback: 'Share'),
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 11.5.sp,
                                fontWeight: FontWeight.w600,
                                color: Colors.white,
                              ),
                            ),
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}
