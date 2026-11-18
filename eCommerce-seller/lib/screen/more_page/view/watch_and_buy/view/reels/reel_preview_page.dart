// ignore_for_file: deprecated_member_use

import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';
import '../../model/watch_and_buy_reel_model.dart';

class ReelPreviewPage extends StatefulWidget {
  final WatchAndBuyReelModel reel;

  const ReelPreviewPage({
    super.key,
    required this.reel,
  });

  @override
  State<ReelPreviewPage> createState() => _ReelPreviewPageState();
}

class _ReelPreviewPageState extends State<ReelPreviewPage> {
  VideoPlayerController? _controller;
  bool _isPlaying = true;
  bool _isError = false;
  bool _isScrubbing = false;
  double _scrubValue = 0.0;

  String _formatDuration(Duration duration) {
    final minutes = duration.inMinutes;
    final seconds = duration.inSeconds % 60;
    return '$minutes:${seconds.toString().padLeft(2, '0')}';
  }

  @override
  void initState() {
    super.initState();
    _initializePlayer();
  }

  Future<void> _initializePlayer() async {
    if (widget.reel.videoUrl == null || widget.reel.videoUrl!.isEmpty) {
      setState(() => _isError = true);
      return;
    }

    try {
      _controller = VideoPlayerController.networkUrl(Uri.parse(widget.reel.videoUrl!));
      await _controller!.initialize();
      _controller!.setLooping(true);
      await _controller!.play();
      if (mounted) {
        setState(() {
          _isPlaying = true;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isError = true);
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  void _togglePlayPause() {
    if (_controller == null || !_controller!.value.isInitialized) return;
    setState(() {
      if (_controller!.value.isPlaying) {
        _controller!.pause();
        _isPlaying = false;
      } else {
        _controller!.play();
        _isPlaying = true;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Video Player or Error
          if (_isError)
            const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.error_outline, color: Colors.white70, size: 48),
                  SizedBox(height: 12),
                  Text('Failed to load video', style: TextStyle(color: Colors.white70)),
                ],
              ),
            )
          else if (_controller != null && _controller!.value.isInitialized)
            GestureDetector(
              onTap: _togglePlayPause,
              child: Center(
                child: AspectRatio(
                  aspectRatio: _controller!.value.aspectRatio,
                  child: VideoPlayer(_controller!),
                ),
              ),
            )
          else
            const Center(
              child: CircularProgressIndicator(color: Colors.white),
            ),

          // Play / Pause Icon overlay animation
          if (!_isPlaying && _controller != null && _controller!.value.isInitialized)
            Center(
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.black54,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.play_arrow, size: 48, color: Colors.white),
              ),
            ),

          // Top Header Bar
          Positioned(
            top: MediaQuery.of(context).padding.top + 8,
            left: 8,
            right: 8,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.white),
                  onPressed: () => Navigator.pop(context),
                ),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Duration Badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                      margin: const EdgeInsets.only(right: 8),
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.55),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.white24, width: 0.8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.timer_outlined, color: Colors.white70, size: 13),
                          const SizedBox(width: 4),
                          Text(
                            _controller != null && _controller!.value.isInitialized
                                ? _formatDuration(_controller!.value.duration)
                                : widget.reel.formattedDuration,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),

                    // Status Badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: widget.reel.isPublished
                            ? Colors.green.withOpacity(0.85)
                            : Colors.orange.withOpacity(0.85),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        widget.reel.status?.toUpperCase() ?? 'DRAFT',
                        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // Bottom Content & Product Overlay
          Positioned(
            bottom: MediaQuery.of(context).padding.bottom + 16,
            left: 16,
            right: 16,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                // Video Progress Bar & Duration



                // Likes & stats
                Row(
                  children: [
                    const Icon(Icons.favorite, color: Colors.red, size: 20),
                    const SizedBox(width: 6),
                    Text(
                      '${widget.reel.likeCount} likes',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ],
                ),
                const SizedBox(height: 8),

                // Caption
                if (widget.reel.caption != null && widget.reel.caption!.isNotEmpty)
                  Text(
                    widget.reel.caption!,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                  ),
                const SizedBox(height: 12),

                // Tagged Products Carousel / Chips
                if (widget.reel.products.isNotEmpty) ...[
                  SizedBox(
                    height: 56,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: widget.reel.products.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 8),
                      itemBuilder: (context, index) {
                        final product = widget.reel.products[index];
                        final isPrimary = widget.reel.primaryAttachmentId == product.id;

                        return Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(8),
                            // border: Border.all(
                            //   color: isPrimary ? AppColors.primaryColor : Colors.white24,
                            //   width: isPrimary ? 1.5 : 1,
                            // ),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              ClipRRect(
                                borderRadius: BorderRadius.circular(4),
                                child: product.productImage != null && product.productImage!.isNotEmpty
                                    ? CustomNetworkImage(
                                        imageUrl: product.productImage!,
                                        width: 38,
                                        height: 38,
                                        fit: BoxFit.cover,
                                      )
                                    : Container(
                                        width: 38,
                                        height: 38,
                                        color: Colors.grey.shade700,
                                        child: const Icon(Icons.image, size: 16, color: Colors.white70),
                                      ),
                              ),
                              const SizedBox(width: 8),
                              ConstrainedBox(
                                constraints: const BoxConstraints(maxWidth: 120),
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      product.productTitle,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(color: Colors.black, fontSize: 12, fontWeight: FontWeight.w600),
                                    ),
                                    Row(
                                      children: [
                                        if (isPrimary) ...[
                                          const Text(
                                            'Primary • ',
                                            style: TextStyle(color: AppColors.primaryColor, fontSize: 10, fontWeight: FontWeight.bold),
                                          ),
                                        ],
                                        Text(
                                          product.formattedPrice,
                                          style: const TextStyle(color: Colors.black54, fontSize: 10, fontWeight: FontWeight.w600),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 12),
                ],


                _buildProgressBar(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProgressBar() {
    if (_controller == null || !_controller!.value.isInitialized) {
      return const SizedBox.shrink();
    }

    return ValueListenableBuilder<VideoPlayerValue>(
      valueListenable: _controller!,
      builder: (context, value, child) {
        final totalDuration = value.duration.inMilliseconds > 0
            ? value.duration
            : Duration(milliseconds: widget.reel.durationMs ?? 0);
        final currentPosition = _isScrubbing
            ? Duration(milliseconds: _scrubValue.toInt())
            : value.position;

        final maxMs = totalDuration.inMilliseconds.toDouble();
        final currentMs = currentPosition.inMilliseconds.toDouble().clamp(0.0, maxMs > 0 ? maxMs : 1.0);

        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: Colors.black.withOpacity(0.45),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              // Play / Pause toggle button
              GestureDetector(
                onTap: _togglePlayPause,
                child: Icon(
                  _isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                  color: Colors.white,
                  size: 22,
                ),
              ),
              const SizedBox(width: 8),

              // Current Position Text
              Text(
                _formatDuration(currentPosition),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(width: 4),

              // Interactive Scrubbing Slider
              Expanded(
                child: SliderTheme(
                  data: SliderTheme.of(context).copyWith(
                    trackHeight: 3.5,
                    thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 5.5),
                    overlayShape: const RoundSliderOverlayShape(overlayRadius: 12.0),
                    activeTrackColor: AppColors.primaryColor,
                    inactiveTrackColor: Colors.white.withOpacity(0.25),
                    thumbColor: Colors.white,
                    overlayColor: AppColors.primaryColor.withOpacity(0.2),
                  ),
                  child: Slider(
                    value: currentMs,
                    min: 0.0,
                    max: maxMs > 0 ? maxMs : 1.0,
                    onChangeStart: (val) {
                      setState(() {
                        _isScrubbing = true;
                        _scrubValue = val;
                      });
                    },
                    onChanged: (val) {
                      setState(() {
                        _scrubValue = val;
                      });
                    },
                    onChangeEnd: (val) {
                      _controller?.seekTo(Duration(milliseconds: val.toInt()));
                      setState(() {
                        _isScrubbing = false;
                      });
                    },
                  ),
                ),
              ),
              const SizedBox(width: 4),

              // Total Duration Text
              Text(
                _formatDuration(totalDuration),
                style: TextStyle(
                  color: Colors.white.withOpacity(0.75),
                  fontSize: 11.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
