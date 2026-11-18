import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import '../utils/product_share_helper.dart';

import '../../../config/global.dart';
import '../../../config/helper.dart';
import '../../../model/selected_address/selected_address_model.dart';
import '../../../services/auth_guard.dart';
import '../../../utils/widgets/animated_button.dart';
import '../../../utils/widgets/custom_shimmer.dart';
import '../../wishlist_page/bloc/get_user_wishlist_bloc/get_user_wishlist_bloc.dart';
import '../../wishlist_page/bloc/get_user_wishlist_bloc/get_user_wishlist_state.dart';
import '../bloc/similar_product_bloc/similar_product_bloc.dart';
import '../model/product_detail_model.dart';
import '../model/product_initial_data.dart';
import 'custom_product_feature_section_widget.dart';
import 'delivery_services_section.dart';
import 'product_description_section.dart';
import 'product_faq_section.dart';
import 'product_image_slider.dart';
import 'product_reviews_section.dart';
import 'product_title_header.dart';
import 'product_variant_selector.dart';
import 'seller_store_name_card.dart';
import 'similar_product_widget.dart';
import 'similar_products_bottom_sheet.dart';

/// Dedicated 2-Column Master-Detail Layout for iPad and Tablet devices.
class ProductDetailTabletView extends StatelessWidget {
  final ProductData product;
  final ProductVariants activeVariant;
  final ProductVariants currentVariant;
  final bool isWishlisted;
  final Color surfaceColor;
  final Map<String, SwatchValues> selectedVariants;
  final void Function(String attributeName, SwatchValues value) onVariantSelected;
  final SelectedAddress? selectedAddress;
  final VoidCallback onChangeAddress;
  final GlobalKey reviewsKey;
  final String productSlug;
  final ProductInitialData? initialData;

  const ProductDetailTabletView({
    super.key,
    required this.product,
    required this.activeVariant,
    required this.currentVariant,
    required this.isWishlisted,
    required this.surfaceColor,
    required this.selectedVariants,
    required this.onVariantSelected,
    required this.selectedAddress,
    required this.onChangeAddress,
    required this.reviewsKey,
    required this.productSlug,
    this.initialData,
  });

  void _scrollToReviews() {
    final ctx = reviewsKey.currentContext;
    if (ctx == null) return;
    final renderObject = ctx.findRenderObject();
    if (renderObject == null) return;

    final position = Scrollable.of(ctx).position;
    final viewport = RenderAbstractViewport.of(renderObject);
    final double reveal = viewport.getOffsetToReveal(renderObject, 0.0).offset;
    final double target = (reveal - 100.h)
        .clamp(position.minScrollExtent, position.maxScrollExtent)
        .toDouble();

    position.animateTo(
      target,
      duration: const Duration(milliseconds: 500),
      curve: Curves.easeInOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Left Column (Gallery + Specs + Store Info)
        Expanded(
          flex: 5,
          child: SingleChildScrollView(
            primary: false,
            physics: const ClampingScrollPhysics(),
            padding: EdgeInsets.fromLTRB(16.w, 14.h, 8.w, 14.h),
            child: _buildLeftGalleryAndSpecsColumn(context),
          ),
        ),
        // Right Column (Title + Pricing + Variants + Delivery + Reviews + FAQs + Similar)
        Expanded(
          flex: 6,
          child: CustomScrollView(
            clipBehavior: Clip.antiAlias,
            physics: const ClampingScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(8.w, 14.h, 16.w, 14.h),
                  child: _buildRightDetailsColumn(context),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildLeftGalleryAndSpecsColumn(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ClipRRect(
          // borderRadius: BorderRadius.circular(12.r),
          borderRadius: BorderRadiusGeometry.only(
            topLeft: Radius.circular(12.r),
            topRight: Radius.circular(12.r),
          ),
          child: ProductImageSlider(
            productSlug: productSlug,
            initialData: initialData,
            loadedProduct: product,
            selectedVariant: activeVariant,
            onRatingTap: _scrollToReviews,
          ),
        ),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.only(
              bottomLeft: Radius.circular(12.r),
              bottomRight: Radius.circular(12.r),
            ),
            // borderRadius: BorderRadius.circular(12.r),
          ),
          padding: EdgeInsets.symmetric(vertical: 6.h).copyWith(bottom: 6.h),
          child: Row(
            children: [
              const SizedBox(width: 15),
              Expanded(
                child: AnimatedButton(
                  onTap: () => SimilarProductsBottomSheet.show(context),
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      color: Colors.grey.shade100,
                    ),
                    height: 30,
                    child: const Icon(TablerIcons.carousel_horizontal, size: 20),
                  ),
                ),
              ),
              const SizedBox(width: 15),
              Expanded(
                child: BlocBuilder<UserWishlistBloc, UserWishlistState>(
                  builder: (context, wishlistState) {
                    final bloc = context.read<UserWishlistBloc>();
                    final productId = product.id;
                    final productVariantId = product.variants
                        .firstWhere((variant) => variant.isDefault)
                        .id;
                    final storeId = product.variants
                        .firstWhere((variant) => variant.isDefault)
                        .storeId;

                    final isWishListedFromBloc = bloc.isProductWishlisted(
                        productId, productVariantId, storeId);
                    final hasBlocData =
                        bloc.hasProductData(productId, productVariantId, storeId);
                    final finalIsWishListed =
                        hasBlocData ? isWishListedFromBloc : isWishlisted;

                    return AnimatedButton(
                      onTap: () async {
                        if (Global.userData == null) {
                          await AuthGuard.ensureLoggedIn(context);
                          return;
                        }
                        bloc.add(ToggleFavorite(
                          productId: productId,
                          productVariantId: productVariantId,
                          storeId: storeId,
                          currentlyFavorited: finalIsWishListed,
                        ));
                      },
                      child: Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                          color: Colors.grey.shade100,
                        ),
                        height: 30,
                        child: Icon(
                          finalIsWishListed
                              ? AppHelpers.wishListedIcon
                              : AppHelpers.notWishListedIcon,
                          color: finalIsWishListed
                              ? Colors.red
                              : Theme.of(context).colorScheme.tertiary,
                          size: 20,
                        ),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(width: 15),
              Expanded(
                child: Builder(
                  builder: (btnContext) => AnimatedButton(
                    onTap: () => ProductShareHelper.shareProduct(
                      context: btnContext,
                      productSlug: productSlug,
                      productTitle: product.title,
                      selectedAddress: selectedAddress,
                    ),
                    child: Container(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(10),
                        color: Colors.grey.shade100,
                      ),
                      height: 30,
                      child: const Icon(TablerIcons.share_3, size: 20),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 15),
            ],
          ),
        ),
        SizedBox(height: 10.h),
        // Specifications & Description
        Container(
          width: double.infinity,
          decoration: BoxDecoration(
            color: surfaceColor,
            borderRadius: BorderRadius.circular(12.r),
          ),
          padding: EdgeInsets.fromLTRB(12.w, 0.h, 12.w, 0.h),
          child: ProductDescriptionSection(product: product),
        ),
        if (product.variants.first.storeName.isNotEmpty &&
            product.variants.first.storeSlug.isNotEmpty) ...[
          SizedBox(height: 10.h),
          ClipRRect(
            borderRadius: BorderRadius.circular(12.r),
            child: SellerStoreNameCard(
              storeName: product.variants.first.storeName,
              storeSlug: product.variants.first.storeSlug,
              sellerName: product.seller,
            ),
          ),
        ],
        if (product.customProductFeaturedSections.isNotEmpty) ...[
          SizedBox(height: 10.h),
          ClipRRect(
            borderRadius: BorderRadius.circular(12.r),
            child: CustomProductFeatureSectionWidget(
              sections: product.customProductFeaturedSections,
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildRightDetailsColumn(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Title + Rating + Pricing
        Container(
          width: double.infinity,
          decoration: BoxDecoration(
            color: surfaceColor,
            borderRadius: BorderRadius.circular(12.r),
          ),
          padding: EdgeInsets.fromLTRB(12.w, 15.h, 12.w, 12.h),
          child: ProductTitleHeader(
            product: product,
            activeVariant: activeVariant,
            currentVariant: currentVariant,
          ),
        ),
        // Variant Selector
        if (product.attributes.isNotEmpty) ...[
          SizedBox(height: 10.h),
          Container(
            width: double.infinity,
            decoration: BoxDecoration(
              color: surfaceColor,
              borderRadius: BorderRadius.circular(12.r),
            ),
            padding: EdgeInsets.fromLTRB(12.w, 12.h, 12.w, 12.h),
            child: ListView.builder(
              padding: EdgeInsets.zero,
              physics: const NeverScrollableScrollPhysics(),
              shrinkWrap: true,
              itemCount: product.attributes.length,
              itemBuilder: (context, index) {
                final attribute = product.attributes[index];
                return ProductVariantSelector(
                  label: attribute.name,
                  attributeSlug: attribute.slug,
                  variantType: attribute.swatcheType,
                  selectedValue: selectedVariants[attribute.name],
                  onSelected: (SwatchValues value) {
                    onVariantSelected(attribute.name, value);
                  },
                  productAttributes: attribute.swatchValues,
                  variants: product.variants,
                );
              },
            ),
          ),
        ],
        // Delivery & Services
        SizedBox(height: 10.h),
        ClipRRect(
          borderRadius: BorderRadius.circular(12.r),
          child: DeliveryServicesSection(
            product: product,
            activeVariant: activeVariant,
            selectedAddress: selectedAddress,
            onChangeAddress: onChangeAddress,
          ),
        ),
        // Customer Reviews
        SizedBox(height: 10.h),
        ClipRRect(
          borderRadius: BorderRadius.circular(12.r),
          child: ProductReviewsSection(key: reviewsKey, product: product),
        ),
        // FAQs
        SizedBox(height: 10.h),
        ClipRRect(
          borderRadius: BorderRadius.circular(12.r),
          child: ProductFaqSection(product: product),
        ),
        // Similar Products
        SizedBox(height: 10.h),
        BlocBuilder<SimilarProductBloc, SimilarProductState>(
          builder: (context, state) {
            if (state is SimilarProductLoaded) {
              return ClipRRect(
                borderRadius: BorderRadius.circular(12.r),
                child: SimilarProductWidget(
                  product: state.similarProduct,
                  currentProduct: product,
                ),
              );
            }
            return const SizedBox.shrink();
          },
        ),
      ],
    );
  }
}

/// 2-Column Shimmer skeleton loading placeholder for iPad and Tablet.
class ProductDetailTabletShimmer extends StatelessWidget {
  const ProductDetailTabletShimmer({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 5,
            child: Column(
              children: [
                ShimmerWidget.rectangular(
                  height: 380.h,
                  borderRadius: 16,
                  isBorder: true,
                ),
                SizedBox(height: 12.h),
                Row(
                  children: [
                    Expanded(
                      child: ShimmerWidget.rectangular(
                        height: 30,
                        borderRadius: 8,
                        isBorder: true,
                      ),
                    ),
                    const SizedBox(width: 15),
                    Expanded(
                      child: ShimmerWidget.rectangular(
                        height: 30,
                        borderRadius: 8,
                        isBorder: true,
                      ),
                    ),
                    const SizedBox(width: 15),
                    Expanded(
                      child: ShimmerWidget.rectangular(
                        height: 30,
                        borderRadius: 8,
                        isBorder: true,
                      ),
                    ),
                  ],
                ),
                SizedBox(height: 12.h),
                ShimmerWidget.rectangular(
                  height: 130.h,
                  borderRadius: 12,
                  isBorder: true,
                ),
                SizedBox(height: 12.h),
                ShimmerWidget.rectangular(
                  height: 50.h,
                  borderRadius: 12,
                  isBorder: true,
                ),
              ],
            ),
          ),
          SizedBox(width: 16.w),
          Expanded(
            flex: 6,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ShimmerWidget.rectangular(
                  height: 28.h,
                  width: 250.w,
                  borderRadius: 8,
                  isBorder: true,
                ),
                SizedBox(height: 10.h),
                ShimmerWidget.rectangular(
                  height: 22.h,
                  width: 150.w,
                  borderRadius: 8,
                  isBorder: true,
                ),
                SizedBox(height: 14.h),
                ShimmerWidget.rectangular(
                  height: 80.h,
                  borderRadius: 12,
                  isBorder: true,
                ),
                SizedBox(height: 14.h),
                ShimmerWidget.rectangular(
                  height: 110.h,
                  borderRadius: 12,
                  isBorder: true,
                ),
                SizedBox(height: 14.h),
                ShimmerWidget.rectangular(
                  height: 140.h,
                  borderRadius: 12,
                  isBorder: true,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
