import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/product_video_player.dart';
import 'package:photo_view/photo_view.dart';

class ImageSliderPage extends StatefulWidget {
  final List<String> images;
  final String? videoUrl;
  final int initialIndex;

  const ImageSliderPage({
    super.key,
    required this.images,
    this.videoUrl,
    this.initialIndex = 0,
  });

  @override
  State<ImageSliderPage> createState() => _ImageSliderPageState();
}

class _ImageSliderPageState extends State<ImageSliderPage> {
  late int currentIndex;
  late PageController _pageController;
  late ScrollController _thumbScrollController;
  late int totalItems;
  late bool hasVideo;
  bool _showOverlay = true;

  @override
  void initState() {
    super.initState();
    hasVideo = widget.videoUrl != null && widget.videoUrl!.isNotEmpty;
    totalItems = hasVideo ? widget.images.length + 1 : widget.images.length;
    currentIndex = widget.initialIndex;
    _pageController = PageController(initialPage: widget.initialIndex);
    _thumbScrollController = ScrollController();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _scrollToThumb(currentIndex);
    });
  }

  @override
  void dispose() {
    _pageController.dispose();
    _thumbScrollController.dispose();
    super.dispose();
  }

  void _scrollToThumb(int index) {
    if (!_thumbScrollController.hasClients) return;
    final screenWidth = MediaQuery.of(context).size.width;
    final itemExtent = 60.w;
    final target = (index * itemExtent) - (screenWidth / 2) + (itemExtent / 2);
    final maxScroll = _thumbScrollController.position.maxScrollExtent;
    _thumbScrollController.animateTo(
      target.clamp(0.0, maxScroll),
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeOutCubic,
    );
  }

  void _onPageChanged(int index) {
    setState(() {
      currentIndex = index;
    });
    _scrollToThumb(index);
  }

  void _toggleOverlay() {
    setState(() {
      _showOverlay = !_showOverlay;
    });
  }

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top;

    return Scaffold(
      backgroundColor: const Color(0xFF0D0E11),
      body: Stack(
        children: [
          // Main Gallery (Video + Photos)
          PageView.builder(
            controller: _pageController,
            onPageChanged: _onPageChanged,
            itemCount: totalItems,
            itemBuilder: (context, index) {
              // Video slide at index 0 if available
              if (index == 0 && hasVideo) {
                return Center(
                  child: ProductVideoPlayer(
                    videoUrl: widget.videoUrl!,
                    isActive: currentIndex == 0,
                    isFullScreen: true,
                  ),
                );
              }

              final imageIndex = hasVideo ? index - 1 : index;
              final imageUrl = widget.images[imageIndex];

              return GestureDetector(
                onTap: _toggleOverlay,
                child: PhotoView(
                  imageProvider: CachedNetworkImageProvider(imageUrl),
                  initialScale: PhotoViewComputedScale.contained,
                  minScale: PhotoViewComputedScale.contained,
                  maxScale: PhotoViewComputedScale.covered * 3.5,
                  heroAttributes: PhotoViewHeroAttributes(tag: imageUrl),
                  backgroundDecoration: const BoxDecoration(
                    color: Color(0xFF0D0E11),
                  ),
                  loadingBuilder: (context, event) => const Center(
                    child: CircularProgressIndicator(color: Colors.white54),
                  ),
                  errorBuilder: (context, error, stackTrace) => const Center(
                    child: Icon(Icons.broken_image_rounded, color: Colors.white38, size: 48),
                  ),
                ),
              );
            },
          ),

          // Floating Top Bar (Back Button + Counter Pill)
          AnimatedPositioned(
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOutCubic,
            top: _showOverlay ? topPadding + 10.h : -70.h,
            left: 16.w,
            right: 16.w,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Close / Back Button
                GestureDetector(
                  onTap: () => Navigator.pop(context),
                  child: Container(
                    width: 38.w,
                    height: 38.w,
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.55),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.2),
                        width: 0.8,
                      ),
                    ),
                    child: Icon(Icons.close_rounded, color: Colors.white, size: 22.sp),
                  ),
                ),

                // Center Counter Pill
                Container(
                  padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 6.h),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.55),
                    borderRadius: BorderRadius.circular(20.r),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.2),
                      width: 0.8,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (hasVideo && currentIndex == 0) ...[
                        Icon(Icons.videocam_rounded, size: 14.sp, color: Colors.white),
                        SizedBox(width: 5.w),
                      ],
                      Text(
                        '${currentIndex + 1} of $totalItems',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 12.sp,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 0.3,
                        ),
                      ),
                    ],
                  ),
                ),

                // Symmetrical Placeholder
                SizedBox(width: 38.w),
              ],
            ),
          ),

          // Floating Bottom Filmstrip Thumbnail Tray
          AnimatedPositioned(
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOutCubic,
            bottom: _showOverlay ? 24.h : -100.h,
            left: 0,
            right: 0,
            child: SizedBox(
              height: 64.h,
              child: Center(
                child: ListView.builder(
                  controller: _thumbScrollController,
                  scrollDirection: Axis.horizontal,
                  shrinkWrap: true,
                  itemCount: totalItems,
                  padding: EdgeInsets.symmetric(horizontal: 16.w),
                  physics: const BouncingScrollPhysics(),
                  itemBuilder: (context, index) {
                    final bool isCurrent = index == currentIndex;

                    return GestureDetector(
                      onTap: () {
                        _pageController.animateToPage(
                          index,
                          duration: const Duration(milliseconds: 300),
                          curve: Curves.easeOutCubic,
                        );
                      },
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 250),
                        curve: Curves.easeOutCubic,
                        width: 56.w,
                        height: 56.h,
                        margin: EdgeInsets.symmetric(horizontal: 4.w),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(12.r),
                          border: Border.all(
                            color: isCurrent ? AppTheme.primaryColor : Colors.white.withValues(alpha: 0.2),
                            width: isCurrent ? 2 : 1,
                          ),
                          boxShadow: isCurrent
                              ? [
                                  BoxShadow(
                                    color: AppTheme.primaryColor.withValues(alpha: 0.35),
                                    blurRadius: 8,
                                    offset: const Offset(0, 2),
                                  ),
                                ]
                              : null,
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(11.r),
                          child: Opacity(
                            opacity: isCurrent ? 1.0 : 0.6,
                            child: _buildThumbnail(index),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildThumbnail(int index) {
    if (index == 0 && hasVideo) {
      return Container(
        color: const Color(0xFF1E2024),
        child: Center(
          child: Icon(
            Icons.play_circle_fill_rounded,
            color: Colors.white,
            size: 24.sp,
          ),
        ),
      );
    }

    final imageIndex = hasVideo ? index - 1 : index;

    return CachedNetworkImage(
      imageUrl: widget.images[imageIndex],
      fit: BoxFit.cover,
      placeholder: (context, url) => Container(
        color: Colors.white10,
        child: const Center(
          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white30),
        ),
      ),
      errorWidget: (context, url, error) => Container(
        color: Colors.white10,
        child: const Icon(Icons.broken_image_rounded, color: Colors.white30, size: 20),
      ),
    );
  }
}