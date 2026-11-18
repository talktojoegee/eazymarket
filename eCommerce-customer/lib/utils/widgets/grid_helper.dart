import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../config/helper.dart';

SliverGridDelegate sliverGridDelegate(
    BuildContext context,
    {double? crossAxisSpacing,
        double? mainAxisSpacing,
        double? mainAxisExtent,
      double? childAspectRatio,
      double? maxCrossAxisExtent,
    }) => isTablet(context) ? SliverGridDelegateWithMaxCrossAxisExtent(
  maxCrossAxisExtent: maxCrossAxisExtent ?? 200,
  crossAxisSpacing: crossAxisSpacing ?? 12.w,
  mainAxisSpacing: mainAxisSpacing ?? 12.h,
  mainAxisExtent: mainAxisExtent ?? 200.h,
) : SliverGridDelegateWithFixedCrossAxisCount(
  crossAxisCount: 3,
  crossAxisSpacing: crossAxisSpacing ?? 12.w,
  mainAxisSpacing: mainAxisSpacing ?? 12.h,
  mainAxisExtent: mainAxisExtent ?? 225.h,
  childAspectRatio: childAspectRatio ?? 1.0
);
