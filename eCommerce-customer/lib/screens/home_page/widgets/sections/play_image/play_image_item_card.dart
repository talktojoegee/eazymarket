import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/screens/home_page/model/home_layout_models.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';

class PlayImageItemCard extends StatefulWidget {
  final PlayImageItem item;
  final double borderRadius;
  final void Function(PlayImageItem item)? onTap;

  const PlayImageItemCard({
    super.key,
    required this.item,
    this.borderRadius = 0,
    this.onTap,
  });

  @override
  State<PlayImageItemCard> createState() => _PlayImageItemCardState();
}

class _PlayImageItemCardState extends State<PlayImageItemCard>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  Widget? _cachedImageWidget;
  String? _lastImageUrl;
  double? _lastAspectRatio;
  double? _lastBorderRadius;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _rebuildWidgetIfNeeded();
  }

  @override
  void didUpdateWidget(covariant PlayImageItemCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    _rebuildWidgetIfNeeded();
  }

  void _rebuildWidgetIfNeeded() {
    final isTablet = MediaQuery.of(context).size.shortestSide >= 600;
    final imageUrl = (!isTablet && (widget.item.mobileImage?.isNotEmpty ?? false))
        ? widget.item.mobileImage!
        : widget.item.image;

    final parsedAspectRatio = widget.item.config.parsedAspectRatio;
    final borderRadius = widget.borderRadius;

    if (_cachedImageWidget != null &&
        imageUrl == _lastImageUrl &&
        parsedAspectRatio == _lastAspectRatio &&
        borderRadius == _lastBorderRadius) {
      return;
    }

    _lastImageUrl = imageUrl;
    _lastAspectRatio = parsedAspectRatio;
    _lastBorderRadius = borderRadius;

    if (imageUrl.isEmpty) {
      _cachedImageWidget = const SizedBox.shrink();
      return;
    }

    Widget imageWidget = CustomImageContainer(
      imagePath: imageUrl,
      fit: parsedAspectRatio != null ? BoxFit.cover : BoxFit.fitWidth,
      fadeInDuration: Duration.zero,
      fadeOutDuration: Duration.zero,
      gaplessPlayback: true,
    );

    if (parsedAspectRatio != null) {
      imageWidget = AspectRatio(
        aspectRatio: parsedAspectRatio,
        child: imageWidget,
      );
    }

    _cachedImageWidget = ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius.r),
      child: imageWidget,
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    if (_cachedImageWidget == null) {
      _rebuildWidgetIfNeeded();
    }

    return GestureDetector(
      onTap: () => widget.onTap?.call(widget.item),
      behavior: HitTestBehavior.opaque,
      child: _cachedImageWidget ?? const SizedBox.shrink(),
    );
  }
}
