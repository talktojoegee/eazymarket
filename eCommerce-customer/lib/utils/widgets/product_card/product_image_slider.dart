import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../custom_image_container.dart';

class ProductImageSlider extends StatefulWidget {
  final List<String> images;
  final BoxFit fit;
  final bool isDim;
  final String heroTag;
  final VoidCallback onTap;

  const ProductImageSlider({
    super.key,
    required this.images,
    required this.fit,
    required this.isDim,
    required this.heroTag,
    required this.onTap,
  });

  @override
  State<ProductImageSlider> createState() => _ProductImageSliderState();
}

class _ProductImageSliderState extends State<ProductImageSlider> {
  int _currentIndex = 0;
  late final PageController _pageController;
  late final ScrollController _scrollController;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
    _scrollController = ScrollController();
  }

  @override
  void dispose() {
    _pageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToIndex(int index) {
    if (!_scrollController.hasClients) return;
    final dotWidth = 4.w;
    const dotMargin = 4.0; // 2 left + 2 right
    final activeDotWidth = 8.w;

    final leftEdge = index * (dotWidth + dotMargin);
    final center = leftEdge + 2 + (activeDotWidth / 2);
    double targetOffset = center - 25;

    final maxScroll = _scrollController.position.maxScrollExtent;
    targetOffset = targetOffset.clamp(0.0, maxScroll);

    _scrollController.animateTo(
      targetOffset,
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeInOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    if (widget.images.isEmpty) {
      return const SizedBox.shrink();
    }
    if (widget.images.length == 1) {
      return GestureDetector(
        onTap: widget.onTap,
        child: ColorFiltered(
          colorFilter: widget.isDim
              ? const ColorFilter.matrix([
            0.2126,
            0.7152,
            0.0722,
            0,
            0,
            0.2126,
            0.7152,
            0.0722,
            0,
            0,
            0.2126,
            0.7152,
            0.0722,
            0,
            0,
            0,
            0,
            0,
            1,
            0,
          ])
              : const ColorFilter.mode(Colors.transparent, BlendMode.multiply),
          child: CustomImageContainer(imagePath: widget.images.first, fit: widget.fit),
        ),
      );
    }

    return Stack(
      alignment: Alignment.bottomCenter,
      children: [
        GestureDetector(
          onTap: widget.onTap,
          child: PageView.builder(
            controller: _pageController,
            itemCount: widget.images.length,
            onPageChanged: (index) {
              setState(() {
                _currentIndex = index;
              });
              WidgetsBinding.instance.addPostFrameCallback((_) {
                _scrollToIndex(index);
              });
            },
            itemBuilder: (context, index) {
              final imageWidget = ColorFiltered(
                colorFilter: widget.isDim
                    ? const ColorFilter.matrix([
                  0.2126,
                  0.7152,
                  0.0722,
                  0,
                  0,
                  0.2126,
                  0.7152,
                  0.0722,
                  0,
                  0,
                  0.2126,
                  0.7152,
                  0.0722,
                  0,
                  0,
                  0,
                  0,
                  0,
                  1,
                  0,
                ])
                    : const ColorFilter.mode(Colors.transparent, BlendMode.multiply),
                child: CustomImageContainer(imagePath: widget.images[index], fit: widget.fit),
              );

              if (index == 0) {
                return imageWidget;
              }
              return imageWidget;
            },
          ),
        ),

        Positioned(
          left: 4,
          bottom: 4,
          child: Container(
            padding: const EdgeInsets.symmetric(
              horizontal: 6,
              vertical: 4,
            ),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.85),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: _buildIndicators(),
            ),
          ),
        ),
      ],
    );
  }
  List<Widget> _buildIndicators() {
    const maxIndicators = 5;
    final total = widget.images.length;

    if (total <= maxIndicators) {
      return List.generate(
        total,
            (index) => _indicator(index),
      );
    }

    int start = _currentIndex - 2;
    int end = start + maxIndicators;

    if (start < 0) {
      start = 0;
      end = maxIndicators;
    }

    if (end > total) {
      end = total;
      start = total - maxIndicators;
    }

    return List.generate(
      end - start,
          (index) => _indicator(start + index),
    );
  }

  Widget _indicator(int index) {
    final isActive = _currentIndex == index;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 250),
      curve: Curves.easeOut,
      width: isActive ? 12 : 4,
      height: 4,
      margin: const EdgeInsets.symmetric(horizontal: 1.5),
      decoration: BoxDecoration(
        color: isActive
            ? Colors.black
            : Colors.black.withValues(alpha: 0.25),
        borderRadius: BorderRadius.circular(10),
      ),
    );
  }
}