import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/utils/widgets/animated_list_item.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';
import '../common/sample_section_data.dart';

class BrandStyleTwo extends StatelessWidget {
  final List<SampleTile> brands;

  const BrandStyleTwo({
    super.key,
    this.brands = SampleSectionData.brands,
  });

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: 16.w),
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: brands.length,
        gridDelegate: SliverGridDelegateWithMaxCrossAxisExtent(
          maxCrossAxisExtent: 110.w,
          mainAxisSpacing: 14.h,
          crossAxisSpacing: 12.w,
          childAspectRatio: 0.9,
        ),
        padding: EdgeInsets.zero,
        itemBuilder: (context, i) {
          final tile = brands[i];
          return AnimatedListItem(
            index: i,
            child: GestureDetector(
              onTap: tile.onTap,
              behavior: HitTestBehavior.opaque,
              child: Column(
                children: [
                  Expanded(
                    child: Container(
                      width: double.infinity,
                      clipBehavior: Clip.antiAlias,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(16.r),
                        color: Colors.grey.withValues(alpha: 0.2),
                      ),
                      padding: const EdgeInsets.all(10.0),
                      child: CustomImageContainer(
                        imagePath: tile.imageUrl,
                        fit: BoxFit.contain,
                      ),
                    ),
                  ),
                  SizedBox(height: 6.h),
                  Text(
                    tile.name,
                    maxLines: 1,
                    textAlign: TextAlign.center,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.label.copyWith(
                      fontWeight: FontWeight.w500,
                      color: scheme.onSurface,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
