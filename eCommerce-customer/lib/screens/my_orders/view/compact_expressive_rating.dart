import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// A compact animated face and five gold stars in a single row.
///
/// This is a controlled widget: update [rating] inside [onChanged]. No packages,
/// Rive files, images, or other assets are required.
///
/// ```dart
/// int _rating = 0; // A field in your State class.
///
/// // Inside build():
/// CompactExpressiveRating(
///   rating: _rating,
///   onChanged: (value) => setState(() => _rating = value),
/// )
/// ```
///
/// Supports taps, horizontal dragging, keyboard arrows/Home/End, screen-reader
/// adjustments, RTL layouts, haptics, and the system reduced-motion setting.
/// Pass a null [onChanged] for a read-only display. Rating zero means unrated.
///
/// Once a rating is chosen, the face keeps moving in place with a small loop
/// that matches the mood (shake, sway, breathe, squash, pulse), plus blinking
/// and falling tears for low ratings. Set [idleAnimation] to false to turn the
/// looping motion off. The loop stops automatically when the system
/// reduced-motion setting is on.
///
/// The default row is 268 logical pixels wide and 48 high, including 44-pixel
/// star targets. In narrower parents it scales down to fit; allow its natural
/// width to preserve full-size touch targets. Use directly in a Column, or
/// place it inside Flexible/Expanded when used in another constrained Row.
class CompactExpressiveRating extends StatefulWidget {
  const CompactExpressiveRating({
    super.key,
    required this.rating,
    this.onChanged,
    this.faceSize = 52,
    this.starSize = 28,
    this.starSpacing = 4,
    this.faceSpacing = 10,
    this.axis = Axis.vertical,
    this.starColor = const Color(0xFFF7BC24),
    this.unselectedStarColor,
    this.faceColor = const Color(0xFFFFD45B),
    this.unratedFaceColor = const Color(0xFFE5E7EB),
    this.unratedInkColor = const Color(0xFF9CA3AF),
    this.enableHaptics = true,
    this.idleAnimation = true,
    this.semanticLabel = 'Rating',
    this.ratingLabels = const [
      'Disappointing',
      'Could be better',
      'Okay',
      'Good',
      'Excellent',
    ],
  })  : assert(rating >= 0 && rating <= 5),
        assert(faceSize > 0 && faceSize <= 96),
        assert(starSize > 0 && starSize <= 64),
        assert(starSpacing >= 0 && starSpacing < double.infinity),
        assert(faceSpacing >= 0 && faceSpacing < double.infinity),
        assert(ratingLabels.length == 5);

  final int rating;
  final ValueChanged<int>? onChanged;
  final double faceSize;
  final double starSize;
  final double starSpacing;
  final double faceSpacing;
  final Axis axis;
  final Color starColor;

  /// Defaults to a neutral outline derived from the current theme.
  final Color? unselectedStarColor;
  final Color faceColor;
  final Color unratedFaceColor;
  final Color unratedInkColor;
  final bool enableHaptics;

  /// Keeps the face moving in place after a rating is chosen.
  final bool idleAnimation;
  final String semanticLabel;

  /// Five localized descriptions, ordered from one star to five stars.
  final List<String> ratingLabels;

  @override
  State<CompactExpressiveRating> createState() =>
      _CompactExpressiveRatingState();
}

class _CompactExpressiveRatingState extends State<CompactExpressiveRating>
    with TickerProviderStateMixin {
  late final AnimationController _transition;
  late final AnimationController _reaction;

  /// Loops 0 -> 1 forever while a rating is set. Drives the face only.
  late final AnimationController _idle;
  late double _fromRating;
  late double _toRating;
  late double _fromExpression;
  late double _toExpression;
  bool _reduceMotion = false;
  bool _showFocus = false;
  int? _lastGestureRating;

  double get _animatedRating {
    final t = Curves.easeInOutCubic.transform(_transition.value);
    return _fromRating + (_toRating - _fromRating) * t;
  }

  double get _animatedExpression {
    final t = Curves.easeInOutCubic.transform(_transition.value);
    return _fromExpression + (_toExpression - _fromExpression) * t;
  }

  @override
  void initState() {
    super.initState();
    _fromRating = _toRating = widget.rating.toDouble();
    _fromExpression =
        _toExpression = widget.rating == 0 ? 3.0 : widget.rating.toDouble();
    _transition = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 300),
      value: 1,
    );
    _reaction = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 560),
      value: 1,
    );
    _idle = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2400),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _reduceMotion = MediaQuery.maybeOf(context)?.disableAnimations ?? false;
    if (_reduceMotion) {
      _transition.value = 1;
      _reaction.value = 1;
    }
    _syncIdle();
  }

  @override
  void didUpdateWidget(covariant CompactExpressiveRating oldWidget) {
    super.didUpdateWidget(oldWidget);
    _syncIdle();
    if (oldWidget.rating == widget.rating) return;
    _fromRating = _animatedRating;
    _toRating = widget.rating.toDouble();
    _fromExpression = _animatedExpression;
    _toExpression = widget.rating == 0 ? 3.0 : widget.rating.toDouble();
    if (_reduceMotion) {
      _transition.value = 1;
      _reaction.value = 1;
    } else {
      _transition.forward(from: 0);
      _reaction.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _transition.dispose();
    _reaction.dispose();
    _idle.dispose();
    super.dispose();
  }

  /// Starts or stops the looping face motion to match the current state.
  void _syncIdle() {
    final shouldRun =
        widget.idleAnimation && widget.rating > 0 && !_reduceMotion;
    if (shouldRun) {
      if (!_idle.isAnimating) _idle.repeat();
    } else if (_idle.isAnimating || _idle.value != 0) {
      _idle.stop();
      _idle.value = 0;
    }
  }

  void _select(int value, {bool fromGesture = false}) {
    final callback = widget.onChanged;
    if (callback == null) return;
    final next = value.clamp(1, 5);
    if (fromGesture) {
      if (_lastGestureRating == next) return;
      _lastGestureRating = next;
    }
    if (next == widget.rating) {
      if (!_reduceMotion) _reaction.forward(from: 0);
      return;
    }
    if (widget.enableHaptics) unawaited(HapticFeedback.selectionClick());
    callback(next);
  }

  void _selectAt(double x, double width, bool rtl, double targetSize) {
    final logicalX = rtl ? width - x : x;
    final index = (logicalX / (targetSize + widget.starSpacing)).floor();
    _select(index + 1, fromGesture: true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final rtl = Directionality.of(context) == TextDirection.rtl;
    final interactive = widget.onChanged != null;
    final targetSize = math.max(44.0, widget.starSize * 1.4);
    final starsWidth = targetSize * 5 + widget.starSpacing * 4;
    final naturalWidth = widget.axis == Axis.vertical
        ? math.max(widget.faceSize, starsWidth) + 4
        : widget.faceSize + widget.faceSpacing + starsWidth + 4;
    final rowHeight = math.max(widget.faceSize, targetSize);
    final inactive =
        widget.unselectedStarColor ?? theme.colorScheme.outlineVariant;
    final description = widget.rating == 0
        ? 'Not rated'
        : '${widget.rating} of 5, ${widget.ratingLabels[widget.rating - 1]}';

    return Semantics(
      label: widget.semanticLabel,
      value: description,
      slider: true,
      focusable: interactive,
      readOnly: !interactive,
      onIncrease: interactive && widget.rating < 5
          ? () => _select(widget.rating + 1)
          : null,
      onDecrease: interactive && widget.rating > 1
          ? () => _select(widget.rating - 1)
          : null,
      increasedValue: interactive && widget.rating < 5
          ? '${widget.rating + 1} of 5, ${widget.ratingLabels[widget.rating]}'
          : null,
      decreasedValue: interactive && widget.rating > 1
          ? '${widget.rating - 1} of 5, ${widget.ratingLabels[widget.rating - 2]}'
          : null,
      child: ExcludeSemantics(
        child: FocusableActionDetector(
          enabled: interactive,
          mouseCursor:
              interactive ? SystemMouseCursors.click : MouseCursor.defer,
          onShowFocusHighlight: (value) {
            if (_showFocus != value) setState(() => _showFocus = value);
          },
          shortcuts: <ShortcutActivator, Intent>{
            const SingleActivator(LogicalKeyboardKey.arrowRight): _AdjustRating(
              rtl ? -1 : 1,
            ),
            const SingleActivator(LogicalKeyboardKey.arrowLeft): _AdjustRating(
              rtl ? 1 : -1,
            ),
            const SingleActivator(LogicalKeyboardKey.arrowUp):
                const _AdjustRating(1),
            const SingleActivator(LogicalKeyboardKey.arrowDown):
                const _AdjustRating(-1),
            const SingleActivator(LogicalKeyboardKey.home): const _SetRating(1),
            const SingleActivator(LogicalKeyboardKey.end): const _SetRating(5),
          },
          actions: <Type, Action<Intent>>{
            _AdjustRating: CallbackAction<_AdjustRating>(
              onInvoke: (intent) {
                _select(widget.rating + intent.delta);
                return null;
              },
            ),
            _SetRating: CallbackAction<_SetRating>(
              onInvoke: (intent) {
                _select(intent.value);
                return null;
              },
            ),
          },
          child: LayoutBuilder(
            builder: (context, constraints) {
              final ratingWidget = AnimatedBuilder(
                animation: Listenable.merge([_transition, _reaction]),
                builder: (context, child) {
                  // An unrated face begins neutral rather than unhappy.
                  final expression = _animatedExpression;
                  final reaction = _reduceMotion ? 1.0 : _reaction.value;
                  final wobble =
                      math.sin(reaction * math.pi * 2) * (1 - reaction) * .12;

                  final ratingFactor = _animatedRating.clamp(0.0, 1.0);
                  final currentFaceColor = Color.lerp(
                    widget.unratedFaceColor,
                    widget.faceColor,
                    ratingFactor,
                  )!;
                  final currentInkColor = Color.lerp(
                    widget.unratedInkColor,
                    const Color(0xFF634014),
                    ratingFactor,
                  )!;
                  final showBlush = ratingFactor > 0.5;

                  // The looping idle motion lives in its own AnimatedBuilder so
                  // only the face repaints every frame, not the stars.
                  final faceWidget = Transform.rotate(
                    angle: wobble,
                    child: AnimatedBuilder(
                      animation: _idle,
                      builder: (context, _) {
                        final idle = widget.rating == 0 ? 0.0 : _idle.value;
                        final amount = Curves.easeOut.transform(
                          _transition.value,
                        );
                        final motion = _idleMotion(
                          widget.rating,
                          idle,
                          amount,
                        );
                        return RepaintBoundary(
                          child: Transform.rotate(
                            angle: motion.angle,
                            child: Transform.scale(
                              scaleX: motion.scaleX,
                              scaleY: motion.scaleY,
                              child: CustomPaint(
                                size: Size.square(widget.faceSize),
                                painter: _RatingFacePainter(
                                  expression: expression,
                                  color: currentFaceColor,
                                  inkColor: currentInkColor,
                                  showBlush: showBlush,
                                  idle: idle,
                                ),
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  );

                  final starsWidget = GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTapUp: interactive
                        ? (details) {
                            _lastGestureRating = null;
                            _selectAt(
                              details.localPosition.dx,
                              starsWidth,
                              rtl,
                              targetSize,
                            );
                          }
                        : null,
                    onHorizontalDragStart: interactive
                        ? (details) {
                            _lastGestureRating = null;
                            _selectAt(
                              details.localPosition.dx,
                              starsWidth,
                              rtl,
                              targetSize,
                            );
                          }
                        : null,
                    onHorizontalDragUpdate: interactive
                        ? (details) => _selectAt(
                              details.localPosition.dx,
                              starsWidth,
                              rtl,
                              targetSize,
                            )
                        : null,
                    onHorizontalDragEnd:
                        interactive ? (_) => _lastGestureRating = null : null,
                    onHorizontalDragCancel:
                        interactive ? () => _lastGestureRating = null : null,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        for (var index = 0; index < 5; index++) ...[
                          if (index > 0) SizedBox(width: widget.starSpacing),
                          CustomPaint(
                            size: Size(targetSize, rowHeight),
                            painter: _RatingStarPainter(
                              fill: (_animatedRating - index).clamp(
                                0.0,
                                1.0,
                              ),
                              active: index < widget.rating,
                              reaction: reaction,
                              index: index,
                              celebrate: widget.rating == 5 && index == 4,
                              starSize: widget.starSize,
                              color: widget.starColor,
                              inactiveColor: inactive,
                              rtl: rtl,
                            ),
                          ),
                        ],
                      ],
                    ),
                  );

                  final content = widget.axis == Axis.vertical
                      ? Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            faceWidget,
                            SizedBox(height: widget.faceSpacing),
                            starsWidget,
                          ],
                        )
                      : Row(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            faceWidget,
                            SizedBox(width: widget.faceSpacing),
                            starsWidget,
                          ],
                        );

                  return DecoratedBox(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        width: 2,
                        color: _showFocus
                            ? theme.colorScheme.primary
                            : Colors.transparent,
                      ),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(2),
                      child: content,
                    ),
                  );
                },
              );
              if (constraints.hasBoundedWidth &&
                  constraints.maxWidth < naturalWidth) {
                return SizedBox(
                  width: constraints.maxWidth,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: widget.axis == Axis.vertical
                        ? AlignmentDirectional.center
                        : AlignmentDirectional.centerStart,
                    child: ratingWidget,
                  ),
                );
              }
              return ratingWidget;
            },
          ),
        ),
      ),
    );
  }
}

class _AdjustRating extends Intent {
  const _AdjustRating(this.delta);
  final int delta;
}

class _SetRating extends Intent {
  const _SetRating(this.value);
  final int value;
}

/// Small looping motion for the face, one personality per rating.
///
/// [t] is the idle loop position (0 to 1). Every frequency below is a whole
/// number of cycles per loop, so the loop restarts without a visible jump.
/// [amount] (0 to 1) fades the motion in after the rating changes. All values
/// are rotation or scale around the face center, so the face never leaves its
/// box.
({double angle, double scaleX, double scaleY}) _idleMotion(
  int rating,
  double t,
  double amount,
) {
  final phase = t * math.pi * 2;
  var angle = 0.0;
  var sx = 1.0;
  var sy = 1.0;
  switch (rating) {
    case 1: // Quick head shake.
      angle = math.sin(phase * 4) * .09;
    case 2: // Slow sway with a little droop.
      angle = math.sin(phase) * .05;
      final droop = .5 + .5 * math.sin(phase);
      sx = 1 + .015 * droop;
      sy = 1 - .03 * droop;
    case 3: // Calm breathing.
      final b = math.sin(phase) * .04;
      sx = 1 + b;
      sy = 1 + b;
    case 4: // Happy squash and stretch.
      final s = math.sin(phase * 2) * .06;
      sx = 1 + s;
      sy = 1 - s;
    case 5: // Excited pulse.
      final p = math.sin(phase * 3) * .08;
      sx = 1 + p;
      sy = 1 + p;
  }
  return (
    angle: angle * amount,
    scaleX: 1 + (sx - 1) * amount,
    scaleY: 1 + (sy - 1) * amount,
  );
}

class _RatingFacePainter extends CustomPainter {
  const _RatingFacePainter({
    required this.expression,
    required this.color,
    required this.inkColor,
    required this.showBlush,
    required this.idle,
  });

  final double expression;
  final Color color;
  final Color inkColor;
  final bool showBlush;

  /// Idle loop position (0 to 1). Drives blinking and tears.
  final double idle;

  @override
  void paint(Canvas canvas, Size size) {
    // Work in normalized coordinates so the face stays crisp at any size.
    canvas.save();
    canvas.scale(size.width / 100, size.height / 100);
    const center = Offset(50, 50);
    final paint = Paint()
      ..shader = RadialGradient(
        center: const Alignment(-.4, -.6),
        colors: [Color.lerp(color, Colors.white, .35)!, color],
      ).createShader(const Rect.fromLTWH(3, 3, 94, 94));
    canvas.drawCircle(center, 47, paint);
    paint.shader = null;
    final ink = inkColor;
    final happy = ((expression - 3) / 2).clamp(-1.0, 1.0);
    final grin = ((expression - 4) / 1).clamp(0.0, 1.0);
    paint
      ..color = ink
      ..style = PaintingStyle.stroke
      ..strokeWidth = 5
      ..strokeCap = StrokeCap.round;

    // One quick blink near the end of every idle loop.
    final blinkPhase = ((idle - .88) / .08).clamp(0.0, 1.0);
    final blink = 1 - math.sin(blinkPhase * math.pi) * .9;

    for (final x in [33.0, 67.0]) {
      if (grin > .05) {
        final eye = Path()
          ..moveTo(x - 4, 40)
          ..quadraticBezierTo(x, 40 - 8 * grin, x + 4, 40);
        canvas.drawPath(eye, paint);
      } else {
        paint.style = PaintingStyle.fill;
        canvas.drawOval(
          Rect.fromCenter(center: Offset(x, 39), width: 7, height: 10 * blink),
          paint,
        );
        paint.style = PaintingStyle.stroke;
      }
    }

    // A continuous curve travels from a frown through neutral to a broad smile.
    final mouth = Path()
      ..moveTo(33, 64)
      ..cubicTo(40, 64 + happy * 18, 60, 64 + happy * 18, 67, 64);
    paint.strokeWidth = 4;
    canvas.drawPath(mouth, paint);
    if (grin > 0) {
      final openSmile = Path()
        ..moveTo(34, 65)
        ..quadraticBezierTo(50, 65 + grin * 7, 66, 65)
        ..quadraticBezierTo(50, 65 + grin * 25, 34, 65)
        ..close();
      paint.style = PaintingStyle.fill;
      canvas.drawPath(openSmile, paint);
    }

    // Tears roll down inside the face for low ratings, one after the other.
    final sadness = ((2.6 - expression) / .8).clamp(0.0, 1.0);
    if (sadness > 0 && idle > 0) {
      paint.style = PaintingStyle.fill;
      for (var i = 0; i < 2; i++) {
        final x = i == 0 ? 31.0 : 69.0;
        final p = (idle * 2 + i * .5) % 1.0;
        final alpha = (math.sin(p * math.pi) * sadness).clamp(0.0, 1.0);
        paint.color = const Color(0xFF6BB6F5).withValues(alpha: alpha);
        canvas.drawOval(
          Rect.fromCenter(
            center: Offset(x, 47 + p * 14),
            width: 5,
            height: 7 + 2 * p,
          ),
          paint,
        );
      }
    }

    if (showBlush) {
      paint
        ..style = PaintingStyle.fill
        ..color = const Color(
          0xFFFF9C54,
        ).withValues(alpha: .12 + .16 * math.max(0, happy));
      canvas.drawOval(const Rect.fromLTWH(17, 51, 14, 7), paint);
      canvas.drawOval(const Rect.fromLTWH(69, 51, 14, 7), paint);
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _RatingFacePainter oldDelegate) =>
      expression != oldDelegate.expression ||
      color != oldDelegate.color ||
      inkColor != oldDelegate.inkColor ||
      showBlush != oldDelegate.showBlush ||
      idle != oldDelegate.idle;
}

class _RatingStarPainter extends CustomPainter {
  const _RatingStarPainter({
    required this.fill,
    required this.active,
    required this.reaction,
    required this.index,
    required this.celebrate,
    required this.starSize,
    required this.color,
    required this.inactiveColor,
    required this.rtl,
  });

  final double fill;
  final bool active;
  final double reaction;
  final int index;
  final bool celebrate;
  final double starSize;
  final Color color;
  final Color inactiveColor;
  final bool rtl;

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final local = ((reaction - index * .035) / .82).clamp(0.0, 1.0);
    final spring = active ? math.sin(local * math.pi * 2) * (1 - local) : 0.0;
    final radius = starSize / 2;
    canvas.save();
    canvas.translate(center.dx, center.dy);
    canvas.scale(1 + spring * .22);
    canvas.rotate(spring * .10);
    final star = Path();
    for (var point = 0; point < 10; point++) {
      final angle = -math.pi / 2 + point * math.pi / 5;
      final r = point.isEven ? radius : radius * .48;
      final x = math.cos(angle) * r;
      final y = math.sin(angle) * r;
      if (point == 0) {
        star.moveTo(x, y);
      } else {
        star.lineTo(x, y);
      }
    }
    star.close();
    final paint = Paint()
      ..color = inactiveColor.withValues(alpha: .22)
      ..style = PaintingStyle.fill;
    canvas.drawPath(star, paint);
    paint
      ..color = inactiveColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.4
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(star, paint);
    if (fill > 0) {
      canvas.save();
      canvas.clipRect(
        Rect.fromLTRB(
          rtl ? radius - radius * 2 * fill : -radius - 2,
          -radius - 2,
          rtl ? radius + 2 : -radius + radius * 2 * fill + (fill == 1 ? 2 : 0),
          radius + 2,
        ),
      );
      paint
        ..color = color
        ..style = PaintingStyle.fill;
      canvas.drawPath(star, paint);
      paint.style = PaintingStyle.stroke;
      canvas.drawPath(star, paint);
      canvas.restore();
    }
    canvas.restore();

    if (celebrate && reaction > 0 && reaction < 1) {
      final opacity = math.sin(reaction * math.pi) * (1 - reaction);
      paint
        ..style = PaintingStyle.fill
        ..color = color.withValues(alpha: opacity.clamp(0.0, 1.0));
      for (var i = 0; i < 8; i++) {
        final angle = i * math.pi / 4;
        final distance = radius + 3 + reaction * 6;
        final offset =
            center + Offset(math.cos(angle), math.sin(angle)) * distance;
        canvas.drawCircle(offset, 1.4 * (1 - reaction), paint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant _RatingStarPainter oldDelegate) =>
      fill != oldDelegate.fill ||
      active != oldDelegate.active ||
      reaction != oldDelegate.reaction ||
      index != oldDelegate.index ||
      celebrate != oldDelegate.celebrate ||
      starSize != oldDelegate.starSize ||
      color != oldDelegate.color ||
      inactiveColor != oldDelegate.inactiveColor ||
      rtl != oldDelegate.rtl;
}
