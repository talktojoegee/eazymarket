import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:hyper_commerce/bloc/user_cart_bloc/user_cart_bloc.dart';
import 'package:hyper_commerce/bloc/user_cart_bloc/user_cart_state.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/model/recent_product_model/recent_product_model.dart';
import 'package:hyper_commerce/screens/address_list_page/model/get_address_list_model.dart';
import 'package:hyper_commerce/screens/cart_page/bloc/add_to_cart/add_to_cart_bloc.dart';
import 'package:hyper_commerce/screens/cart_page/bloc/add_to_cart/add_to_cart_state.dart';
import 'package:hyper_commerce/screens/cart_page/widgets/address_selection_bottom_sheet.dart';
import 'package:hyper_commerce/model/selected_address/selected_address_model.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_reel_widget.dart';
import 'package:hyper_commerce/services/address/selected_address_hive.dart';
import 'package:hyper_commerce/services/auth_guard.dart';
import 'package:hyper_commerce/services/market/market_service.dart';
import 'package:hyper_commerce/utils/widgets/custom_toast.dart';
import 'package:hyper_commerce/screens/product_detail_page/bloc/product_detail_bloc/product_detail_bloc.dart';
import 'package:hyper_commerce/screens/product_detail_page/bloc/product_detail_bloc/product_detail_event.dart';
import 'package:hyper_commerce/screens/product_detail_page/bloc/product_detail_bloc/product_detail_state.dart';
import 'package:hyper_commerce/screens/product_detail_page/bloc/product_faq_bloc/product_faq_bloc.dart';
import 'package:hyper_commerce/screens/product_detail_page/bloc/product_review_bloc/product_review_bloc.dart';
import 'package:hyper_commerce/screens/product_detail_page/bloc/similar_product_bloc/similar_product_bloc.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_detail_model.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/app_bar_widget.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/custom_product_feature_section_widget.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/delivery_services_section.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_bottom_cart_bar.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_description_section.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_detail_shimmer.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_faq_section.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_image_slider.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_reviews_section.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_title_header.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_variant_selector.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/seller_store_name_card.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/similar_product_widget.dart';
import 'package:hyper_commerce/services/recent_product/recent_product_service.dart';
import 'package:hyper_commerce/utils/widgets/custom_circular_progress_indicator.dart';
import 'package:hyper_commerce/utils/widgets/custom_scaffold/custom_scaffold.dart';
import 'package:hyper_commerce/utils/widgets/empty_states_page.dart';
import '../utils/product_share_helper.dart';
import '../../../config/global.dart';
import '../../../utils/widgets/animated_button.dart';
import '../../wishlist_page/bloc/get_user_wishlist_bloc/get_user_wishlist_bloc.dart';
import '../../wishlist_page/bloc/get_user_wishlist_bloc/get_user_wishlist_state.dart';
import '../model/product_initial_data.dart';
import '../widgets/product_detail_tablet_view.dart';
import '../widgets/similar_products_bottom_sheet.dart';
export 'package:hyper_commerce/screens/product_detail_page/model/product_initial_data.dart';
export 'package:hyper_commerce/screens/product_detail_page/widgets/rating_bar_widget.dart';

class ProductDetailPage extends StatefulWidget {
  final String productSlug;
  final ProductInitialData? initialData;
  final VoidCallback? closeContainer;

  const ProductDetailPage({
    super.key,
    required this.productSlug,
    this.initialData,
    this.closeContainer,
  });

  @override
  State<ProductDetailPage> createState() => _ProductDetailPageState();
}

class _ProductDetailPageState extends State<ProductDetailPage>
    with SingleTickerProviderStateMixin {
  final GlobalKey _reviewsKey = GlobalKey();
  final ProductDetailBloc _productDetailBloc = ProductDetailBloc();
  Map<String, SwatchValues> selectedVariants = {};
  bool _hasLoggedView = false;

  SelectedAddress? _selectedAddress;

  void _scrollToReviews() {
    final ctx = _reviewsKey.currentContext;
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

  ProductVariants? _currentVariant;

  ProductVariants _getActiveVariant(ProductData product) {
    if (selectedVariants.isEmpty) {
      return product.variants.firstWhere(
        (v) => v.isDefault,
        orElse: () => product.variants.first,
      );
    }

    return product.variants.firstWhere(
      (v) {
        for (var attr in product.attributes) {
          final selected = selectedVariants[attr.name];
          if (selected != null) {
            final variantValue = v.attributes[attr.slug];
            if (variantValue?.toString().toLowerCase().trim() !=
                selected.value.toString().toLowerCase().trim()) {
              return false;
            }
          }
        }
        return true;
      },
      orElse: () => product.variants.firstWhere(
        (v) => v.isDefault,
        orElse: () => product.variants.first,
      ),
    );
  }

  @override
  void initState() {
    super.initState();
    _selectedAddress = HiveSelectedAddressHelper.getSelectedAddress();
    _fetchProductDetail();
  }

  Future<void> _fetchProductDetail({bool silent = false}) async {
    final countryCode = _selectedAddress?.countryCode;
    final marketCode = countryCode != null
        ? await MarketService.marketCodeForCountry(countryCode)
        : null;
    if (!mounted) return;

    _productDetailBloc.add(
      FetchProductDetail(
        productSlug: widget.productSlug,
        addressId: countryCode ?? 'IN',
        marketCode: marketCode,
        silent: silent,
      ),
    );
  }

  @override
  void dispose() {
    _productDetailBloc.close();
    super.dispose();
  }

  Future<void> _onProductViewed(
    String id,
    String name,
    String imageUrl,
    String slug,
  ) async {
    final product = RecentProduct(
      id: id,
      name: name,
      imageUrl: imageUrl,
      productSlug: slug,
    );
    await RecentlyViewedService.addProduct(product);
  }

  void _openAddressBottomSheet(BuildContext context) {
    final selected = _selectedAddress;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      useRootNavigator: true,
      builder: (_) => AddressSelectionBottomSheet(
        selectedAddress:
            selected != null ? AddressListData(id: selected.id) : null,
        onAddressSelected: _onAddressChanged,
      ),
    );
  }

  void _onAddressChanged(AddressListData address) {
    if (_selectedAddress?.id == address.id) return;

    setState(() {
      _selectedAddress = SelectedAddress.fromAddressListData(address);
    });
    _fetchProductDetail(silent: true);
  }

  void _hydrateSelectedVariantsIfEmpty(ProductData product) {
    if (selectedVariants.isNotEmpty || _currentVariant == null) return;
    for (var attr in product.attributes) {
      final variantAttrValue = _currentVariant!.attributes[attr.slug];
      if (variantAttrValue == null) continue;
      try {
        final sw = attr.swatchValues.firstWhere(
          (s) =>
              s.value.toString().toLowerCase().trim() ==
              variantAttrValue.toString().toLowerCase().trim(),
        );
        selectedVariants[attr.name] = sw;
      } catch (_) {
        selectedVariants[attr.name] =
            SwatchValues(value: variantAttrValue.toString(), swatch: '');
      }
    }
  }

  Widget _buildMobileBody({
    required ProductData product,
    required ProductVariants activeVariant,
    required bool isWishlisted,
    required Color surfaceColor,
  }) {
    return CustomScrollView(
      clipBehavior: Clip.antiAlias,
      physics: const ClampingScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: Column(
            children: [
              // Image slider at the top of the body
              ProductImageSlider(
                productSlug: widget.productSlug,
                initialData: widget.initialData,
                loadedProduct: product,
                selectedVariant: activeVariant,
                onRatingTap: _scrollToReviews,
              ),
              Container(
                color: Colors.white,
                padding: const EdgeInsets.only(bottom: 10),
                child: Row(
                  children: [
                    const SizedBox(width: 15),
                    Expanded(
                      child: AnimatedButton(
                        onTap: () => SimilarProductsBottomSheet.show(
                          context,
                          currentProduct: product,
                        ),
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
                                    ? AppTheme.primaryColor
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
                            productSlug: widget.productSlug,
                            productTitle: product.title,
                            selectedAddress: _selectedAddress,
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

              // Card 1 — Title + rating + pricing
              Container(
                width: double.infinity,
                color: surfaceColor,
                padding: EdgeInsets.fromLTRB(
                  12.w,
                  15.h,
                  12.w,
                  12.h,
                ),
                child: ProductTitleHeader(
                  product: product,
                  activeVariant: activeVariant,
                  currentVariant: _currentVariant ?? activeVariant,
                ),
              ),

              // Card 2 — Variant selector
              if (product.attributes.isNotEmpty) ...[
                SizedBox(height: 10.h),
                Container(
                  width: double.infinity,
                  color: surfaceColor,
                  padding: EdgeInsets.fromLTRB(
                    12.w,
                    12.h,
                    12.w,
                    12.h,
                  ),
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
                          setState(() {
                            selectedVariants[attribute.name] = value;
                          });
                        },
                        productAttributes: attribute.swatchValues,
                        variants: product.variants,
                      );
                    },
                  ),
                ),
              ],

              // Card 3 — Delivery & Services
              SizedBox(height: 10.h),
              DeliveryServicesSection(
                product: product,
                activeVariant: activeVariant,
                selectedAddress: _selectedAddress,
                onChangeAddress: () => _openAddressBottomSheet(context),
              ),

              // Card 4 — Product description / details
              SizedBox(height: 10.h),
              Container(
                width: double.infinity,
                color: surfaceColor,
                padding: EdgeInsets.fromLTRB(
                  12.w,
                  0.h,
                  12.w,
                  0.h,
                ),
                child: ProductDescriptionSection(product: product),
              ),

              SizedBox(height: 10.h),
              if (product.variants.first.storeName.isNotEmpty &&
                  product.variants.first.storeSlug.isNotEmpty) ...[
                SellerStoreNameCard(
                  storeName: product.variants.first.storeName,
                  storeSlug: product.variants.first.storeSlug,
                  sellerName: product.seller,
                ),
                SizedBox(height: 10.h),
              ],
              if (product.customProductFeaturedSections.isNotEmpty) ...[
                CustomProductFeatureSectionWidget(
                  sections: product.customProductFeaturedSections,
                ),
                SizedBox(height: 10.h),
              ],
              ProductReviewsSection(key: _reviewsKey, product: product),
              ProductFaqSection(product: product),
              BlocBuilder<SimilarProductBloc, SimilarProductState>(
                builder: (context, state) {
                  if (state is SimilarProductLoaded) {
                    return SimilarProductWidget(
                      product: state.similarProduct,
                      currentProduct: product,
                    );
                  }
                  return const SizedBox.shrink();
                },
              ),
            ],
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider(create: (_) => AddToCartBloc()),
        BlocProvider.value(value: _productDetailBloc),
        BlocProvider(
          create: (_) => ProductReviewBloc()
            ..add(FetchProductReview(productSlug: widget.productSlug)),
        ),
        BlocProvider(
          create: (_) => ProductFAQBloc()
            ..add(FetchProductFAQ(productSlug: widget.productSlug)),
        ),
        BlocProvider(
          create: (_) => SimilarProductBloc()
            ..add(FetchSimilarProduct(
              excludeProductSlug: [widget.productSlug],
            )),
        ),
      ],
      child: CustomScaffold(
        showViewCart: true,
        backgroundColor: Theme.of(context).colorScheme.surfaceContainer,
        body: MultiBlocListener(
          listeners: [
            // Pre-existing (kept as a no-op slot — some downstream code
            BlocListener<AddToCartBloc, AddToCartState>(
              listener: (_, __) {},
            ),
            // Catches server-side cart errors (e.g.
            BlocListener<CartBloc, CartState>(
              listenWhen: (prev, next) =>
                  next is CartLoaded && next.errorMessage != null,
              listener: (context, state) {
                if (state is CartLoaded && state.errorMessage != null) {
                  ToastManager.show(
                    context: context,
                    message: state.errorMessage!,
                    type: ToastType.error,
                  );
                }
              },
            ),
          ],
          child: BlocBuilder<ProductDetailBloc, ProductDetailState>(
            builder: (BuildContext context, ProductDetailState state) {
              if (state is ProductDetailLoading || state is ProductDetailInitial) {
                return NestedScrollView(
                  headerSliverBuilder:
                      (BuildContext context, bool innerBoxIsScrolled) {
                    return [
                      const AppBarWidget(
                        showTitle: true,
                      ),
                    ];
                  },
                  body: isTablet(context)
                      ? const ProductDetailTabletShimmer()
                      : const ProductDetailShimmer(),
                );
              }
              else if (state is ProductDetailLoaded) {
                final product = state.productData[0];

                if (product.variants.isEmpty) {
                  return const NoProductPage();
                }

                _currentVariant ??= product.variants.firstWhere(
                  (v) => v.isDefault,
                  orElse: () => product.variants.first,
                );

                _hydrateSelectedVariantsIfEmpty(product);

                final activeVariant = _getActiveVariant(product);
                _currentVariant = activeVariant;
                final bool isWishlisted = product.favorite.isNotEmpty;

                if (!_hasLoggedView) {
                  _hasLoggedView = true;
                  _onProductViewed(
                    product.id.toString(),
                    product.title,
                    product.mainImage,
                    product.slug,
                  );
                }

                final surfaceColor = Theme.of(context).colorScheme.surface;

                final scrollView = NestedScrollView(
                  headerSliverBuilder:
                      (BuildContext context, bool innerBoxIsScrolled) {
                    return [
                      AppBarWidget(
                        showTitle: innerBoxIsScrolled,
                      ),
                    ];
                  },
                  body: isTablet(context)
                      ? ProductDetailTabletView(
                          product: product,
                          activeVariant: activeVariant,
                          currentVariant: _currentVariant ?? activeVariant,
                          isWishlisted: isWishlisted,
                          surfaceColor: surfaceColor,
                          selectedVariants: selectedVariants,
                          onVariantSelected: (name, value) {
                            setState(() {
                              selectedVariants[name] = value;
                            });
                          },
                          selectedAddress: _selectedAddress,
                          onChangeAddress: () =>
                              _openAddressBottomSheet(context),
                          reviewsKey: _reviewsKey,
                          productSlug: widget.productSlug,
                          initialData: widget.initialData,
                        )
                      : _buildMobileBody(
                          product: product,
                          activeVariant: activeVariant,
                          isWishlisted: isWishlisted,
                          surfaceColor: surfaceColor,
                        ),
                );

                return Stack(
                  children: [
                    scrollView,
                    if (state.isRefreshing)
                      Positioned.fill(
                        child: AbsorbPointer(
                          child: ColoredBox(
                            color: Theme.of(context)
                                .colorScheme
                                .scrim
                                .withValues(alpha: 0.06),
                            child: const CustomCircularProgressIndicator(),
                          ),
                        ),
                      ),
                  ],
                );
              }
              if (state is ProductDetailFailed) {
                return const NoProductPage();
              }
              return const CustomCircularProgressIndicator();
            },
          ),
        ),
        floatingActionButton: BlocBuilder<ProductDetailBloc, ProductDetailState>(
          buildWhen: (previous, current) {
            if (previous is ProductDetailLoaded && current is ProductDetailLoaded) {
              return previous.productData.first.reels !=
                  current.productData.first.reels;
            }
            return previous.runtimeType != current.runtimeType;
          },
          builder: (context, state) {
            if (state is ProductDetailLoaded) {
              final reels = state.productData.first.reels;
              if (reels.isNotEmpty) {
                return ProductReelWidget(reels: reels);
              }
            }
            return const SizedBox.shrink();
          },
        ),
        bottomNavigationBar: ProductBottomCartBar(
          selectedVariants: selectedVariants,
          countryCode: _selectedAddress?.countryCode ?? MarketService.currentCountryIso2(),
        ),
      ),
    );
  }
}
