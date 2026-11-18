import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_detail_model.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';

class ProductPickerBottomSheet extends StatelessWidget {
  final List<ProductData> products;
  final int currentSelectedId;
  final ValueChanged<ProductData> onProductSelected;

  const ProductPickerBottomSheet({
    super.key,
    required this.products,
    required this.currentSelectedId,
    required this.onProductSelected,
  });

  static Future<ProductData?> show({
    required BuildContext context,
    required List<ProductData> products,
    required int currentSelectedId,
  }) {
    return showModalBottomSheet<ProductData>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => ProductPickerBottomSheet(
        products: products,
        currentSelectedId: currentSelectedId,
        onProductSelected: (selected) {
          Navigator.of(ctx).pop(selected);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.75,
      ),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkProductCardColor : Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20.r)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag Handle
          SizedBox(height: 10.h),
          Center(
            child: Container(
              width: 36.w,
              height: 4.h,
              decoration: BoxDecoration(
                color: scheme.outlineVariant.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(2.r),
              ),
            ),
          ),
          SizedBox(height: 10.h),

          // Header
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 6.h),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    context.tr('chooseProductToCompare', fallback: 'Choose Product to Compare'),
                    style: AppTextStyles.titleMedium.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                GestureDetector(
                  onTap: () => Navigator.of(context).pop(),
                  child: Container(
                    padding: EdgeInsets.all(4.r),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: scheme.surfaceContainerHighest.withValues(alpha: 0.5),
                    ),
                    child: Icon(TablerIcons.x, size: 18.sp, color: scheme.onSurfaceVariant),
                  ),
                ),
              ],
            ),
          ),
          Divider(color: scheme.outlineVariant.withValues(alpha: 0.2), height: 1),

          // Product List
          if (products.isEmpty)
            Padding(
              padding: EdgeInsets.symmetric(vertical: 40.h, horizontal: 20.w),
              child: Text(
                context.tr('noSimilarProducts', fallback: 'No alternative products available for comparison.'),
                textAlign: TextAlign.center,
                style: AppTextStyles.body.copyWith(color: scheme.onSurfaceVariant),
              ),
            )
          else
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
                itemCount: products.length,
                separatorBuilder: (_, __) => Divider(
                  color: scheme.outlineVariant.withValues(alpha: 0.2),
                  height: 1,
                ),
                itemBuilder: (context, index) {
                  final p = products[index];
                  final isSelected = p.id == currentSelectedId;
                  final defaultVariant = p.variants.firstWhere(
                    (v) => v.isDefault,
                    orElse: () => p.variants.isNotEmpty ? p.variants.first : ProductVariants(),
                  );
                  final currency = defaultVariant.currencySymbol.isNotEmpty
                      ? defaultVariant.currencySymbol
                      : '₹';
                  final price = defaultVariant.specialPrice > 0
                      ? defaultVariant.specialPrice
                      : defaultVariant.price;

                  return InkWell(
                    onTap: () => onProductSelected(p),
                    borderRadius: BorderRadius.circular(10.r),
                    child: Padding(
                      padding: EdgeInsets.symmetric(vertical: 8.h, horizontal: 6.w),
                      child: Row(
                        children: [
                          // Thumbnail
                          Container(
                            width: 50.w,
                            height: 50.h,
                            decoration: BoxDecoration(
                              color: scheme.surfaceContainerHighest.withValues(alpha: 0.3),
                              borderRadius: BorderRadius.circular(8.r),
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(8.r),
                              child: CustomImageContainer(
                                imagePath: p.mainImage,
                                fit: BoxFit.contain,
                              ),
                            ),
                          ),
                          SizedBox(width: 12.w),

                          // Title and details
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  p.title,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: AppTextStyles.body.copyWith(
                                    fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                                    fontSize: 12.sp,
                                  ),
                                ),
                                SizedBox(height: 3.h),
                                Row(
                                  children: [
                                    Text(
                                      '$currency${price.toStringAsFixed(0)}',
                                      style: TextStyle(
                                        fontSize: 12.sp,
                                        fontWeight: FontWeight.w700,
                                        color: AppTheme.primaryColor,
                                      ),
                                    ),
                                    if (p.brandName.isNotEmpty) ...[
                                      SizedBox(width: 8.w),
                                      Text(
                                        '• ${p.brandName}',
                                        style: TextStyle(
                                          fontSize: 11.sp,
                                          color: scheme.onSurfaceVariant,
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ],
                            ),
                          ),

                          // Selected Indicator / Radio
                          if (isSelected)
                            Container(
                              padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 4.h),
                              decoration: BoxDecoration(
                                color: scheme.primary.withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(6.r),
                              ),
                              child: Text(
                                context.tr('active', fallback: 'Active'),
                                style: TextStyle(
                                  fontSize: 10.sp,
                                  fontWeight: FontWeight.w700,
                                  color: scheme.primary,
                                ),
                              ),
                            )
                          else
                            Icon(
                              TablerIcons.chevron_right,
                              size: 16.sp,
                              color: scheme.onSurfaceVariant,
                            ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}
