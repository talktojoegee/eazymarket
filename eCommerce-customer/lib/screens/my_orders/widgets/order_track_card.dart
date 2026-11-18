import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/config/app_colors.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:intl/intl.dart';
import '../model/tracking.dart';

const Color _trackDone = AppTheme.orderTrackMainColor;
const Color _trackInactive = Color(0xFFD9D9D9);
const Color _trackHollowBorder = Color(0xFFCBD5E1);
const Color _trackMuted = Color(0xFF9AA0A6);

class OrderTrackCard extends StatelessWidget {
  final List<Milestones> stages;
  final List<History> history;
  final String? statusCode;

  const OrderTrackCard({
    super.key,
    required this.stages,
    required this.history,
    this.statusCode,
  });

  @override
  Widget build(BuildContext context) {
    if (stages.isEmpty) return const SizedBox.shrink();

    final scheme = Theme.of(context).colorScheme;

    final nodes = _buildNodes(context);
    final progress = _progress(nodes);
    final current = _currentStage();
    final title = _titleForStage(context, current);
    final subtitle = _statusSummary(context, current);

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(16.r),
      ),
      child: Column(
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 14.h, 16.w, 8.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (title.isNotEmpty)
                  Text(
                    title,
                    style: AppTextStyles.titleMedium.copyWith(
                      color: scheme.onSurface,
                    ),
                  ),
                if (subtitle.isNotEmpty) ...[
                  SizedBox(height: 2.h),
                  Text(
                    subtitle,
                    style: AppTextStyles.label.copyWith(
                      fontWeight: FontWeight.w400,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
                SizedBox(height: 20.h),
                TweenAnimationBuilder<double>(
                  tween: Tween(begin: 0, end: progress),
                  duration: const Duration(milliseconds: 700),
                  curve: Curves.easeOutCubic,
                  builder: (context, animated, _) =>
                      _stepper(context, nodes, animated),
                ),
                SizedBox(height: 16.h),
              ],
            ),
          ),
          Center(
            child: InkWell(
              borderRadius: BorderRadius.circular(8.r),
              onTap: () => context.push(
                AppRoutes.orderTimeline,
                extra: {
                  'history': history,
                  'statusCode': statusCode,
                },
              ),
              child: Container(
                width: double.infinity,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  border: Border(
                    top: BorderSide(color: Colors.grey.withValues(alpha: 0.3)),
                  ),
                ),
                padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 8.h),
                child: Text(
                  context.tr('orderTrackSeeAllUpdates', fallback: 'See all updates'),
                  style: AppTextStyles.bodySmall.copyWith(
                    fontWeight: FontWeight.w600,
                    color: Theme.of(context).colorScheme.tertiary,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── stepper ──────────────────────────────────────────────────────────────

  Widget _stepper(BuildContext context, List<_TrackNode> nodes, double progress) {
    return Column(
      children: [
        Row(
          children: [
            for (var i = 0; i < nodes.length; i++) ...[
              _node(context, nodes[i]),
              if (i != nodes.length - 1)
                Expanded(
                  child: Padding(
                    padding: EdgeInsets.symmetric(horizontal: 2.w),
                    child: _connector(
                      (progress - i).clamp(0.0, 1.0),
                      nodes[i + 1].color,
                    ),
                  ),
                ),
            ],
          ],
        ),
        SizedBox(height: 8.h),
        _labelsRow(
          nodes,
          (n) => n.label,
          fontSize: 10.sp,
          colorFor: (n) => (n.done || n.current)
              ? const Color(0xFF1F2937)
              : _trackMuted,
          fontWeightFor: (n) => (n.done || n.current)
              ? FontWeight.w600
              : FontWeight.w400,
        ),
        SizedBox(height: 1.h),
      ],
    );
  }

  Widget _labelsRow(
    List<_TrackNode> nodes,
    String Function(_TrackNode) textOf, {
    required double fontSize,
    required Color Function(_TrackNode) colorFor,
    required FontWeight Function(_TrackNode) fontWeightFor,
  }) {
    return Row(
      children: [
        for (var i = 0; i < nodes.length; i++)
          Expanded(
            child: Text(
              textOf(nodes[i]),
              textAlign: _alignFor(i, nodes.length),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: fontSize,
                fontWeight: fontWeightFor(nodes[i]),
                color: colorFor(nodes[i]),
              ),
            ),
          ),
      ],
    );
  }

  Widget _node(BuildContext context, _TrackNode node) {
    if (node.done) {
      return Container(
        width: 18.w,
        height: 18.w,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: node.color, shape: BoxShape.circle),
        child: Icon(node.icon, size: 12.sp, color: Colors.white),
      );
    }
    if (node.current) {
      return Container(
        width: 18.w,
        height: 18.w,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          shape: BoxShape.circle,
          border: Border.all(
            color: node.color,
            width: 1.w,
          ),
        ),
        child: Container(
          width: 8.w,
          height: 8.w,
          decoration: BoxDecoration(
            color: node.color,
            shape: BoxShape.circle,
          ),
        ),
      );
    }
    return Container(
      width: 18.w,
      height: 18.w,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        shape: BoxShape.circle,
        border: Border.all(
          color: _trackHollowBorder,
          width: 1.5.w,
        ),
      ),
    );
  }

  Widget _connector(double fill, Color color) {
    final f = fill.clamp(0.0, 1.0);
    final greenFlex = (f * 1000).round();
    final greyFlex = 1000 - greenFlex;
    return SizedBox(
      height: 2.5.h,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(2.r),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (greenFlex > 0)
              Expanded(flex: greenFlex, child: ColoredBox(color: color)),
            if (greyFlex > 0)
              Expanded(flex: greyFlex, child: const ColoredBox(color: _trackInactive)),
          ],
        ),
      ),
    );
  }

  TextAlign _alignFor(int index, int length) {
    if (index == 0) return TextAlign.start;
    if (index == length - 1) return TextAlign.end;
    return TextAlign.center;
  }

  // ── data → nodes ─────────────────────────────────────────────────────────

  List<_TrackNode> _buildNodes(BuildContext context) {
    return stages.map((stage) => _nodeFor(context, stage)).toList();
  }

  _TrackNode _nodeFor(BuildContext context, Milestones stage) {
    final key = stage.key ?? '';
    return _TrackNode(
      key: key,
      label: _labelFor(context, stage),
      date: _formatTrackDate(stage.at),
      done: stage.done == true,
      current: stage.current == true,
      color: _colorForKey(context, key),
      icon: _iconForKey(key),
      stage: stage,
    );
  }

  String _labelFor(BuildContext context, Milestones stage) {
    if ((stage.label ?? '').trim().isNotEmpty) return stage.label!.trim();
    final key = (stage.key ?? '').toLowerCase();
    switch (key) {
      case 'placed':
        return context.tr('orderTrackStepPlaced', fallback: 'Placed');
      case 'confirmed':
        return context.tr('orderTrackStepConfirmed', fallback: 'Order Confirmed');
      case 'preparing':
        return context.tr('orderTrackStepPreparing', fallback: 'Preparing');
      case 'shipped':
        return context.tr('orderTrackStepShipped', fallback: 'Shipped');
      case 'delivered':
        return context.tr('orderTrackStepDelivery', fallback: 'Delivered');
      case 'cancelled':
      case 'canceled':
        return context.tr('orderTrackStepCancelled', fallback: 'Cancelled');
      case 'returned':
        return context.tr('orderTrackStepReturned', fallback: 'Returned');
      case 'refunded':
        return context.tr('orderTrackStepRefunded', fallback: 'Refunded');
      default:
        return stage.key ?? '';
    }
  }

  Color _colorForKey(BuildContext context, String key) {
    switch (key.toLowerCase()) {
      case 'cancelled':
      case 'canceled':
        return Theme.of(context).colorScheme.error;
      case 'returned':
        return context.appColors.warning;
      default:
        return _trackDone; // confirmed / shipped / delivered / refunded
    }
  }

  IconData _iconForKey(String key) {
    switch (key.toLowerCase()) {
      case 'cancelled':
      case 'canceled':
        return Icons.close_rounded;
      case 'returned':
        return Icons.keyboard_return_rounded;
      default:
        return Icons.check_rounded;
    }
  }

  /// Overall progress in "node units": leading full segments + the partial
  /// fraction of the first in-progress segment.
  double _progress(List<_TrackNode> nodes) {
    double p = 0;
    for (var i = 0; i < nodes.length - 1; i++) {
      final frac = _segmentFraction(nodes, i);
      if (frac >= 1.0) {
        p = (i + 1).toDouble();
      } else {
        return i + frac;
      }
    }
    return p;
  }

  double _segmentFraction(List<_TrackNode> nodes, int i) {
    if (i + 1 >= nodes.length) return 0.0;
    final nextNode = nodes[i + 1];

    // If next node is already completed
    if (nextNode.done) return 1.0;

    // If next node is the current active node
    if (nextNode.current) {
      final st = nextNode.stage;
      if (st != null && st.completedQty != null && (st.qty ?? 0) > 0 && st.completedQty! > 0) {
        return (st.completedQty! / st.qty!).clamp(0.0, 1.0);
      }
      return 0.0;
    }

    // If current node (i) is current and has partial quantity progress towards next node
    final currentNode = nodes[i];
    if (currentNode.current) {
      final st = currentNode.stage;
      if (st != null && st.completedQty != null && (st.qty ?? 0) > 0 && st.completedQty! > 0) {
        return (st.completedQty! / st.qty!).clamp(0.0, 1.0);
      }
    }

    return 0.0;
  }

  Milestones? _currentStage() {
    for (final s in stages) {
      if (s.current == true) return s;
    }
    Milestones? lastDone;
    for (final s in stages) {
      if (s.done == true) lastDone = s;
    }
    return lastDone ?? (stages.isNotEmpty ? stages.first : null);
  }

  Milestones? _lastDoneStage() {
    Milestones? lastDone;
    for (final s in stages) {
      if (s.done == true) lastDone = s;
    }
    return lastDone;
  }

  String _titleForStage(BuildContext context, Milestones? current) {
    if (current == null) return '';
    final key = (current.key ?? '').toLowerCase();

    // If stage is 'delivered' but not completed yet
    if (key == 'delivered' && current.done != true) {
      if (current.completedQty != null && current.completedQty! > 0 && (current.qty ?? 0) > 0) {
        return context.tr('orderTrackPartiallyDelivered', fallback: 'Partially Delivered');
      }
      return context.tr('orderTrackOnTheWay', fallback: 'On the way');
    }

    if ((current.label ?? '').trim().isNotEmpty) return current.label!.trim();
    return _labelFor(context, current);
  }

  String _statusSummary(BuildContext context, Milestones? current) {
    if (current == null) return '';
    final key = (current.key ?? '').toLowerCase();
    String text = '';
    switch (key) {
      case 'placed':
        text = context.tr('orderTrackSummaryPlaced', fallback: 'Your order has been placed');
        break;
      case 'confirmed':
        text = context.tr('orderTrackSummaryConfirmed', fallback: 'Your order is confirmed');
        break;
      case 'preparing':
        text = context.tr('orderTrackSummaryPreparing', fallback: 'Seller is preparing your order');
        break;
      case 'shipped':
        text = context.tr('orderTrackSummaryShipped', fallback: 'Your order is on the way');
        break;
      case 'delivered':
        if (current.done == true) {
          text = context.tr('orderTrackSummaryDelivered', fallback: 'Your order has been delivered');
        } else if (current.completedQty != null && current.completedQty! > 0 && (current.qty ?? 0) > 0) {
          text = context.tr(
            'orderTrackSummaryPartiallyDelivered',
            positionalArgs: [current.completedQty, current.qty],
            fallback: '${current.completedQty} of ${current.qty} items delivered',
          );
        } else {
          text = context.tr('orderTrackSummaryShipped', fallback: 'Your order is on the way');
        }
        break;
      case 'cancelled':
      case 'canceled':
        text = context.tr('orderTrackSummaryCancelled', fallback: 'Your order was cancelled');
        break;
      case 'returned':
        text = context.tr('orderTrackSummaryReturned', fallback: 'Your return is in progress');
        break;
      case 'refunded':
        text = context.tr('orderTrackSummaryRefunded', fallback: 'Your refund has been processed');
        break;
      default:
        text = (current.marker != null && current.marker!.trim().isNotEmpty) ? current.marker!.trim() : '';
    }

    // Format date: use current.at if present, otherwise fall back to last done milestone date
    String formattedDate = _formatTrackDate(current.at);
    if (formattedDate.isEmpty && current.done != true) {
      final lastDone = _lastDoneStage();
      if (lastDone != null) {
        formattedDate = _formatTrackDate(lastDone.at);
      }
    }

    if (text.isNotEmpty && formattedDate.isNotEmpty) {
      return '$text · $formattedDate';
    }
    return text.isNotEmpty ? text : formattedDate;
  }
}

class _TrackNode {
  final String key;
  final String label;
  final String date;
  final bool done;
  final bool current;
  final Color color;
  final IconData icon;
  final Milestones? stage;

  const _TrackNode({
    required this.key,
    required this.label,
    required this.date,
    required this.done,
    required this.current,
    required this.color,
    required this.icon,
    required this.stage,
  });
}

String _formatTrackDate(String? raw) {
  if (raw == null || raw.trim().isEmpty) return '';
  final dt = DateTime.tryParse(raw.trim());
  if (dt == null) return '';
  return DateFormat('MMM d').format(dt);
}
