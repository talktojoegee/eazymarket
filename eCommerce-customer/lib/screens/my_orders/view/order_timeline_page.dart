import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:hyper_commerce/utils/widgets/custom_scaffold/custom_scaffold.dart';
import '../model/tracking.dart';
import '../widgets/order_timeline_widget.dart';

class OrderTimelinePage extends StatelessWidget {
  final List<History> timeline;
  final String? statusCode;

  const OrderTimelinePage({
    super.key,
    required this.timeline,
    this.statusCode,
  });

  @override
  Widget build(BuildContext context) {
    log('Hello TimeLine $timeline, statusCode: $statusCode');
    return CustomScaffold(
      showAppBar: true,
      title: context.tr('orderTimelineTitle', fallback: 'Order Updates'),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 24.h),
          child: OrderTimelineWidget(
            stages: timeline,
            statusCode: statusCode,
          ),
        ),
      ),
    );
  }
}
