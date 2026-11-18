import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';

import '../config/theme.dart';

class CustomRefreshIndicator extends StatelessWidget {
  final Widget child;
  final Future<void> Function() onRefresh;

  const CustomRefreshIndicator({
    super.key,
    required this.child,
    required this.onRefresh
  });

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
        color: AppColors.primaryColor,
        onRefresh: onRefresh,
        child: child
    );
  }
}