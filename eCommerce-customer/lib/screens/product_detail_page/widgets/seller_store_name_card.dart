import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import '../../../router/app_routes.dart';

class SellerStoreNameCard extends StatelessWidget {
  final String storeName;
  final String storeSlug;
  final String sellerName;

  const SellerStoreNameCard({
    super.key,
    required this.storeName,
    required this.storeSlug,
    required this.sellerName,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        GoRouter.of(context).push(
          AppRoutes.nearbyStoreDetails,
          extra: {
            'store-slug': storeSlug,
            'store-name': storeName,
          },
        );
      },
      child: Card(
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(0.r),
        ),
        margin: EdgeInsets.symmetric(horizontal: 0.w, vertical: 0.h),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${context.tr('soldBy')} ',
                style: AppTextStyles.body.copyWith(
                  color: Colors.grey,
                ),
              ),
              Expanded(
                child: Text(
                  storeName,
                  style: AppTextStyles.body.copyWith(
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
              Directionality.of(context) == TextDirection.ltr
                  ? const Icon(TablerIcons.chevron_right, color: Colors.grey)
                  : const Icon(TablerIcons.chevron_left, color: Colors.grey),
            ],
          ),
        ),
      ),
    );
  }
}
