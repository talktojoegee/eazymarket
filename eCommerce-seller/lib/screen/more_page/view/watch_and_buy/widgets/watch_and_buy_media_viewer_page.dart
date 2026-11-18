// ignore_for_file: deprecated_member_use

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';

class WatchAndBuyMediaViewerPage extends StatefulWidget {
  final File? videoFile;
  final String? videoUrl;
  final File? imageFile;
  final String? imageUrl;
  final String title;

  const WatchAndBuyMediaViewerPage({
    super.key,
    this.videoFile,
    this.videoUrl,
    this.imageFile,
    this.imageUrl,
    this.title = 'Media Preview',
  });

  static Future<void> showVideo(
    BuildContext context, {
    File? file,
    String? url,
    String title = 'Video Preview',
  }) {
    return Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => WatchAndBuyMediaViewerPage(
          videoFile: file,
          videoUrl: url,
          title: title,
        ),
      ),
    );
  }

  static Future<void> showImage(
    BuildContext context, {
    File? file,
    String? url,
    String title = 'Image Preview',
  }) {
    return Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => WatchAndBuyMediaViewerPage(
          imageFile: file,
          imageUrl: url,
          title: title,
        ),
      ),
    );
  }

  @override
  State<WatchAndBuyMediaViewerPage> createState() => _WatchAndBuyMediaViewerPageState();
}

class _WatchAndBuyMediaViewerPageState extends State<WatchAndBuyMediaViewerPage>
    with SingleTickerProviderStateMixin {
  VideoPlayerController? _controller;
  bool _isPlaying = true;
  bool _isLoading = true;
  bool _isError = false;

  late final TransformationController _transformationController;
  AnimationController? _animationController;
  Animation<Matrix4>? _animation;
  TapDownDetails? _doubleTapDetails;

  bool get isVideo => widget.videoFile != null || widget.videoUrl != null;

  @override
  void initState() {
    super.initState();
    _transformationController = TransformationController();
    if (isVideo) {
      _initVideo();
    } else {
      _isLoading = false;
      _animationController = AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 250),
      )..addListener(() {
          if (_animation != null) {
            _transformationController.value = _animation!.value;
          }
        });
    }
  }

  Future<void> _initVideo() async {
    try {
      if (widget.videoFile != null) {
        _controller = VideoPlayerController.file(widget.videoFile!);
      } else if (widget.videoUrl != null && widget.videoUrl!.isNotEmpty) {
        _controller = VideoPlayerController.networkUrl(Uri.parse(widget.videoUrl!));
      }

      if (_controller != null) {
        await _controller!.initialize();
        _controller!.setLooping(true);
        await _controller!.play();
        setState(() {
          _isLoading = false;
          _isPlaying = true;
        });
      } else {
        setState(() {
          _isLoading = false;
          _isError = true;
        });
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
        _isError = true;
      });
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    _transformationController.dispose();
    _animationController?.dispose();
    super.dispose();
  }

  void _handleDoubleTap() {
    if (_animationController == null) return;

    final currentMatrix = _transformationController.value;
    final isZoomed = currentMatrix != Matrix4.identity();

    Matrix4 targetMatrix;
    if (isZoomed) {
      targetMatrix = Matrix4.identity();
    } else {
      final position = _doubleTapDetails?.localPosition ?? Offset.zero;
      targetMatrix = Matrix4.identity()
        ..translate(-position.dx * 1.5, -position.dy * 1.5)
        ..scale(2.5);
    }

    _animation = Matrix4Tween(
      begin: currentMatrix,
      end: targetMatrix,
    ).animate(CurvedAnimation(
      parent: _animationController!,
      curve: Curves.easeOutCubic,
    ));

    _animationController!.forward(from: 0.0);
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
      appBar: AppBar(
        backgroundColor: Colors.black,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          widget.title,
          style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
        ),
        actions: [
          if (!isVideo)
            IconButton(
              icon: const Icon(Icons.refresh_rounded, color: Colors.white),
              tooltip: 'Reset Zoom',
              onPressed: () {
                if (_animationController != null) {
                  _animation = Matrix4Tween(
                    begin: _transformationController.value,
                    end: Matrix4.identity(),
                  ).animate(CurvedAnimation(
                    parent: _animationController!,
                    curve: Curves.easeOutCubic,
                  ));
                  _animationController!.forward(from: 0.0);
                } else {
                  _transformationController.value = Matrix4.identity();
                }
              },
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.white))
          : _isError
              ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.error_outline, size: 48, color: Colors.white54),
                      SizedBox(height: 12),
                      Text('Failed to load media preview', style: TextStyle(color: Colors.white70)),
                    ],
                  ),
                )
              : isVideo
                  ? _buildVideoBody()
                  : _buildImageBody(),
    );
  }

  Widget _buildVideoBody() {
    return Column(
      children: [
        Expanded(
          child: GestureDetector(
            onTap: _togglePlayPause,
            behavior: HitTestBehavior.opaque,
            child: Stack(
              alignment: Alignment.center,
              children: [
                if (_controller != null && _controller!.value.isInitialized)
                  Center(
                    child: AspectRatio(
                      aspectRatio: _controller!.value.aspectRatio,
                      child: VideoPlayer(_controller!),
                    ),
                  ),

                // Play / Pause Icon overlay
                if (!_isPlaying)
                  Center(
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: const BoxDecoration(
                        color: Colors.black54,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.play_arrow, size: 54, color: Colors.white),
                    ),
                  ),
              ],
            ),
          ),
        ),

        // Bottom playback progress & controls
        if (_controller != null && _controller!.value.isInitialized)
          SafeArea(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              color: Colors.black87,
              child: ValueListenableBuilder(
                valueListenable: _controller!,
                builder: (context, VideoPlayerValue value, child) {
                  final position = value.position;
                  final duration = value.duration;

                  return Row(
                    children: [
                      IconButton(
                        icon: Icon(_isPlaying ? Icons.pause : Icons.play_arrow, color: Colors.white),
                        onPressed: _togglePlayPause,
                      ),
                      Text(
                        '${position.inMinutes}:${(position.inSeconds % 60).toString().padLeft(2, '0')}',
                        style: const TextStyle(color: Colors.white70, fontSize: 12),
                      ),
                      Expanded(
                        child: Slider(
                          value: position.inMilliseconds.toDouble().clamp(0.0, duration.inMilliseconds.toDouble()),
                          min: 0.0,
                          max: duration.inMilliseconds.toDouble() > 0 ? duration.inMilliseconds.toDouble() : 1.0,
                          activeColor: AppColors.primaryColor,
                          inactiveColor: Colors.white24,
                          onChanged: (pos) {
                            _controller!.seekTo(Duration(milliseconds: pos.toInt()));
                          },
                        ),
                      ),
                      Text(
                        '${duration.inMinutes}:${(duration.inSeconds % 60).toString().padLeft(2, '0')}',
                        style: const TextStyle(color: Colors.white70, fontSize: 12),
                      ),
                    ],
                  );
                },
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildImageBody() {
    final size = MediaQuery.of(context).size;

    final imageWidget = widget.imageFile != null
        ? Image.file(
            widget.imageFile!,
            fit: BoxFit.contain,
          )
        : widget.imageUrl != null && widget.imageUrl!.isNotEmpty
            ? CustomNetworkImage(imageUrl: widget.imageUrl!, fit: BoxFit.contain)
            : const Icon(Icons.broken_image, size: 48, color: Colors.white54);

    return SizedBox.expand(
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onDoubleTapDown: (details) => _doubleTapDetails = details,
        onDoubleTap: _handleDoubleTap,
        child: InteractiveViewer(
          transformationController: _transformationController,
          clipBehavior: Clip.none,
          panEnabled: true,
          scaleEnabled: true,
          minScale: 0.5,
          maxScale: 6.0,
          boundaryMargin: EdgeInsets.symmetric(
            horizontal: size.width * 0.75,
            vertical: size.height * 0.75,
          ),
          child: Center(
            child: imageWidget,
          ),
        ),
      ),
    );
  }
}
