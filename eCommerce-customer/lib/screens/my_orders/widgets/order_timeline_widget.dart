import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../model/tracking.dart';

Color _timelineDone = AppTheme.orderTrackMainColor;
const Color _timelineException = Color(0xFFF59F00);
const Color _timelinePending = Color(0xFFD9D9D9);
const Color _timelinePendingText = Color(0xFF9AA0A6);
const Color _timelineHollowBorder = Color(0xFFCBD5E1);

class OrderTimelineWidget extends StatelessWidget {
  final List<History> stages;
  final String? statusCode;

  const OrderTimelineWidget({
    super.key,
    required this.stages,
    this.statusCode,
  });

  @override
  Widget build(BuildContext context) {
    if (stages.isEmpty) {
      return Center(
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: 40.h),
          child: Text(
            context.tr('noTimelineUpdates', fallback: 'No updates available yet'),
            style: AppTextStyles.body.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ),
      );
    }

    final currentIndex = _findCurrentIndex();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < stages.length; i++)
          _TimelineStageRow(
            stage: stages[i],
            isFirst: i == 0,
            isLast: i == stages.length - 1,
            isCurrent: i == currentIndex,
            isPassed: (stages[i].done == true) || (currentIndex >= 0 && i < currentIndex),
            topRailColor: i == 0 ? Colors.transparent : _topRailColor(i, currentIndex),
            bottomRailColor: i == stages.length - 1 ? Colors.transparent : _bottomRailColor(i, currentIndex),
          ),
      ],
    );
  }

  int _findCurrentIndex() {
    if (statusCode != null && statusCode!.trim().isNotEmpty) {
      final code = statusCode!.trim().toLowerCase();
      for (var i = 0; i < stages.length; i++) {
        if ((stages[i].code ?? '').trim().toLowerCase() == code) {
          return i;
        }
      }
    }
    // Fallback: first not done stage, or last done stage
    for (var i = 0; i < stages.length; i++) {
      if (stages[i].done != true) return i;
    }
    return stages.isNotEmpty ? stages.length - 1 : -1;
  }

  Color _topRailColor(int i, int currentIndex) {
    // Top line connects from stage i - 1 to stage i.
    // If stage i is reached (done, or strictly before currentIndex), it is green.
    final stage = stages[i];
    final isReached = (stage.done == true) || (currentIndex >= 0 && i < currentIndex);
    return isReached ? _timelineDone : _timelinePending;
  }

  Color _bottomRailColor(int i, int currentIndex) {
    // Bottom line connects from stage i to stage i + 1.
    // If stage i + 1 is reached (done, or strictly before currentIndex), it is green.
    if (i + 1 >= stages.length) return Colors.transparent;
    final nextStage = stages[i + 1];
    final isNextReached = (nextStage.done == true) || (currentIndex >= 0 && i + 1 < currentIndex);
    return isNextReached ? _timelineDone : _timelinePending;
  }
}

class _TimelineStageRow extends StatelessWidget {
  final History stage;
  final bool isFirst;
  final bool isLast;
  final bool isCurrent;
  final bool isPassed;
  final Color topRailColor;
  final Color bottomRailColor;

  const _TimelineStageRow({
    required this.stage,
    required this.isFirst,
    required this.isLast,
    required this.isCurrent,
    required this.isPassed,
    required this.topRailColor,
    required this.bottomRailColor,
  });

  bool get _isException =>
      stage.isException == true || (stage.code ?? '').toLowerCase() == 'exception';

  bool get _isReached => isPassed || isCurrent;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final formattedDate = _formatHistoryDate(stage.at);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Rail & Dot column
          SizedBox(
            width: 24.w,
            child: Column(
              children: [
                // Top connecting line
                Container(
                  width: 2.w,
                  height: isFirst ? 0 : 4.h,
                  color: topRailColor,
                ),
                // Indicator Dot / Ring
                _buildDot(context, scheme),
                // Bottom connecting line
                Expanded(
                  child: Container(
                    width: 2.w,
                    color: bottomRailColor,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 12.w),
          // Content column
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 8.h : 20.h),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(
                        child: Text(
                          stage.label ?? stage.code ?? '',
                          style: AppTextStyles.body.copyWith(
                            fontWeight: isCurrent
                                ? FontWeight.w600
                                : (_isReached ? FontWeight.w500 : FontWeight.w400),
                            color: _isReached ? scheme.onSurface : _timelinePendingText,
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (formattedDate.isNotEmpty) ...[
                    SizedBox(height: 3.h),
                    Text(
                      formattedDate,
                      style: AppTextStyles.label.copyWith(
                        color: _isReached
                            ? scheme.onSurfaceVariant.withValues(alpha: 0.65)
                            : _timelinePendingText.withValues(alpha: 0.7),
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                  ],
                  if (stage.meta != null)
                    _buildMetaInfo(context, stage.meta!, scheme),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDot(BuildContext context, ColorScheme scheme) {
    if (_isException) {
      return Container(
        width: 18.w,
        height: 18.w,
        alignment: Alignment.center,
        decoration: const BoxDecoration(
          color: _timelineException,
          shape: BoxShape.circle,
        ),
        child: Icon(Icons.priority_high_rounded, size: 12.sp, color: Colors.white),
      );
    }

    if (isPassed && !isCurrent) {
      return Container(
        width: 18.w,
        height: 18.w,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: _timelineDone,
          shape: BoxShape.circle,
        ),
        child: Icon(Icons.check_rounded, size: 12.sp, color: Colors.white),
      );
    }

    if (isCurrent) {
      if (stage.done == true) {
        return Container(
          width: 18.w,
          height: 18.w,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: _timelineDone,
            shape: BoxShape.circle,
          ),
          child: Icon(Icons.check_rounded, size: 12.sp, color: Colors.white),
        );
      }
      return Container(
        width: 18.w,
        height: 18.w,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: scheme.surface,
          shape: BoxShape.circle,
          border: Border.all(
            color: _timelineDone,
            width: 2.5.w,
          ),
        ),
        child: Container(
          width: 7.w,
          height: 7.w,
          decoration: BoxDecoration(
            color: _timelineDone,
            shape: BoxShape.circle,
          ),
        ),
      );
    }

    // Pending stage
    return Container(
      width: 18.w,
      height: 18.w,
      decoration: BoxDecoration(
        color: scheme.surface,
        shape: BoxShape.circle,
        border: Border.all(
          color: _timelineHollowBorder,
          width: 1.5.w,
        ),
      ),
    );
  }

  Widget _buildMetaInfo(BuildContext context, Meta meta, ColorScheme scheme) {
    final shipmentId = meta.shipmentId;
    final courier = meta.courier?.trim() ?? '';
    final trackingId = meta.trackingId?.trim() ?? '';
    final trackingUrl = meta.trackingUrl?.trim() ?? '';
    final statusNote = meta.status?.trim() ?? '';
    final qty = meta.qty;

    final hasCourierInfo = courier.isNotEmpty || trackingId.isNotEmpty;
    final hasContent = hasCourierInfo || trackingUrl.isNotEmpty || statusNote.isNotEmpty || (qty != null && qty > 0);
    if (!hasContent) return const SizedBox.shrink();

    return Container(
      margin: EdgeInsets.only(top: 4.h),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (hasCourierInfo) ...[
            Row(
              children: [
                Expanded(
                  child: Text.rich(
                    TextSpan(
                      children: [
                        if (shipmentId != null && shipmentId > 0)
                          TextSpan(
                            text: '${context.tr('shipments', fallback: 'Shipments')} #$shipmentId',
                            style: AppTextStyles.label.copyWith(
                              fontWeight: FontWeight.w400,
                              color: _isReached
                                  ? scheme.onSurfaceVariant.withValues(alpha: 0.65)
                                  : _timelinePendingText.withValues(alpha: 0.7),
                            ),
                          ),
                        if((shipmentId != null && shipmentId > 0) && courier.isNotEmpty)
                          TextSpan(
                            text: '  ·  ',
                            style: TextStyle(color: scheme.tertiary.withValues(alpha: 0.8)),
                          ),
                        if (courier.isNotEmpty)
                          TextSpan(
                            text: courier,
                            style: AppTextStyles.label.copyWith(
                              fontWeight: FontWeight.w400,
                              color: _isReached
                                  ? scheme.onSurfaceVariant.withValues(alpha: 0.65)
                                  : _timelinePendingText.withValues(alpha: 0.7),
                            ),
                          ),
                        if (courier.isNotEmpty && trackingId.isNotEmpty)
                          TextSpan(
                            text: '  ·  ',
                            style: AppTextStyles.label.copyWith(color: scheme.tertiary.withValues(alpha: 0.6)),
                          ),
                        if (trackingId.isNotEmpty)
                          TextSpan(
                            text: trackingId,
                            style: AppTextStyles.label.copyWith(
                              fontWeight: FontWeight.w400,
                              color: _isReached
                                  ? scheme.onSurfaceVariant.withValues(alpha: 0.65)
                                  : _timelinePendingText.withValues(alpha: 0.7),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ],
          if (qty != null && qty > 0) ...[
            SizedBox(height: 2.h),
            Text(
              context.tr('itemQuantity', positionalArgs: [qty], fallback: 'Quantity: $qty'),
              style: AppTextStyles.label.copyWith(
                color: _isReached
                    ? scheme.onSurfaceVariant.withValues(alpha: 0.65)
                    : _timelinePendingText.withValues(alpha: 0.7),
                fontWeight: FontWeight.w400,
              ),
            ),
          ],
          if (trackingUrl.isNotEmpty) ...[
            SizedBox(height: 4.h),
            InkWell(
              onTap: () async {
                final uri = Uri.tryParse(trackingUrl);
                if (uri != null && await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
              borderRadius: BorderRadius.circular(4.r),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    context.tr('trackPackage', fallback: 'Track package'),
                    style: AppTextStyles.label.copyWith(
                      fontWeight: FontWeight.w600,
                      color: scheme.tertiary,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                  SizedBox(width: 4.w),
                  Icon(
                    Icons.open_in_new_rounded,
                    size: 12.sp,
                    color: scheme.primary,
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

String _ordinalSuffix(int day) {
  if (day >= 11 && day <= 13) return 'th';
  switch (day % 10) {
    case 1:
      return 'st';
    case 2:
      return 'nd';
    case 3:
      return 'rd';
    default:
      return 'th';
  }
}

DateTime? _tryParseDate(String? raw) {
  if (raw == null || raw.trim().isEmpty) return null;
  return DateTime.tryParse(raw.trim());
}

String _formatHistoryDate(String? raw) {
  final dt = _tryParseDate(raw);
  if (dt == null) return '';
  final local = dt.toLocal();
  final weekday = DateFormat('EEE').format(local);
  final month = DateFormat('MMM').format(local);
  final year = DateFormat('yy').format(local);
  final time = DateFormat('h:mm a').format(local);
  return "$weekday, ${local.day}${_ordinalSuffix(local.day)} $month '$year · $time";
}
