import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:hyper_commerce/screens/watch_and_buy_page/model/watch_and_buy_model.dart';
import 'package:hyper_commerce/screens/watch_and_buy_page/services/watch_video_preload_service.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';
import 'package:video_player/video_player.dart';
import 'package:visibility_detector/visibility_detector.dart';

class ProductReelWidget extends StatefulWidget {
  final List<WatchAndBuyItem> reels;

  const ProductReelWidget({
    super.key,
    required this.reels,
  });

  @override
  State<ProductReelWidget> createState() => _ProductReelWidgetState();
}

class _ProductReelWidgetState extends State<ProductReelWidget>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver, RouteAware {
  late PageController _pageController;
  int _currentIndex = 0;

  VideoPlayerController? _controller;
  bool _isInitialized = false;
  bool _isPlaying = true;
  bool _isMuted = true;
  bool _isDismissed = false;
  bool _isPageVisible = true;
  String? _activeVideoUrl;

  late AnimationController _entranceController;
  late Animation<double> _scaleAnimation;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    _pageController = PageController();

    _entranceController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 350),
    );
    _scaleAnimation = CurvedAnimation(
      parent: _entranceController,
      curve: Curves.easeOutBack,
    );
    _entranceController.forward();

    _setupVideoController();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final modalRoute = ModalRoute.of(context);
    if (modalRoute != null) {
      MyAppRoute.routeObserver.subscribe(this, modalRoute);
    }
  }

  @override
  void didPushNext() {
    // Pauses video when user navigates to any other page from product detail page
    _pauseVideo();
    super.didPushNext();
  }

  @override
  void didPopNext() {
    // Resumes playback if user returns back to product detail page and it wasn't paused/dismissed
    if (!_isDismissed && _isPlaying && _isPageVisible) {
      _playVideo();
    }
    super.didPopNext();
  }

  @override
  void didPop() {
    // Pauses when product detail page is popped
    _pauseVideo();
    super.didPop();
  }

  @override
  void didUpdateWidget(covariant ProductReelWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_currentIndex >= widget.reels.length) {
      _currentIndex = 0;
      _cleanupController();
      _setupVideoController();
    } else {
      final currentUrl = _currentReelUrl();
      if (currentUrl != _activeVideoUrl) {
        _cleanupController();
        _setupVideoController();
      }
    }
  }

  String _currentReelUrl() {
    if (widget.reels.isEmpty || _currentIndex >= widget.reels.length) return '';
    return widget.reels[_currentIndex].videoUrl.trim();
  }

  void _setupVideoController() {
    final url = _currentReelUrl();
    if (url.isEmpty) return;

    _activeVideoUrl = url;
    WatchVideoPreloadService().retain(url);

    try {
      _controller = WatchVideoPreloadService().getOrCreateController(
        url,
        isMuted: _isMuted,
        isLooping: true,
      );

      if (_controller!.value.isInitialized) {
        _isInitialized = true;
        _controller!.setVolume(_isMuted ? 0.0 : 1.0);
        _controller!.setLooping(true);
        if (_isPlaying && _isPageVisible) {
          _controller!.play();
        } else {
          _controller!.pause();
        }
      } else {
        _controller!.addListener(_onControllerUpdate);
      }
    } catch (_) {}

    if (mounted) setState(() {});
  }

  void _onControllerUpdate() {
    if (_controller != null &&
        _controller!.value.isInitialized &&
        !_isInitialized &&
        mounted) {
      setState(() {
        _isInitialized = true;
      });
      _controller!.setVolume(_isMuted ? 0.0 : 1.0);
      _controller!.setLooping(true);
      if (_isPlaying && _isPageVisible) {
        _controller!.play();
      }
    }
  }

  void _cleanupController() {
    if (_activeVideoUrl != null && _activeVideoUrl!.isNotEmpty) {
      WatchVideoPreloadService().release(_activeVideoUrl!);
    }
    if (_controller != null) {
      _controller!.removeListener(_onControllerUpdate);
      _controller!.pause();
      _controller = null;
    }
    _isInitialized = false;
    _activeVideoUrl = null;
  }

  void _onPageChanged(int index) {
    if (_currentIndex == index) return;
    setState(() {
      _currentIndex = index;
    });
    _cleanupController();
    _setupVideoController();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      _pauseVideo();
    } else if (state == AppLifecycleState.resumed &&
        !_isDismissed &&
        _isPlaying &&
        _isPageVisible) {
      _playVideo();
    }
  }

  void _playVideo() {
    try {
      if (_controller != null && _controller!.value.isInitialized) {
        _controller!.play();
        if (!_isPlaying && mounted) {
          setState(() {
            _isPlaying = true;
          });
        }
      }
    } catch (_) {}
  }

  void _pauseVideo() {
    try {
      if (_controller != null && _controller!.value.isInitialized) {
        _controller!.pause();
        if (_isPlaying && mounted) {
          setState(() {
            _isPlaying = false;
          });
        }
      }
    } catch (_) {}
  }

  void _togglePlayPause() {
    setState(() {
      _isPlaying = !_isPlaying;
    });
    if (_isPlaying) {
      _playVideo();
    } else {
      _pauseVideo();
    }
  }

  void _toggleMute() {
    setState(() {
      _isMuted = !_isMuted;
    });
    try {
      _controller?.setVolume(_isMuted ? 0.0 : 1.0);
    } catch (_) {}
  }

  void _openWatchAndBuyPlayer(BuildContext context, WatchAndBuyItem reel, int index) {
    _pauseVideo();
    context.push(
      AppRoutes.watchAndBuyPlayer,
      extra: {
        'initialItem': reel,
        'shortsSlug': reel.slug,
        'initialIndex': index,
      },
    ).then((_) {
      if (mounted && !_isDismissed && _isPlaying && _isPageVisible) {
        _playVideo();
      }
    });
  }

  @override
  void dispose() {
    MyAppRoute.routeObserver.unsubscribe(this);
    WidgetsBinding.instance.removeObserver(this);
    _pageController.dispose();
    _entranceController.dispose();
    _cleanupController();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_isDismissed || widget.reels.isEmpty) {
      return const SizedBox.shrink();
    }

    final int reelId = widget.reels.isNotEmpty ? widget.reels.first.id : 0;

    return VisibilityDetector(
      key: ValueKey('product_reel_visibility_$reelId'),
      onVisibilityChanged: (info) {
        if (!mounted) return;
        final bool visible = info.visibleFraction > 0.1;
        if (_isPageVisible != visible) {
          _isPageVisible = visible;
          if (!visible) {
            _pauseVideo();
          } else if (_isPlaying && !_isDismissed) {
            _playVideo();
          }
        }
      },
      child: ScaleTransition(
        scale: _scaleAnimation,
        alignment: Alignment.bottomRight,
        child: Container(
          width: 80.w,
          height: 120.h,
          decoration: BoxDecoration(
            color: Colors.black,
            borderRadius: BorderRadius.circular(12.r),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.6),
              width: 1.2,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.35),
                blurRadius: 10,
                spreadRadius: 2,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(11.r),
            child: Stack(
              fit: StackFit.expand,
              children: [
                // 1. Horizontal Swipeable Reel Video Slider
                PageView.builder(
                  controller: _pageController,
                  itemCount: widget.reels.length,
                  onPageChanged: _onPageChanged,
                  itemBuilder: (context, index) {
                    final reel = widget.reels[index];
                    final String imageUrl = (reel.previewUrl != null && reel.previewUrl!.trim().isNotEmpty)
                        ? reel.previewUrl!
                        : (reel.coverUrl != null && reel.coverUrl!.trim().isNotEmpty)
                            ? reel.coverUrl!
                            : reel.thumbnailUrl;

                    final bool isCurrent = index == _currentIndex;

                    return GestureDetector(
                      onTap: () => _openWatchAndBuyPlayer(context, reel, index),
                      behavior: HitTestBehavior.opaque,
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          // Thumbnail image
                          if (imageUrl.isNotEmpty)
                            CustomImageContainer(
                              imagePath: imageUrl,
                              fit: BoxFit.cover,
                              borderRadius: BorderRadius.circular(11.r),
                            )
                          else
                            Container(
                              color: Colors.black87,
                              child: const Center(
                                child: Icon(Icons.videocam_outlined, color: Colors.white38),
                              ),
                            ),

                          // Active Video Player
                          if (isCurrent &&
                              _isInitialized &&
                              _controller != null &&
                              _controller!.value.isInitialized)
                            SizedBox.expand(
                              child: FittedBox(
                                fit: BoxFit.cover,
                                clipBehavior: Clip.hardEdge,
                                child: SizedBox(
                                  width: _controller!.value.size.width > 0
                                      ? _controller!.value.size.width
                                      : 720,
                                  height: _controller!.value.size.height > 0
                                      ? _controller!.value.size.height
                                      : 1280,
                                  child: VideoPlayer(_controller!),
                                ),
                              ),
                            ),
                        ],
                      ),
                    );
                  },
                ),

                // 2. Top & Bottom subtle vignette gradients
                Positioned.fill(
                  child: IgnorePointer(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.black.withValues(alpha: 0.5),
                            Colors.transparent,
                            Colors.black.withValues(alpha: 0.65),
                          ],
                          stops: const [0.0, 0.35, 1.0],
                        ),
                      ),
                    ),
                  ),
                ),

                // 3. Top Segmented Story Indicators (only if multiple reels)
                if (widget.reels.length > 1)
                  Positioned(
                    top: 5.h,
                    left: 6.w,
                    right: 22.w,
                    child: Row(
                      children: List.generate(widget.reels.length, (i) {
                        final bool isActive = i == _currentIndex;
                        return Expanded(
                          child: Container(
                            height: 2.h,
                            margin: EdgeInsets.symmetric(horizontal: 1.w),
                            decoration: BoxDecoration(
                              color: isActive
                                  ? Colors.white
                                  : Colors.white.withValues(alpha: 0.35),
                              borderRadius: BorderRadius.circular(1.r),
                            ),
                          ),
                        );
                      }),
                    ),
                  ),

                // 4. Center Paused Play Button
                if (!_isPlaying)
                  Center(
                    child: GestureDetector(
                      onTap: _togglePlayPause,
                      behavior: HitTestBehavior.opaque,
                      child: Container(
                        padding: EdgeInsets.all(7.r),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.55),
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.7),
                            width: 1.2,
                          ),
                        ),
                        child: Icon(
                          Icons.play_arrow_rounded,
                          color: Colors.white,
                          size: 20.sp,
                        ),
                      ),
                    ),
                  ),

                // 5. Dismiss button (top-right)
                Positioned(
                  top: 3.h,
                  right: 3.w,
                  child: GestureDetector(
                    onTap: () {
                      _pauseVideo();
                      setState(() {
                        _isDismissed = true;
                      });
                    },
                    behavior: HitTestBehavior.opaque,
                    child: Container(
                      padding: EdgeInsets.all(3.5.r),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.65),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.4),
                          width: 0.6,
                        ),
                      ),
                      child: Icon(
                        Icons.close_rounded,
                        size: 11.sp,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),

                // 6. Bottom Controls: strictly Play/Pause and Mute/Unmute
                Positioned(
                  bottom: 5.h,
                  left: 6.w,
                  right: 6.w,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      // Play / Pause Button
                      GestureDetector(
                        onTap: _togglePlayPause,
                        behavior: HitTestBehavior.opaque,
                        child: Container(
                          padding: EdgeInsets.all(4.r),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.65),
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: Colors.white.withValues(alpha: 0.4),
                              width: 0.6,
                            ),
                          ),
                          child: Icon(
                            _isPlaying
                                ? Icons.pause_rounded
                                : Icons.play_arrow_rounded,
                            color: Colors.white,
                            size: 12.sp,
                          ),
                        ),
                      ),

                      // Mute / Unmute Button
                      GestureDetector(
                        onTap: _toggleMute,
                        behavior: HitTestBehavior.opaque,
                        child: Container(
                          padding: EdgeInsets.all(4.r),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.65),
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: Colors.white.withValues(alpha: 0.4),
                              width: 0.6,
                            ),
                          ),
                          child: Icon(
                            _isMuted
                                ? Icons.volume_off_rounded
                                : Icons.volume_up_rounded,
                            color: Colors.white,
                            size: 12.sp,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
