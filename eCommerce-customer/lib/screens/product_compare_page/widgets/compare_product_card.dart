import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/bloc/user_cart_bloc/user_cart_bloc.dart';
import 'package:hyper_commerce/bloc/user_cart_bloc/user_cart_event.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/model/user_cart_model/cart_sync_action.dart';
import 'package:hyper_commerce/model/user_cart_model/user_cart.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_detail_model.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:hyper_commerce/utils/widgets/animated_button.dart';
import 'package:hyper_commerce/utils/widgets/bottom_variant_selector_with_addons.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';

class CompareProductCard extends StatelessWidget {
  final ProductData product;
  final bool isChangeable;
  final VoidCallback? onChange;

  const CompareProductCard({
    super.key,
    required this.product,
    this.isChangeable = false,
    this.onChange,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final isDark = theme.brightness == Brightness.dark;

    final defaultVariant = product.variants.firstWhere(
      (v) => v.isDefault,
      orElse: () => product.variants.isNotEmpty ? product.variants.first : ProductVariants(),
    );

    final currency = defaultVariant.currencySymbol.isNotEmpty
        ? defaultVariant.currencySymbol
        : '₹';
    final price = defaultVariant.specialPrice > 0
        ? defaultVariant.specialPrice
        : defaultVariant.price;
    final origPrice = defaultVariant.price;
    final hasDiscount = origPrice > price;

    final rating = double.tryParse(product.ratings.toString()) ?? 0.0;
    final inStock = defaultVariant.isInStock;

    void handleAddToCart() {
      if (product.variants.length > 1 ||
          product.variants.any((v) => v.addonGroups.isNotEmpty == true)) {
        showVariantBottomSheetWithAddons(
          variantsList: product.variants,
          productData: product,
          productImage: product.mainImage,
          quantityStepSize: product.quantityStepSize,
          context: context,
        );
      } else {
        final item = UserCart(
          productId: product.id.toString(),
          variantId: defaultVariant.id.toString(),
          variantName: defaultVariant.title.toString(),
          vendorId: defaultVariant.storeId.toString(),
          name: product.title,
          image: product.mainImage,
          price: defaultVariant.specialPrice.toDouble(),
          originalPrice: defaultVariant.price.toDouble(),
          quantity: product.minimumOrderQuantity,
          serverCartItemId: null,
          syncAction: CartSyncAction.add,
          updatedAt: DateTime.now(),
          minQty: product.minimumOrderQuantity,
          maxQty: product.totalAllowedQuantity,
          isOutOfStock: !defaultVariant.isInStock,
          isSynced: false,
        );
        context.read<CartBloc>().add(AddToCart(item: item, context: context));
      }
    }

    return Container(
      color: isDark ? AppTheme.darkProductCardColor : Colors.white,
      padding: EdgeInsets.fromLTRB(10.w, 4.h, 10.w, 8.h),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Row: Swap / Remove button aligned right
          Align(
            alignment: Alignment.topRight,
            child: isChangeable && onChange != null
                ? GestureDetector(
                    onTap: onChange,
                    behavior: HitTestBehavior.opaque,
                    child: Container(
                      padding: EdgeInsets.all(3.r),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: isDark
                            ? Colors.white.withValues(alpha: 0.15)
                            : Colors.black.withValues(alpha: 0.1),
                      ),
                      child: Icon(
                        Icons.close,
                        size: 13.sp,
                        color: isDark ? Colors.white70 : Colors.black54,
                      ),
                    ),
                  )
                : SizedBox(height: 19.h),
          ),

          // Product Image: Clean centered
          Center(
            child: SizedBox(
              height: 85.h,
              width: 85.w,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(6.r),
                child: CustomImageContainer(
                  imagePath: product.mainImage,
                  fit: BoxFit.contain,
                ),
              ),
            ),
          ),
          SizedBox(height: 8.h),

          // Product Title
          SizedBox(
            height: 32.h,
            child: Text(
              product.title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppTextStyles.body.copyWith(
                fontSize: 11.5.sp,
                fontWeight: FontWeight.w500,
                color: isDark ? Colors.white : const Color(0xFF212121),
                height: 1.25,
              ),
            ),
          ),
          SizedBox(height: 4.h),

          // Flipkart-Style Green Rating Pill
          if (rating > 0)
            Container(
              padding: EdgeInsets.symmetric(horizontal: 5.w, vertical: 1.5.h),
              decoration: BoxDecoration(
                color: const Color(0xFF388E3C), // Flipkart green
                borderRadius: BorderRadius.circular(3.r),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Text(
                    rating.toStringAsFixed(1),
                    style: TextStyle(
                      fontSize: 10.sp,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
                  SizedBox(width: 2.w),
                  Icon(
                    Icons.star,
                    size: 9.sp,
                    color: Colors.white,
                  ),
                ],
              ),
            )
          else
            SizedBox(height: 16.h),

          SizedBox(height: 5.h),

          // Pricing Row: Bold current price + Strikethrough MRP
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                '$currency${price.toStringAsFixed(0)}',
                style: TextStyle(
                  fontSize: 14.5.sp,
                  fontWeight: FontWeight.w800,
                  color: isDark ? Colors.white : const Color(0xFF212121),
                ),
              ),
              if (hasDiscount) ...[
                SizedBox(width: 5.w),
                Text(
                  '$currency${origPrice.toStringAsFixed(0)}',
                  style: TextStyle(
                    fontSize: 10.5.sp,
                    decoration: TextDecoration.lineThrough,
                    color: isDark
                        ? Colors.white.withValues(alpha: 0.45)
                        : const Color(0xFF878787),
                  ),
                ),
              ],
            ],
          ),
          SizedBox(height: 8.h),

          // Compact Add to Cart Pill Button
          SizedBox(
            width: double.infinity,
            height: 28.h,
            child: AnimatedButton(
              onTap: inStock ? handleAddToCart : () {},
              child: Container(
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: inStock
                      ? AppTheme.primaryColor
                      : scheme.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(4.r),
                ),
                child: Text(
                  inStock
                      ? context.tr('addToCart', fallback: 'Add to Cart')
                      : context.tr('outOfStock', fallback: 'Out of Stock'),
                  style: TextStyle(
                    fontSize: 10.5.sp,
                    fontWeight: FontWeight.w600,
                    color: inStock ? Colors.white : scheme.onSurfaceVariant,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
