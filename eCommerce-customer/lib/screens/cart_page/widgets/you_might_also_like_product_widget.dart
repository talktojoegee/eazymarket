import 'package:flutter/material.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_detail_model.dart';
import 'package:hyper_commerce/utils/widgets/bottom_variant_selector_with_addons.dart';
import '../../../bloc/user_cart_bloc/user_cart_bloc.dart';
import '../../../bloc/user_cart_bloc/user_cart_event.dart';
import '../../../model/user_cart_model/cart_sync_action.dart';
import '../../../model/user_cart_model/user_cart.dart';
import '../../../utils/widgets/grid_helper.dart';
import '../../../utils/widgets/product_card/product_card_style1.dart';
import '../../ad_campaign/bloc/ad_click_bloc/ad_click_bloc.dart';
import '../../ad_campaign/widgets/ad_visibility_observer.dart';

class YouMightAlsoLikeProductWidget extends StatelessWidget {
  final List<ProductData> productData;
  final int? addressId;
  final String? promoCode;
  final bool? rushDelivery;
  final bool? useWallet;
  final bool? isFromCartPage;

  const YouMightAlsoLikeProductWidget(
      {super.key,
      required this.productData,
      this.addressId,
      this.promoCode,
      this.rushDelivery,
      this.useWallet,
      this.isFromCartPage});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: BorderRadius.circular(0.r)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: EdgeInsets.only(
                left: 12.0.w, right: 12.0.w, top: 12.h, bottom: 12.h),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  context.tr('youMightAlsoLike'),
                  style: AppTextStyles.titleMedium,
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.only(left: 12.0.w, right: 12.0.w, bottom: 12.h),
            child: GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: sliverGridDelegate(context,
                  mainAxisSpacing: 8.w,
                  crossAxisSpacing: 8.w,
                  mainAxisExtent: isTablet(context) ? 280.h : 230.h),
              itemCount: productData.length > 15 ? 15 : productData.length,
              itemBuilder: (context, index) {
                final product = productData[index];
                ProductVariants? defaultVariant;
                if (product.variants.isNotEmpty) {
                  defaultVariant = product.variants.firstWhere(
                    (variant) => variant.isDefault == true,
                    orElse: () => product.variants.first,
                  );
                }

                // Set price and specialPrice based on the default variant
                final price = defaultVariant != null
                    ? defaultVariant.price.toString()
                    : '0';
                final specialPrice = defaultVariant != null
                    ? defaultVariant.specialPrice.toString()
                    : '';

                final card = ProductCardStyle1(
                  productId: product.id,
                  productImage: product.mainImage,
                  productImages: [
                    product.mainImage,
                    ...product.additionalImages,
                    ...product.variants.map((v) => v.image),
                  ].where((img) => img.isNotEmpty).toSet().toList(),
                  productSlug: product.slug,
                  productName: product.title,
                  productPrice: price,
                  specialPrice: specialPrice,
                  productTags: const [],
                  estimatedDeliveryTime:
                      product.estimatedDeliveryTime.toString(),
                  assetImage: '',
                  ratings: double.parse(product.ratings.toString()),
                  ratingCount: product.ratingCount,
                  onAddToCart: () {
                    if (product.variants.length > 1 ||
                        product.variants
                            .any((v) => v.addonGroups.isNotEmpty == true)) {
                      showVariantBottomSheetWithAddons(
                          variantsList: product.variants,
                          productData: product,
                          productImage: product.mainImage,
                          quantityStepSize: product.quantityStepSize,
                          context: context,
                          addressId: addressId,
                          promoCode: promoCode,
                          rushDelivery: rushDelivery,
                          useWallet: useWallet,
                          isFromCartPage: isFromCartPage);
                    } else {
                      final item = UserCart(
                          productId: product.id.toString(),
                          variantId: defaultVariant!.id.toString(),
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
                          isSynced: false);
                      context.read<CartBloc>().add(AddToCart(
                          item: item,
                          context: context,
                          isFromCartPage: true,
                          useWallet: useWallet,
                          rushDelivery: rushDelivery,
                          addressId: addressId,
                          promoCode: promoCode));
                    }
                  },
                  variantCount: product.variants.length,
                  onVariantSelectorRequested: product.variants.length > 1
                      ? () => showVariantBottomSheetWithAddons(
                          variantsList: product.variants,
                          productData: product,
                          productImage: product.mainImage,
                          quantityStepSize: product.quantityStepSize,
                          context: context,
                          addressId: addressId,
                          promoCode: promoCode,
                          rushDelivery: rushDelivery,
                          useWallet: useWallet,
                          isFromCartPage: isFromCartPage)
                      : null,
                  isStoreOpen: true,
                  isWishListed: product.favorite.isNotEmpty,
                  productVariantId: defaultVariant!.id,
                  storeId: defaultVariant.storeId,
                  wishlistItemId: product.favorite.isNotEmpty
                      ? product.favorite.first.id ?? 0
                      : 0,
                  totalStocks: defaultVariant.stock,
                  imageFit: product.imageFit,
                  quantityStepSize: product.quantityStepSize,
                  minQty: product.minimumOrderQuantity,
                  totalAllowedQuantity: product.totalAllowedQuantity,
                  isSponsored: product.isSponsored,
                  onCardTap: product.isSponsored
                      ? () => context.read<AdClickBloc>().add(RecordClick(
                            campaignId: product.campaignId,
                            visitorKey: product.visitorKey,
                          ))
                      : null,
                  isInStock: defaultVariant.isInStock,
                  brandName: product.brandName,
                );

                final Widget cardWidget =
                    product.isSponsored && product.campaignId > 0
                        ? AdVisibilityObserver(
                            campaignId: product.campaignId,
                            visitorKey: product.visitorKey,
                            child: card,
                          )
                        : card;

                return cardWidget;
              },
            ),
          ),
        ],
      ),
    );
  }
}
