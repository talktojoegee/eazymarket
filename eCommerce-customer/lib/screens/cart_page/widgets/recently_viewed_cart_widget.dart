import 'package:collection/collection.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:go_router/go_router.dart';
import 'package:hive/hive.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/model/recent_product_model/recent_product_model.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:hyper_commerce/services/recent_product/recent_product_service.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';

class RecentlyViewedCartWidget extends StatefulWidget {
  const RecentlyViewedCartWidget({super.key});

  @override
  State<RecentlyViewedCartWidget> createState() =>
      _RecentlyViewedCartWidgetState();
}

class _RecentlyViewedCartWidgetState extends State<RecentlyViewedCartWidget> {
  Future<Box<RecentProduct>>? _recentBoxFuture;

  @override
  void initState() {
    super.initState();
    _initBox();
  }

  void _initBox() {
    if (Hive.isBoxOpen(RecentlyViewedService.boxName)) {
      _recentBoxFuture =
          Future.value(Hive.box<RecentProduct>(RecentlyViewedService.boxName));
    } else {
      _recentBoxFuture =
          Hive.openBox<RecentProduct>(RecentlyViewedService.boxName);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Box<RecentProduct>>(
      future: _recentBoxFuture,
      builder: (context, snapshot) {
        if (!snapshot.hasData || snapshot.data!.isEmpty) {
          return const SizedBox.shrink();
        }

        final box = snapshot.data!;
        return ValueListenableBuilder<Box<RecentProduct>>(
          valueListenable: box.listenable(),
          builder: (context, listenableBox, _) {
            if (listenableBox.isEmpty) {
              return const SizedBox.shrink();
            }

            final recentItems = listenableBox.values
                .toList()
                .reversed
                .groupListsBy((item) => item.id)
                .values
                .map((group) => group.first)
                .toList();

            if (recentItems.isEmpty) {
              return const SizedBox.shrink();
            }

            return Container(
              margin: EdgeInsets.only(bottom: 9.h),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.surface,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: EdgeInsets.only(
                      left: 12.0.w,
                      right: 12.0.w,
                      top: 12.h,
                      bottom: 8.h,
                    ),
                    child: Text(
                      context.tr('recentlyViewed'),
                      style: AppTextStyles.titleMedium,
                    ),
                  ),
                  Container(
                    decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(10.r)),
                    height: 108.h,
                    child: ListView.builder(
                      padding: EdgeInsets.symmetric(horizontal: 8.w),
                      scrollDirection: Axis.horizontal,
                      itemCount: recentItems.length,
                      itemBuilder: (context, index) {
                        final item = recentItems[index];
                        return InkWell(
                          borderRadius: BorderRadius.circular(8.r),
                          onTap: () {
                            GoRouter.of(context).push(
                              AppRoutes.productDetailPage,
                              extra: {
                                'productSlug': item.productSlug,
                                'title': item.name,
                                'mainImage': item.imageUrl,
                              },
                            );
                          },
                          child: Container(
                            width: 84.w,
                            margin: EdgeInsets.symmetric(horizontal: 4.w),
                            // decoration: BoxDecoration(
                            //     borderRadius: BorderRadius.circular(10.r)),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  decoration: BoxDecoration(
                                    color: Theme.of(context).brightness ==
                                            Brightness.dark
                                        ? Theme.of(context).colorScheme.surface
                                        : Colors.white,
                                    borderRadius: BorderRadius.circular(10.r),
                                    border: Border.all(
                                      color: Theme.of(context).brightness ==
                                              Brightness.dark
                                          ? Colors.grey.shade800
                                          : Colors.grey.shade200,
                                      width: 1.0,
                                    ),
                                  ),
                                  clipBehavior: Clip.antiAlias,
                                  child: CustomImageContainer(
                                    imagePath: item.imageUrl,
                                    height: 68.h,
                                    width: 84.w,
                                    fit: BoxFit.cover,
                                  ),
                                ),
                                SizedBox(height: 4.h),
                                Text(
                                  item.name,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 9.5.sp,
                                    fontWeight: FontWeight.w800,
                                    height: 1.2,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                  SizedBox(height: 10.h),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
