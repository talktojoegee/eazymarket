import 'package:flutter/material.dart' hide Badge;
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:hyper_commerce/utils/widgets/animated_button.dart';
import '../../../config/helper.dart';
import '../../../utils/widgets/basket_button.dart';

class AppBarWidget extends StatelessWidget {
  final bool showTitle;

  const AppBarWidget({
    super.key,
    required this.showTitle,
  });

  @override
  Widget build(BuildContext context) {
    final searchBar = GestureDetector(
      onTap: () {
        GoRouter.of(context).push(AppRoutes.search);
      },
      child: Container(
        height: 30.h,
        padding: EdgeInsets.symmetric(horizontal: 12.w),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: BorderRadius.circular(12.r),
          border: Border.all(color: Colors.grey.shade400),
        ),
        child: Row(
          children: [
            Icon(
              TablerIcons.zoom,
              size: 20.sp,
              color:
                  Theme.of(context).colorScheme.tertiary.withValues(alpha: 0.6),
            ),
            SizedBox(width: 8.w),
            Expanded(
              child: Text(
                context.tr('searchForProducts'),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTextStyles.body.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .tertiary
                      .withValues(alpha: 0.6),
                ),
              ),
            ),
          ],
        ),
      ),
    );

    return SliverAppBar(
      titleSpacing: 0,
      title: isTablet(context)
          ? Align(
              alignment: AlignmentDirectional.centerStart,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 520),
                child: searchBar,
              ),
            )
          : searchBar,
      pinned: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      elevation: showTitle ? 1 : 0,
      shadowColor: Colors.grey.withValues(alpha: 0.3),
      actions: [
        const SizedBox(
          width: 10,
        ),
        AnimatedButton(
          onTap: (){
            GoRouter.of(context).push(AppRoutes.wishlistPage);
          },
          child: Icon(
            AppHelpers.notWishListedIcon,
          ),
        ),
        const SizedBox(
          width: 10,
        ),
        const BasketButton(),
        const SizedBox(
          width: 15,
        ),
      ],
    );
  }
}
