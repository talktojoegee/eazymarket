import 'dart:async';
import 'package:flutter/material.dart';

/// A reusable widget that provides a smooth, staggered entrance animation
/// for items in a list or grid when they are first displayed.
///
/// Items slide into their position with a subtle fade-in, cascading one-by-one.
/// Once animated, state is kept alive so scrolling does not re-trigger the animation.
class AnimatedListItem extends StatefulWidget {
  final int index;
  final Widget child;
  final Duration duration;
  final Duration delayStep;
  final int maxStaggerCount;
  final double slideOffset;
  final Axis direction;

  const AnimatedListItem({
    super.key,
    required this.index,
    required this.child,
    this.duration = const Duration(milliseconds: 380),
    this.delayStep = const Duration(milliseconds: 45),
    this.maxStaggerCount = 10,
    this.slideOffset = 0.18,
    this.direction = Axis.vertical,
  });

  @override
  State<AnimatedListItem> createState() => _AnimatedListItemState();
}

class _AnimatedListItemState extends State<AnimatedListItem>
    with SingleTickerProviderStateMixin, AutomaticKeepAliveClientMixin {
  late final AnimationController _controller;
  late final Animation<double> _fadeAnimation;
  late final Animation<Offset> _slideAnimation;
  Timer? _timer;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: widget.duration,
    );

    final curved = CurvedAnimation(
      parent: _controller,
      curve: Curves.easeOutCubic,
    );

    _fadeAnimation = Tween<double>(
      begin: 0.0,
      end: 1.0,
    ).animate(curved);

    final beginOffset = widget.direction == Axis.vertical
        ? Offset(0.0, widget.slideOffset)
        : Offset(widget.slideOffset, 0.0);

    _slideAnimation = Tween<Offset>(
      begin: beginOffset,
      end: Offset.zero,
    ).animate(curved);

    // Initial items visible on screen cascade with staggered delay.
    // Items beyond maxStaggerCount animate immediately on scroll without delay.
    final delay = widget.index < widget.maxStaggerCount
        ? widget.delayStep * widget.index
        : Duration.zero;

    if (delay == Duration.zero) {
      _controller.forward();
    } else {
      _timer = Timer(delay, () {
        if (mounted) {
          _controller.forward();
        }
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return FadeTransition(
      opacity: _fadeAnimation,
      child: SlideTransition(
        position: _slideAnimation,
        child: widget.child,
      ),
    );
  }
}
