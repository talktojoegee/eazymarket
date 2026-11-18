import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';
import 'package:lottie/lottie.dart';

enum HeroLayout { bannerOnly, withItems }

enum HeroCardStyle { full, imageTitle }

class HeroSectionStyle extends StatelessWidget {
  final String bgType;
  final String bgPath;
  final List<HeroPromoCard>? cards;
  final HeroLayout layout;
  final double? bannerHeight;
  final String? cardBgType;
  final Color? cardBgColor;
  final String? cardBgImage;
  final Color? cardTitleColor;
  final HeroCardStyle cardStyle;
  final bool heroFirst;

  const HeroSectionStyle({
    super.key,
    this.bgType = 'image',
    this.bgPath = 'assets/images/hero-image-8.png',
    this.cards,
    this.layout = HeroLayout.withItems,
    this.bannerHeight,
    this.cardBgType,
    this.cardBgColor,
    this.cardBgImage,
    this.cardTitleColor,
    this.cardStyle = HeroCardStyle.imageTitle,
    required this.heroFirst
  });

  BoxDecoration get _cardDecoration {
    if (cardBgType == 'image' &&
        cardBgImage != null &&
        cardBgImage!.isNotEmpty) {
      return BoxDecoration(
        image: DecorationImage(
          image: NetworkImage(cardBgImage!),
          fit: BoxFit.cover,
        ),
      );
    }
    return BoxDecoration(
      color: cardBgColor ?? Colors.white.withValues(alpha: 0.9),
    );
  }

  static const Color _cardBottom = Colors.lightGreen;

  @override
  Widget build(BuildContext context) {
    switch (layout) {
      case HeroLayout.bannerOnly:
        return _bannerOnly();
      case HeroLayout.withItems:
        return _withItems(context);
    }
  }

  Widget _bannerOnly() {
    return SizedBox(
      width: double.infinity,
      child: Padding(
        padding: EdgeInsets.only(
            left: heroFirst ? 0 : 10.w,
            right: heroFirst ? 0 : 10.w,
            top: heroFirst ? 0 : 10.w,
            bottom: heroFirst ? 0 : 10.w
        ),
        child: ClipRRect(

          clipBehavior: Clip.antiAlias,
          child: heroBackground(bgType: bgType, bgPath: bgPath),
        ),
      ),
    );
  }

  Widget _withItems(BuildContext ctx) {
    final tablet = isTablet(ctx);
    final items = cards ?? [];
    const int itemsPerRow = 3;
    final rows = <List<HeroPromoCard>>[];
    for (var i = 0; i < items.length; i += itemsPerRow) {
      rows.add(items.sublist(
          i, (i + itemsPerRow) > items.length ? items.length : i + itemsPerRow));
    }

    final double containerHeight = tablet ? 450.h : 270.h;

    final EdgeInsets containerPadding = EdgeInsets.only(
            left: heroFirst ? 0 : 10.w,
            right: heroFirst ? 0 : 10.w,
            top: heroFirst ? 0 : 10.w,
            bottom: heroFirst ? 0 : 8.w,
          );

    final double topLeftRadius = (heroFirst) ? 0 : 20.0;
    final double topRightRadius = (heroFirst ) ? 0 : 20.0;

    final EdgeInsets mosaicPadding = tablet
        ? EdgeInsets.fromLTRB(36.w, 105.h, 36.w, 12.h)
        : EdgeInsets.fromLTRB(12.w, 85.h, 12.w, 8.h);

    return Container(
      height: containerHeight,
      padding: containerPadding,
      child: Stack(
        children: [
          Container(
            height: containerHeight,
            width: double.infinity,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(topLeftRadius),
                topRight: Radius.circular(topRightRadius),
                bottomLeft: const Radius.circular(20),
                bottomRight: const Radius.circular(20),
              ),
              color: Colors.transparent,
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(topLeftRadius),
                topRight: Radius.circular(topRightRadius),
                bottomLeft: const Radius.circular(20),
                bottomRight: const Radius.circular(20),
              ),
              clipBehavior: Clip.antiAlias,
              child: RepaintBoundary(child: heroBackground(bgType: bgType, bgPath: bgPath)),
            ),
          ),
          // Promo card mosaic.
          Padding(
            padding: mosaicPadding,
            child: Column(
              children: [
                for (final row in rows) ...[
                  Expanded(
                    child: Row(
                      children: _rowChildren(row, ctx, itemsPerRow),
                    ),
                  ),
                  if (row != rows.last) SizedBox(height: 8.h),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _rowChildren(
      List<HeroPromoCard> row, BuildContext context, int itemsPerRow) {
    final children = <Widget>[];
    for (var i = 0; i < row.length; i++) {
      if (i > 0) children.add(SizedBox(width: isTablet(context) ? 40.w : 8.w));
      children.add(Expanded(
        child: _SmallCard(
          card: row[i],
          decoration: _cardDecoration,
          cardTitleColor:
              cardTitleColor ?? Theme.of(context).colorScheme.tertiary,
          cardStyle: cardStyle,
        ),
      ));
    }
    // Keep card widths consistent when the last row has fewer than itemsPerRow.
    for (var i = row.length; i < itemsPerRow; i++) {
      children.add(SizedBox(width: 8.w));
      children.add(const Expanded(child: SizedBox.shrink()));
    }
    return children;
  }

  Widget heroBackground ({required String bgType, required String bgPath}) {
    if(bgType == 'animation') {
      final errorWidget = Center(
        child: Text(
          'Hero animation',
          style: AppTextStyles.displayLarge.copyWith(
            fontWeight: FontWeight.w900,
            color: _cardBottom,
          ),
        ),
      );
      // hero_media may be a network URL (API) or a bundled asset.
      return bgPath.startsWith('http')
          ? Lottie.network(
        bgPath,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => errorWidget,
      )
          : Lottie.asset(
        bgPath,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => errorWidget,
      );
    } else {
      return CustomImageContainer(
        imagePath: bgPath,
        fit: BoxFit.cover,
        placeholder: Align(
          alignment: AlignmentGeometry.center,
          child: Image.asset(
            'assets/images/placeholder.png',
            fit: BoxFit.contain,
            height: 100,
          ),
        ),
      );
    }
  }
}

class HeroPromoCard {
  final String title;
  final String badgeLabel;
  final List<String> imageUrls;
  final VoidCallback? onTap;

  const HeroPromoCard({
    required this.title,
    this.badgeLabel = '',
    this.imageUrls = const [],
    this.onTap,
  });
}

class _SmallCard extends StatelessWidget {
  final HeroPromoCard card;
  final BoxDecoration decoration;
  final Color cardTitleColor;
  final HeroCardStyle cardStyle;
  const _SmallCard({
    required this.card,
    required this.decoration,
    required this.cardTitleColor,
    required this.cardStyle,
  });

  @override
  Widget build(BuildContext context) {
    final tablet = isTablet(context);
    final cardHeight = tablet ? 150.h : 90.h;

    return GestureDetector(
      onTap: card.onTap,
      behavior: HitTestBehavior.opaque,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12.r),
        child: Container(
          height: cardHeight,
          decoration: decoration,
          child: cardStyle == HeroCardStyle.full
              ? _fullImage()
              : _imageTitle(context),
        ),
      ),
    );
  }

  Widget _fullImage() {
    if (card.imageUrls.isEmpty) {
      return Center(
        child: Icon(
          Icons.local_drink_rounded,
          size: 28.r,
          color: Colors.white70,
        ),
      );
    }
    return CustomImageContainer(
      imagePath: card.imageUrls.first,
      width: double.infinity,
      height: double.infinity,
      fit: BoxFit.cover,
    );
  }

  Widget _imageTitle(BuildContext context) {
    final tablet = isTablet(context);
    final imgHeight = tablet ? 100.h : 55.h;

    return Stack(
      fit: StackFit.expand,
      children: [
        // Product images
        Positioned(
          left: 0,
          right: 0,
          bottom: tablet ? 4.h : -10.h,
          child: SizedBox(
            height: imgHeight,
            child: Row(
              children: [
                for (final url in card.imageUrls)
                  Expanded(
                    child: CustomImageContainer(
                      imagePath: url,
                      height: imgHeight,
                      fit: BoxFit.contain,
                    ),
                  ),
              ],
            ),
          ),
        ),

        // Title
        Positioned(
          top: tablet ? 8.h : 6.h,
          left: tablet ? 8.w : 16.w,
          right: tablet ? 8.w : 16.w,
          child: Text(
            card.title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: (tablet
                ? AppTextStyles.bodySmall
                : AppTextStyles.label)
                .copyWith(
              color: cardTitleColor,
              height: 1.1,
              fontWeight:
              tablet ? FontWeight.w600 : FontWeight.w400,
              letterSpacing: 0.2,
            ),
            textAlign: TextAlign.center,
          ),
        ),
      ],
    );
  }
}