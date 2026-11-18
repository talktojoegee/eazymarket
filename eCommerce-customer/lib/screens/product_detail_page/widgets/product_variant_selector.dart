import 'dart:developer';
import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_detail_model.dart';
import 'package:hyper_commerce/utils/widgets/price_utils.dart';
import '../../../utils/widgets/dominant_colors.dart';

class ProductVariantSelector extends StatelessWidget {
  final String label;
  final String attributeSlug;
  final String variantType;
  final SwatchValues? selectedValue;
  final ValueChanged<SwatchValues> onSelected;
  final List<SwatchValues> productAttributes;
  final List<ProductVariants> variants;

  const ProductVariantSelector({
    super.key,
    required this.label,
    required this.attributeSlug,
    required this.variantType,
    required this.selectedValue,
    required this.onSelected,
    required this.productAttributes,
    required this.variants,
  });

  ProductVariants? _findVariantForSwatch(SwatchValues swatch) {
    try {
      return variants.firstWhere((v) {
        final attrValue = v.attributes[attributeSlug];
        return attrValue?.toString().toLowerCase().trim() ==
            swatch.value.toString().toLowerCase().trim();
      });
    } catch (_) {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isColor = variantType == 'color';
    final isImage = variantType == 'image';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTextStyles.body.copyWith(
            fontWeight: FontWeight.w600,
            color: Theme.of(context).colorScheme.tertiary.withValues(alpha: 0.8),
          ),
        ),
        SizedBox(height: 10.h),
        if (isColor) _buildColorSwatches(context) else if (isImage) _buildImageSwatches(context) else _buildTextVariantCards(context),
        SizedBox(height: 5.h),
      ],
    );
  }

  Widget _buildColorSwatches(BuildContext context) {
    return SizedBox(
      height: 35.h,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        itemCount: productAttributes.length,
        itemBuilder: (BuildContext context, int index) {
          final currentValue = productAttributes[index];
          final isSelected = selectedValue == currentValue;
          final color = getColorFromHex(currentValue.swatch);
          final matchedVariant = _findVariantForSwatch(currentValue);
          final isOutOfStock = matchedVariant != null && !matchedVariant.isInStock;

          log('SwatchValue $matchedVariant');
          return GestureDetector(
            onTap: isOutOfStock ? null : () => onSelected(currentValue),
            child: Padding(
              padding: EdgeInsets.only(right: 12.w),
              child: Opacity(
                opacity: isOutOfStock ? 0.4 : 1.0,
                child: Container(
                  width: 35.w,
                  height: 35.h,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: color,
                    border: Border.all(
                      color: isSelected
                          ? AppTheme.primaryColor
                          : Theme.of(context).colorScheme.outline,
                      width: isSelected ? 2 : 1,
                    ),
                  ),
                  child: isSelected
                      ? Center(
                          child: Icon(
                            Icons.check,
                            size: 16.sp,
                            color: (color?.computeLuminance() ?? 1.0) < 0.5
                                ? Colors.white
                                : Colors.black87,
                          ),
                        )
                      : null,
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildImageSwatches(BuildContext context) { final theme = Theme.of(context); return SizedBox( height: 48.h, child: ListView.builder( scrollDirection: Axis.horizontal, itemCount: productAttributes.length, itemBuilder: (BuildContext context, int index) { final currentValue = productAttributes[index]; final isSelected = selectedValue == currentValue; final matchedVariant = _findVariantForSwatch(currentValue); final isOutOfStock = matchedVariant != null && !matchedVariant.isInStock; log('Image SwatchValue $matchedVariant'); return GestureDetector( onTap: isOutOfStock ? null : () => onSelected(currentValue), child: Padding( padding: EdgeInsets.only(right: 10.w), child: Opacity( opacity: isOutOfStock ? 0.45 : 1.0, child: AnimatedContainer( duration: const Duration(milliseconds: 200), width: 48.w, height: 48.h, padding: EdgeInsets.all(2.w), decoration: BoxDecoration( borderRadius: BorderRadius.circular(8.r), border: Border.all( color: isSelected ? AppTheme.primaryColor : theme.colorScheme.outline .withValues(alpha: 0.6), width: isSelected ? 2 : 1, ), ), child: ClipRRect( borderRadius: BorderRadius.circular(6.r), child: Stack( fit: StackFit.expand, children: [ Image.network( currentValue.swatch, fit: BoxFit.cover, errorBuilder: ( context, error, stackTrace, ) { return Container( color: theme .colorScheme .surfaceContainerHighest, alignment: Alignment.center, child: Icon( Icons.image_not_supported_outlined, size: 18.sp, color: theme.colorScheme.onSurfaceVariant, ), ); }, loadingBuilder: ( context, child, loadingProgress, ) { if (loadingProgress == null) { return child; } return Container( color: theme .colorScheme .surfaceContainerHighest, alignment: Alignment.center, child: SizedBox( width: 16.w, height: 16.h, child: const CircularProgressIndicator( strokeWidth: 1.5, ), ), ); }, ), if (isSelected) Container( color: Colors.black.withValues(alpha: 0.25), alignment: Alignment.center, child: Container( width: 20.w, height: 20.h, decoration: const BoxDecoration( shape: BoxShape.circle, color: Colors.white, ), child: Icon( Icons.check, size: 14.sp, color: AppTheme.primaryColor, ), ), ), if (isOutOfStock) Container( color: Colors.black.withValues(alpha: 0.25), alignment: Alignment.center, child: Icon( Icons.close, size: 18.sp, color: Colors.white, ), ), ], ), ), ), ), ), ); }, ), ); }

  Widget _buildTextVariantCards(BuildContext context) {
    final theme = Theme.of(context);

    return Wrap(
      spacing: 10.w,
      runSpacing: 10.h,
      children: productAttributes.map((currentValue) {
        final isSelected = selectedValue == currentValue;
        final matchedVariant = _findVariantForSwatch(currentValue);
        final isOutOfStock =
            matchedVariant != null && !matchedVariant.isInStock;

        final double? price = matchedVariant?.price.toDouble();
        final double? salePrice = matchedVariant?.specialPrice.toDouble();
        // Variant-level currency follows the per-row payload from PricePresenter.
        final String variantSymbol = matchedVariant?.currencySymbol ?? '';
        final variantFormat = matchedVariant?.format;
        String fmt(double v) => variantFormat != null
            ? PriceUtils.formatWithFormat(v, variantFormat, variantSymbol)
            : PriceUtils.formatWithSymbol(v, variantSymbol);
        final String displayPrice = salePrice != null && salePrice > 0
            ? fmt(salePrice)
            : (price != null ? fmt(price) : '');


        return GestureDetector(
          onTap: isOutOfStock ? null : () => onSelected(currentValue),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeInOut,
            constraints: BoxConstraints(minWidth: 80.w),
            padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 10.h),
            decoration: BoxDecoration(
              color: isOutOfStock
                  ? theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5)
                  : isSelected
                      ? AppTheme.primaryColor.withValues(alpha: 0.08)
                      : theme.colorScheme.surface,
              borderRadius: BorderRadius.circular(10.r),
              border: Border.all(
                color: isOutOfStock
                    ? theme.colorScheme.outline.withValues(alpha: 0.3)
                    : isSelected
                        ? AppTheme.primaryColor
                        : theme.colorScheme.outline.withValues(alpha: 0.6),
                width: 1,
              ),
            ),
            child: Opacity(
              opacity: isOutOfStock ? 0.5 : 1.0,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    currentValue.value,
                    style: AppTextStyles.bodySmall.copyWith(
                      color: isSelected
                          ? AppTheme.primaryColor
                          : theme.colorScheme.tertiary,
                    ),
                  ),
                  if (isOutOfStock) ...[
                    SizedBox(height: 4.h),
                    Text(
                      context.tr('outOfStock'),
                      style: AppTextStyles.caption.copyWith(
                        fontWeight: FontWeight.w500,
                        color: theme.colorScheme.error.withValues(alpha: 0.7),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      }).toList(),
    );
  }
}
