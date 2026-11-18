import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';

/// The app's one and only loading indicator. Use this everywhere — never a
/// bare [CircularProgressIndicator].
///
/// **Indeterminate** (the default): a tapered arc sweeps a faint track, fading
/// from transparent at its tail to solid at its head, with a small head dot
/// leading it round. Its length breathes twice per revolution, so the motion
/// reads as alive rather than as a mechanical constant-length sweep.
///
/// **Determinate**: pass [value] between 0 and 1 and the same arc becomes the
/// progress fill, animating smoothly between updates instead of jumping. Set
/// [showPercentage] to print the number in the middle.
///
/// ```dart
/// const CustomLoadingIndicator()                        // page loader
/// const CustomLoadingIndicator(size: 18, strokeWidth: 2) // inside a button
/// CustomLoadingIndicator(value: 0.4, showPercentage: true)
/// ```
class CustomLoadingIndicator extends StatefulWidget {
  /// Outer diameter in logical pixels.
  final double size;

  /// Arc thickness. Derived from [size] when omitted, so small indicators stay
  /// delicate and large ones stay visible.
  final double? strokeWidth;

  /// Defaults to [AppColors.primaryColor]. Pass white/black when the indicator
  /// sits on a filled button.
  final Color? color;

  /// 0–1 for determinate progress; null spins.
  final double? value;

  /// Retained for call sites that set it explicitly; a non-null [value] is
  /// enough on its own.
  final bool isDeterminate;

  /// Prints the rounded percentage in the centre. Determinate only.
  final bool showPercentage;

  const CustomLoadingIndicator({
    super.key,
    this.size = 48,
    this.strokeWidth,
    this.color,
    this.value,
    this.isDeterminate = false,
    this.showPercentage = false,
  });

  @override
  State<CustomLoadingIndicator> createState() => _CustomLoadingIndicatorState();
}

class _CustomLoadingIndicatorState extends State<CustomLoadingIndicator>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  bool get _isDeterminate => widget.isDeterminate || widget.value != null;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1250),
    );
    if (!_isDeterminate) _controller.repeat();
  }

  @override
  void didUpdateWidget(covariant CustomLoadingIndicator oldWidget) {
    super.didUpdateWidget(oldWidget);
    // Don't burn frames painting a static arc.
    if (_isDeterminate && _controller.isAnimating) {
      _controller.stop();
    } else if (!_isDeterminate && !_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color = widget.color ?? AppColors.primaryColor;
    final stroke =
        widget.strokeWidth ?? (widget.size * 0.085).clamp(1.6, 4.5).toDouble();

    return RepaintBoundary(
      child: SizedBox(
        width: widget.size,
        height: widget.size,
        child: _isDeterminate
            ? _buildDeterminate(color, stroke)
            : AnimatedBuilder(
                animation: _controller,
                builder: (context, _) => CustomPaint(
                  painter: _ArcPainter(
                    t: _controller.value,
                    color: color,
                    stroke: stroke,
                  ),
                ),
              ),
      ),
    );
  }

  Widget _buildDeterminate(Color color, double stroke) {
    final target = (widget.value ?? 0).clamp(0.0, 1.0).toDouble();

    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: target),
      duration: const Duration(milliseconds: 320),
      curve: Curves.easeOutCubic,
      builder: (context, shown, _) {
        return Material(
          color: Colors.transparent,
          child: CustomPaint(
            painter: _ArcPainter(t: 0, value: shown, color: color, stroke: stroke),
            child: widget.showPercentage
                ? Center(
                    child: Text(
                      '${(shown * 100).round()}%',
                      style: TextStyle(
                        fontSize: widget.size * 0.22,
                        fontWeight: FontWeight.w700,
                        letterSpacing: -0.4,
                        color: color,
                      ),
                    ),
                  )
                : null,
          ),
        );
      },
    );
  }
}

class _ArcPainter extends CustomPainter {
  /// Animation position, 0–1. Ignored when [value] is set.
  final double t;

  /// Progress fraction for determinate mode; null spins.
  final double? value;
  final Color color;
  final double stroke;

  const _ArcPainter({
    required this.t,
    required this.color,
    required this.stroke,
    this.value,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final shortest = math.min(size.width, size.height);
    final radius = (shortest - stroke) / 2;
    if (radius <= 0) return;

    final center = Offset(size.width / 2, size.height / 2);
    final circle = Rect.fromCircle(center: center, radius: radius);

    canvas.drawCircle(
      center,
      radius,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = stroke
        ..color = color.withValues(alpha: 0.12),
    );

    final double start;
    final double sweep;

    if (value != null) {
      start = -math.pi / 2;
      sweep = value! * 2 * math.pi;
    } else {
      // Two breaths per revolution: the arc grows and shrinks as it turns.
      final breath = 0.5 - 0.5 * math.cos(t * 4 * math.pi);
      sweep = (0.16 + 0.56 * breath) * 2 * math.pi;
      start = -math.pi / 2 + t * 2 * math.pi;
    }

    if (sweep <= 0) return;

    canvas.drawArc(
      circle,
      start,
      sweep,
      false,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = stroke
        ..strokeCap = StrokeCap.round
        // Tail transparent, head solid — the taper is what makes it read as
        // motion rather than a rotating stick.
        ..shader = SweepGradient(
          startAngle: 0,
          endAngle: sweep,
          colors: [color.withValues(alpha: 0), color],
          transform: GradientRotation(start),
        ).createShader(circle),
    );

    // The head dot would crowd the arc at button size, so it only shows once
    // there is room for it.
    if (shortest >= 28) {
      final headAngle = start + sweep;
      final head =
          center + Offset(math.cos(headAngle), math.sin(headAngle)) * radius;
      canvas.drawCircle(head, stroke * 0.62, Paint()..color = color);
    }
  }

  @override
  bool shouldRepaint(covariant _ArcPainter old) =>
      old.t != t ||
      old.value != value ||
      old.color != color ||
      old.stroke != stroke;
}
