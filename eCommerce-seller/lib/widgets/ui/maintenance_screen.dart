// ignore_for_file: deprecated_member_use

import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';

/// Full screen widget displayed when app is under maintenance
class MaintenanceScreen extends StatelessWidget {
  final String? message;

  const MaintenanceScreen({super.key, this.message});

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
                  ImagesPath.underMaintenanceSvg,
                  width: 220,
                  height: 220,
                ),
                const SizedBox(height: 32),
                Text(
                  context.tr('appUnderMaintenance'),
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    fontFamily: AppConstants.fontFamily,
                    color: theme.colorScheme.onSurface,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),
                Text(
                  message ?? context.tr('maintenanceMessage'),
                  style: TextStyle(
                    fontSize: 14,
                    fontFamily: AppConstants.fontFamily,
                    color: theme.colorScheme.onSurface.withValues(alpha:0.7),
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
