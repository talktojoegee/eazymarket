// ignore_for_file: deprecated_member_use

import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';

/// Full screen widget displayed when there is no internet connection
class NoInternetScreen extends StatelessWidget {
  final VoidCallback? onRetry;

  const NoInternetScreen({super.key, this.onRetry});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    
    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                SvgPicture.asset(
                  ImagesPath.noInternetSvg,
                  width: 200,
                  height: 200,
                ),
                const SizedBox(height: 32),
                Text(
                  context.tr('noInternetConnection'),
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    fontFamily: AppConstants.fontFamily,
                    color: theme.colorScheme.onSurface,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),
                Text(
                  context.tr('noInternetMessage'),
                  style: TextStyle(
                    fontSize: 14,
                    fontFamily: AppConstants.fontFamily,
                    color: theme.colorScheme.onSurface.withValues(alpha:0.7),
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 32),
                if (onRetry != null)
                  PrimaryButton(
                    text: context.tr('retry'),
                    onPressed: onRetry!,
                    width: 160,
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
