import 'dart:async';
import 'dart:developer';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/screens/product_detail_page/widgets/video_player_manager.dart';
import 'package:video_player/video_player.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

class ProductVideoPlayer extends StatefulWidget {
  final String videoUrl;
  final bool isActive;
  final VoidCallback? onExpand;
  final bool isFullScreen;

  const ProductVideoPlayer({
    super.key,
    required this.videoUrl,
    required this.isActive,
    this.onExpand,
    this.isFullScreen = false,
  });

  @override
  State<ProductVideoPlayer> createState() => _ProductVideoPlayerState();
}

class _ProductVideoPlayerState extends State<ProductVideoPlayer> with AutomaticKeepAliveClientMixin {
  VideoPlayerController? _videoController;
  YoutubePlayerController? _youtubeController;
  bool _isYouTube = false;
  bool _isInitialized = false;

  bool _isPlaying = true;
  bool _isMuted = false;
  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;
  bool _isDragging = false;
  double _dragProgress = 0.0;
  bool _showControls = true;
  Timer? _hideControlsTimer;
  Timer? _ytProgressTimer;

  final _manager = VideoPlayerManager();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _initializePlayer();
  }

  @override
  void didUpdateWidget(ProductVideoPlayer oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (oldWidget.videoUrl != widget.videoUrl) {
      _disposeControllers();
      _initializePlayer();
    }

    if (oldWidget.isActive != widget.isActive) {
      _handleActiveStateChange();
    }
  }

  void _initializePlayer() {
    final videoId = YoutubePlayerController.convertUrlToId(widget.videoUrl);

    if (videoId != null) {
      _isYouTube = true;
      _youtubeController = YoutubePlayerController.fromVideoId(
        videoId: videoId,
        autoPlay: true,
        params: const YoutubePlayerParams(
          mute: false,
          loop: true,
          showControls: false,
          showFullscreenButton: false,
          enableCaption: false,
        ),
      );

      _manager.youtubeController = _youtubeController;

      setState(() {
        _isInitialized = true;
        _isPlaying = true;
        _isMuted = false;
      });

      _startYouTubeTimer();
      _startHideControlsTimer();
    } else {
      _isYouTube = false;
      _videoController = VideoPlayerController.networkUrl(
        Uri.parse(widget.videoUrl),
      )..initialize().then((_) {
        _manager.videoPlayerController = _videoController;
        _manager.play();
        _manager.videoPlayerController!.setLooping(true);
        if (mounted) {
          setState(() {
            _isInitialized = true;
            _duration = _videoController!.value.duration;
            _isPlaying = true;
            _isMuted = false;
          });
          _startHideControlsTimer();
        }
      }).catchError((error) {
        log('Video initialization error: $error');
      });

      _videoController!.addListener(_onVideoControllerUpdate);
    }
  }

  void _onVideoControllerUpdate() {
    if (!mounted || _videoController == null) return;
    final val = _videoController!.value;
    if (!_isDragging) {
      setState(() {
        _position = val.position;
        _duration = val.duration;
        _isPlaying = val.isPlaying;
        _isMuted = val.volume == 0;
      });
    }
  }

  void _startYouTubeTimer() {
    _ytProgressTimer?.cancel();
    _ytProgressTimer = Timer.periodic(const Duration(milliseconds: 500), (_) async {
      if (!mounted || _youtubeController == null || !_isYouTube) return;
      try {
        final cur = await _youtubeController!.currentTime;
        final dur = await _youtubeController!.duration;
        if (mounted && !_isDragging) {
          setState(() {
            _position = Duration(milliseconds: (cur * 1000).toInt());
            if (dur > 0) {
              _duration = Duration(milliseconds: (dur * 1000).toInt());
            }
          });
        }
      } catch (_) {}
    });
  }

  void _handleActiveStateChange() {
    if (!mounted) return;

    if (widget.isActive) {
      if (_isYouTube) {
        _youtubeController?.playVideo();
        setState(() => _isPlaying = true);
        _startHideControlsTimer();
      } else {
        _videoController?.play();
        setState(() => _isPlaying = true);
        _startHideControlsTimer();
      }
    } else {
      if (_isYouTube) {
        _youtubeController?.pauseVideo();
        setState(() => _isPlaying = false);
      } else {
        _videoController?.pause();
        setState(() => _isPlaying = false);
      }
      _hideControlsTimer?.cancel();
    }
  }

  void _togglePlayPause() {
    if (_isYouTube) {
      if (_isPlaying) {
        _youtubeController?.pauseVideo();
        setState(() => _isPlaying = false);
        _hideControlsTimer?.cancel();
      } else {
        _youtubeController?.playVideo();
        setState(() => _isPlaying = true);
        _startHideControlsTimer();
      }
    } else {
      if (_videoController == null) return;
      if (_videoController!.value.isPlaying) {
        _videoController!.pause();
        setState(() => _isPlaying = false);
        _hideControlsTimer?.cancel();
      } else {
        _videoController!.play();
        setState(() => _isPlaying = true);
        _startHideControlsTimer();
      }
    }
  }

  void _toggleMute() {
    if (_isYouTube) {
      if (_isMuted) {
        _youtubeController?.unMute();
        setState(() => _isMuted = false);
      } else {
        _youtubeController?.mute();
        setState(() => _isMuted = true);
      }
    } else {
      if (_videoController == null) return;
      if (_isMuted) {
        _videoController!.setVolume(1.0);
        setState(() => _isMuted = false);
      } else {
        _videoController!.setVolume(0.0);
        setState(() => _isMuted = true);
      }
    }
    _resetHideTimer();
  }

  void _seekToProgress(double progress) {
    if (_duration.inMilliseconds == 0) return;
    final targetMs = (progress * _duration.inMilliseconds).toInt();
    final targetDuration = Duration(milliseconds: targetMs);
    setState(() => _position = targetDuration);

    if (_isYouTube) {
      final seconds = targetMs / 1000.0;
      _youtubeController?.seekTo(seconds: seconds, allowSeekAhead: true);
    } else {
      _videoController?.seekTo(targetDuration);
    }
    _resetHideTimer();
  }

  void _startHideControlsTimer() {
    _hideControlsTimer?.cancel();
    if (_isPlaying) {
      _hideControlsTimer = Timer(const Duration(milliseconds: 2800), () {
        if (mounted && _isPlaying) {
          setState(() => _showControls = false);
        }
      });
    }
  }

  void _resetHideTimer() {
    if (!_showControls) {
      setState(() => _showControls = true);
    }
    _startHideControlsTimer();
  }

  void _toggleControls() {
    setState(() => _showControls = !_showControls);
    if (_showControls) {
      _startHideControlsTimer();
    } else {
      _hideControlsTimer?.cancel();
    }
  }

  void _disposeControllers() {
    _hideControlsTimer?.cancel();
    _ytProgressTimer?.cancel();
    if (_manager.youtubeController == _youtubeController) {
      _manager.youtubeController = null;
    }
    if (_manager.videoPlayerController == _videoController) {
      _manager.videoPlayerController = null;
    }
    _youtubeController?.close();
    _videoController?.removeListener(_onVideoControllerUpdate);
    _videoController?.dispose();
    _youtubeController = null;
    _videoController = null;
  }

  @override
  void dispose() {
    _disposeControllers();
    super.dispose();
  }

  String _formatDuration(Duration d) {
    final minutes = d.inMinutes.remainder(60).toString();
    final seconds = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    if (!_isInitialized) {
      return Container(
        color: const Color(0xFF0F0F11),
        child: const Center(
          child: CircularProgressIndicator(color: Colors.white70),
        ),
      );
    }

    Widget videoWidget;
    if (_isYouTube && _youtubeController != null) {
      videoWidget = Center(
        child: AspectRatio(
          aspectRatio: 16 / 9,
          child: IgnorePointer(
            ignoring: true,
            child: YoutubePlayer(controller: _youtubeController!),
          ),
        ),
      );
    } else if (_videoController != null && _videoController!.value.isInitialized) {
      videoWidget = Center(
        child: AspectRatio(
          aspectRatio: _videoController!.value.aspectRatio,
          child: VideoPlayer(_videoController!),
        ),
      );
    } else {
      return Container(
        color: const Color(0xFF0F0F11),
        child: const Center(
          child: Icon(Icons.error_outline_rounded, color: Colors.white, size: 48),
        ),
      );
    }

    final double progress = _isDragging
        ? _dragProgress
        : (_duration.inMilliseconds > 0
            ? (_position.inMilliseconds / _duration.inMilliseconds).clamp(0.0, 1.0)
            : 0.0);

    return ClipRRect(
      borderRadius: widget.isFullScreen ? BorderRadius.zero : BorderRadius.circular(0.r),
      child: Container(
        color: const Color(0xFF0F0F11),
        child: Stack(
          alignment: Alignment.center,
          fit: StackFit.expand,
          children: [
            // Video Frame
            videoWidget,

            // Gesture Detector to toggle controls
            Positioned.fill(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: _toggleControls,
                child: const SizedBox.expand(),
              ),
            ),

            // Top Bar Overlay (Video Badge & Mute Toggle)
            AnimatedPositioned(
              duration: const Duration(milliseconds: 250),
              curve: Curves.easeOutCubic,
              top: _showControls ? 12.h : -50.h,
              left: 12.w,
              right: 12.w,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  // Video Badge
                  Container(
                    padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 5.h),
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
                        Icon(Icons.videocam_rounded, size: 14.sp, color: Colors.white),
                        SizedBox(width: 5.w),
                        Text(
                          'Video',
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

                  // Mute / Unmute Button
                  GestureDetector(
                    onTap: _toggleMute,
                    child: Container(
                      padding: EdgeInsets.all(7.w),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.55),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.2),
                          width: 0.8,
                        ),
                      ),
                      child: Icon(
                        _isMuted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
                        color: Colors.white,
                        size: 18.sp,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // Center Play/Pause Indicator
            Center(
              child: AnimatedOpacity(
                duration: const Duration(milliseconds: 200),
                opacity: _showControls || !_isPlaying ? 1.0 : 0.0,
                child: GestureDetector(
                  onTap: _togglePlayPause,
                  child: Container(
                    width: 52.w,
                    height: 52.w,
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.55),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.3),
                        width: 1.5,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.35),
                          blurRadius: 12,
                        ),
                      ],
                    ),
                    child: Icon(
                      _isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                      color: Colors.white,
                      size: 30.sp,
                    ),
                  ),
                ),
              ),
            ),

            // Bottom Control Bar (Time, Scrubber, Fullscreen)
            AnimatedPositioned(
              duration: const Duration(milliseconds: 250),
              curve: Curves.easeOutCubic,
              bottom: _showControls ? 0 : -60.h,
              left: 0,
              right: 0,
              child: Container(
                padding: EdgeInsets.fromLTRB(14.w, 14.h, 14.w, 10.h),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.bottomCenter,
                    end: Alignment.topCenter,
                    colors: [
                      Colors.black.withValues(alpha: 0.75),
                      Colors.transparent,
                    ],
                  ),
                ),
                child: Row(
                  children: [
                    Text(
                      _formatDuration(_isDragging ? Duration(milliseconds: (progress * _duration.inMilliseconds).toInt()) : _position),
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 11.sp,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Expanded(
                      child: SliderTheme(
                        data: SliderThemeData(
                          trackHeight: 3.h,
                          thumbShape: RoundSliderThumbShape(enabledThumbRadius: 6.r),
                          overlayShape: RoundSliderOverlayShape(overlayRadius: 12.r),
                          activeTrackColor: AppTheme.primaryColor,
                          inactiveTrackColor: Colors.white.withValues(alpha: 0.3),
                          thumbColor: Colors.white,
                          overlayColor: AppTheme.primaryColor.withValues(alpha: 0.25),
                        ),
                        child: Slider(
                          value: progress.clamp(0.0, 1.0),
                          onChanged: (val) {
                            setState(() {
                              _isDragging = true;
                              _dragProgress = val;
                            });
                          },
                          onChangeEnd: (val) {
                            _isDragging = false;
                            _seekToProgress(val);
                          },
                        ),
                      ),
                    ),
                    Text(
                      _formatDuration(_duration),
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.8),
                        fontSize: 11.sp,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    if (widget.onExpand != null && !widget.isFullScreen) ...[
                      SizedBox(width: 8.w),
                      GestureDetector(
                        onTap: widget.onExpand,
                        child: Container(
                          padding: EdgeInsets.all(5.w),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(6.r),
                          ),
                          child: Icon(
                            Icons.fullscreen_rounded,
                            color: Colors.white,
                            size: 18.sp,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}