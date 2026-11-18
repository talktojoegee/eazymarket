import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_detail_model.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_initial_data.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/image_slider_page.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_video_player.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';
import 'package:hyper_commerce/utils/widgets/hero_tags.dart';

class ProductImageSlider extends StatefulWidget {
  final String productSlug;
  final ProductInitialData? initialData;
  final ProductData? loadedProduct;
  final ProductVariants? selectedVariant;
  final VoidCallback? onRatingTap;

  const ProductImageSlider({
    super.key,
    required this.productSlug,
    this.initialData,
    this.loadedProduct,
    this.selectedVariant,
    this.onRatingTap,
  });

  @override
  State<ProductImageSlider> createState() => _ProductImageSliderState();
}

class _ProductImageSliderState extends State<ProductImageSlider> {
  int _currentPage = 0;
  final CarouselSliderController _controller = CarouselSliderController();

  @override
  void didUpdateWidget(ProductImageSlider oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.selectedVariant != oldWidget.selectedVariant &&
        widget.selectedVariant != null) {
      final images = getDisplayedImages();
      final index = images.indexOf(widget.selectedVariant!.image);
      if (index != -1) {
        final videoUrl = widget.loadedProduct?.videoLink ??
            widget.initialData?.videoUrl ??
            '';
        final targetIndex = videoUrl.isNotEmpty ? index + 1 : index;
        _controller.animateToPage(targetIndex,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeInOut);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final product = widget.loadedProduct;
    final double imageHeight =
        isTablet(context) ? 400.0 : MediaQuery.of(context).size.width;

    List<String> images = [];
    String videoUrl = '';
    final String heroTag = productHeroTag(widget.productSlug);

    if (product != null) {
      if (product.mainImage.isNotEmpty) images.add(product.mainImage);
      images.addAll(product.additionalImages);
      videoUrl = product.videoLink;
    }
    else if (widget.initialData != null) {
      images.add(widget.initialData!.mainImage);
      images.addAll(widget.initialData!.additionalImages);
      videoUrl = widget.initialData!.videoUrl;
    }
    else {
      return SizedBox(
        height: imageHeight,
        child: Container(
            color: Theme.of(context).colorScheme.surfaceContainerHighest),
      );
    }

    final displayedImages = getDisplayedImages();
    final hasVideo = (widget.loadedProduct?.videoLink ??
            widget.initialData?.videoUrl ??
            '')
        .isNotEmpty;

    final int totalItems = videoUrl.isNotEmpty
        ? displayedImages.length + 1
        : displayedImages.length;


    return Container(
      color: Theme.of(context).colorScheme.surface,
      child: Column(
        children: [
          SizedBox(
            height: isTablet(context) ? 400.0 : 400.0,
            child: Stack(
              children: [
                CarouselSlider(
                  carouselController: _controller,
                  options: CarouselOptions(
                    height: double.infinity,
                    autoPlay: false,
                    enableInfiniteScroll: false,
                    enlargeCenterPage: false,
                    viewportFraction: 1.0,
                    onPageChanged: (index, reason) {
                      setState(() {
                        _currentPage = index;
                      });
                    },
                  ),
                  items: List.generate(totalItems, (index) {
                    // Video first if exists
                    if (index == 0 && videoUrl.isNotEmpty && hasVideo) {
                      return Padding(
                        padding: EdgeInsets.symmetric(horizontal: 0.w),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(0.r),
                          child: ProductVideoPlayer(
                            videoUrl: videoUrl,
                            isActive: _currentPage == index,
                            isFullScreen: false,
                            onExpand: () => _openFullScreenSlider(
                                context, displayedImages, videoUrl, 0),
                          ),
                        ),
                      );
                    }

                    final int imageIndex =
                        videoUrl.isNotEmpty ? index - 1 : index;
                    final imageUrl = displayedImages[imageIndex];

                    return GestureDetector(
                      onTap: () => _openFullScreenSlider(
                        context,
                        displayedImages,
                        videoUrl,
                        videoUrl.isNotEmpty ? index : index,
                      ),
                      child: SizedBox(
                        width: isTablet(context)
                            ? double.infinity
                            : MediaQuery.of(context).size.width,
                        child: Hero(
                          tag: (imageUrl == displayedImages.first)
                              ? heroTag
                              : productGalleryHeroTag(
                                  widget.productSlug, imageIndex),
                          child: CustomImageContainer(
                            imagePath: imageUrl,
                            fit: BoxFit.contain,
                          ),
                        ),
                      ),
                    );
                  }),
                ),
                // Similar-products button, placed to the LEFT of the
                // rating/review card. The button is always shown; the rating
                // card only appears when the product has ratings.
                PositionedDirectional(
                  bottom: 12.h,
                  end: 12.w,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      const SizedBox(height: 10,),
                      if (product!.ratingCount > 0) ...[
                        SizedBox(width: 8.w),
                        GestureDetector(
                          onTap: widget.onRatingTap,
                          child: Container(
                            padding: EdgeInsets.symmetric(
                                horizontal: 10.w, vertical: 6.h),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(10.r),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.15),
                                  blurRadius: 8,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  product.ratings.toStringAsFixed(1),
                                  style: AppTextStyles.body.copyWith(
                                    fontWeight: FontWeight.w700,
                                    color:
                                        Theme.of(context).colorScheme.onSurface,
                                  ),
                                ),
                                SizedBox(width: 4.w),
                                Icon(
                                  AppTheme.ratingStarIconFilled,
                                  color: AppTheme.ratingStarColor,
                                  size: 16.sp,
                                ),
                                SizedBox(width: 6.w),
                                Container(
                                  width: 1,
                                  height: 14.h,
                                  color: Theme.of(context).dividerColor,
                                ),
                                SizedBox(width: 6.w),
                                Text(
                                  product.ratingCount.toString(),
                                  style: AppTextStyles.bodySmall.copyWith(
                                    fontWeight: FontWeight.w500,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .onSurfaceVariant,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),

                // Product Indicator
                if (product.indicator.isNotEmpty &&
                    (product.indicator == 'veg' ||
                        product.indicator == 'non-veg'))
                  PositionedDirectional(
                    bottom: 15,
                    end: 15,
                    child: Container(
                      width: 24.sp,
                      height: 24.sp,
                      padding: EdgeInsets.all(4.sp),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.8),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Container(
                        decoration: BoxDecoration(
                          border: Border.all(
                            color: product.indicator == 'veg'
                                ? Colors.green
                                : Colors.red,
                            width: 1.5,
                          ),
                          borderRadius: BorderRadius.circular(2),
                        ),
                        child: Center(
                          child: Container(
                            width: 10.sp,
                            height: 10.sp,
                            decoration: BoxDecoration(
                              color: product.indicator == 'veg'
                                  ? Colors.green
                                  : Colors.red,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),

                // Top-Left Floating Slide Counter Pill
                /*if (totalItems > 1)
                  PositionedDirectional(
                    top: 12.h,
                    start: 12.w,
                    child: Container(
                      padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 5.h),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.45),
                        borderRadius: BorderRadius.circular(20.r),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.2),
                          width: 0.8,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (hasVideo && _currentPage == 0) ...[
                            Icon(Icons.videocam_rounded, size: 13.sp, color: Colors.white),
                            SizedBox(width: 4.w),
                          ],
                          Text(
                            '${_currentPage + 1} / $totalItems',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 11.sp,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 0.3,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),*/

              ],
            ),
          ),

          // Morphing Animated Dot Indicator
          if (totalItems > 1)
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 10.h),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(totalItems, (i) {
                  final bool isSelected = i == _currentPage;
                  return AnimatedContainer(
                    duration: const Duration(milliseconds: 250),
                    curve: Curves.easeOutCubic,
                    margin: EdgeInsets.symmetric(horizontal: 3.w),
                    width: isSelected ? 20.w : 6.w,
                    height: 5.h,
                    decoration: BoxDecoration(
                      color: isSelected
                          ? AppTheme.primaryColor
                          : Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(4.r),
                    ),
                  );
                }),
              ),
            ),
        ],
      ),
    );
  }

  List<String> getDisplayedImages() {
    final product = widget.loadedProduct;
    if (product == null) {
      final init = widget.initialData;
      return ([
        if (init?.mainImage != null && init!.mainImage.isNotEmpty)
          init.mainImage,
        ...?init?.additionalImages,
      ]).toSet().toList();
    }

    final List<String> images = [];

    // Always keep main image at start
    if (product.mainImage.isNotEmpty) {
      images.add(product.mainImage);
    }

    // Add ALL unique variant images so we can scroll to them
    for (var v in product.variants) {
      if (v.image.isNotEmpty) {
        images.add(v.image);
      }
    }

    // Add remaining gallery images
    images.addAll(product.additionalImages);

    // Deduplicate while preserving order
    return images.toSet().toList();
  }

  void _openFullScreenSlider(
    BuildContext context,
    List<String> images,
    String videoUrl,
    int initialIndex,
  ) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ImageSliderPage(
          images: images,
          videoUrl: videoUrl,
          initialIndex: initialIndex,
        ),
      ),
    );
  }
}

