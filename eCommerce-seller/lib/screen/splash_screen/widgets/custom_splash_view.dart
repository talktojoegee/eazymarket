import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';
import 'package:video_player/video_player.dart';
import '../../../utils/color_utils.dart';
import '../model/custom_splash_model.dart';

/// Renders the dynamic splash screen background and overlay according to [SplashScreenModel].
/// If [splashModel] is null or any asset fails to load, gracefully falls back to default branding.
class CustomSplashView extends StatelessWidget {
  final SplashScreenModel? splashModel;
  final Widget defaultChild;

  const CustomSplashView({
    super.key,
    required this.splashModel,
    required this.defaultChild,
  });

  @override
  Widget build(BuildContext context) {
    if (splashModel == null || !splashModel!.hasContent) {
      return Container(
        color: Theme.of(context).colorScheme.surface,
        child: Center(child: defaultChild),
      );
    }

    return Stack(
      fit: StackFit.expand,
      children: [
        _buildBackground(context),
        _buildOverlay(context),
      ],
    );
  }

  Widget _buildBackground(BuildContext context) {
    final type = splashModel?.backgroundType?.toLowerCase();
    final Color? bgColor = hexStringToColor(splashModel?.backgroundColor);
    final defaultBg = Container(
      color: bgColor ?? Theme.of(context).colorScheme.surface,
    );

    switch (type) {
      case 'color':
        return defaultBg;

      case 'image':
        final imageUrl = splashModel?.backgroundImage?.trim();
        if (imageUrl != null && imageUrl.isNotEmpty) {
          return CachedNetworkImage(
            imageUrl: imageUrl,
            fit: BoxFit.cover,
            width: double.infinity,
            height: double.infinity,
            placeholder: (context, url) => defaultBg,
            errorWidget: (context, url, error) => defaultBg,
          );
        }
        return defaultBg;

      case 'video':
        final videoUrl = splashModel?.backgroundVideo?.trim();
        if (videoUrl != null && videoUrl.isNotEmpty) {
          return _SplashVideoBackground(
            videoUrl: videoUrl,
            fallbackColor: bgColor ?? Theme.of(context).colorScheme.surface,
          );
        }
        return defaultBg;

      default:
        final imageUrl = splashModel?.backgroundImage?.trim();
        if (imageUrl != null && imageUrl.isNotEmpty) {
          return CachedNetworkImage(
            imageUrl: imageUrl,
            fit: BoxFit.cover,
            width: double.infinity,
            height: double.infinity,
            placeholder: (context, url) => defaultBg,
            errorWidget: (context, url, error) => defaultBg,
          );
        }
        final videoUrl = splashModel?.backgroundVideo?.trim();
        if (videoUrl != null && videoUrl.isNotEmpty) {
          return _SplashVideoBackground(
            videoUrl: videoUrl,
            fallbackColor: bgColor ?? Theme.of(context).colorScheme.surface,
          );
        }
        return defaultBg;
    }
  }

  Widget _buildOverlay(BuildContext context) {
    final bool isEnabled = splashModel?.overlayEnabled == true;

    if (!isEnabled) {
      return const SizedBox.shrink();
    }

    final String? overlayType = splashModel?.overlayType?.toLowerCase();
    final String? imageUrl = splashModel?.overlayImage?.trim();
    final String? lottieUrl = splashModel?.overlayLottie?.trim();

    Widget? overlayWidget;

    if (overlayType == 'image' && imageUrl != null && imageUrl.isNotEmpty) {
      overlayWidget = CachedNetworkImage(
        imageUrl: imageUrl,
        fit: BoxFit.contain,
        placeholder: (_, __) => const SizedBox.shrink(),
        errorWidget: (_, __, ___) => const SizedBox.shrink(),
      );
    } else if (overlayType == 'lottie' &&
        lottieUrl != null &&
        lottieUrl.isNotEmpty) {
      overlayWidget = Lottie.network(
        lottieUrl,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => const SizedBox.shrink(),
      );
    }

    if (overlayWidget == null) {
      return const SizedBox.shrink();
    }

    // Determine alignment from position coordinates (0..100)
    Alignment alignment = Alignment.center;
    final pos = splashModel?.overlayPosition;
    if (pos != null) {
      final double h = pos.horizontal.clamp(0.0, 100.0);
      final double v = pos.vertical.clamp(0.0, 100.0);
      alignment = Alignment(((h - 50.0) / 50.0), ((v - 50.0) / 50.0));
    }

    // Determine size (either percentage of screen width or logical pixels)
    final double screenWidth = MediaQuery.of(context).size.width;
    final double rawSize = splashModel?.overlaySize ?? 40.0;
    final double dimension = rawSize <= 100.0 && rawSize > 0
        ? screenWidth * (rawSize / 100.0)
        : (rawSize > 100.0 ? rawSize : 180.0);

    return Align(
      alignment: alignment,
      child: SizedBox(
        width: dimension,
        height: dimension,
        child: overlayWidget,
      ),
    );
  }
}

class _SplashVideoBackground extends StatefulWidget {
  final String videoUrl;
  final Color? fallbackColor;

  const _SplashVideoBackground({
    required this.videoUrl,
    this.fallbackColor,
  });

  @override
  State<_SplashVideoBackground> createState() => _SplashVideoBackgroundState();
}

class _SplashVideoBackgroundState extends State<_SplashVideoBackground> {
  VideoPlayerController? _controller;
  bool _isInitialized = false;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _initVideo();
  }

  Future<void> _initVideo() async {
    try {
      final uri = Uri.tryParse(widget.videoUrl);
      if (uri == null) {
        if (mounted) setState(() => _hasError = true);
        return;
      }
      final controller = VideoPlayerController.networkUrl(uri);
      _controller = controller;
      await controller.initialize();
      await controller.setLooping(true);
      await controller.setVolume(0.0);
      await controller.play();
      if (mounted) {
        setState(() => _isInitialized = true);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _hasError = true);
      }
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_hasError || !_isInitialized || _controller == null) {
      return Container(
        color: widget.fallbackColor ?? Theme.of(context).colorScheme.surface,
      );
    }

    final size = _controller!.value.size;
    return SizedBox.expand(
      child: FittedBox(
        fit: BoxFit.cover,
        child: SizedBox(
          width:
              size.width > 0 ? size.width : MediaQuery.of(context).size.width,
          height: size.height > 0
              ? size.height
              : MediaQuery.of(context).size.height,
          child: VideoPlayer(_controller!),
        ),
      ),
    );
  }
}
