import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';

import '../../../config/constant.dart';
import '../../../config/settings_data_instance.dart';
import '../../../model/selected_address/selected_address_model.dart';
import '../../../services/market/market_service.dart';
import '../../../utils/extensions/translation_extension.dart';
import '../../../utils/widgets/custom_snack_bar.dart';

/// Helper utility to handle product sharing reliably across Mobile and Tablet (iPad).
abstract class ProductShareHelper {
  static Future<void> shareProduct({
    required BuildContext context,
    required String productSlug,
    required String productTitle,
    SelectedAddress? selectedAddress,
  }) async {
    final countryCode =
        selectedAddress?.countryCode ?? MarketService.currentCountryIso2();

    String url = SettingsData.instance.web?.customerWebUrl ?? '';
    if (url.trim().isEmpty) {
      url = AppConstant.baseUrl.replaceFirst('/api/', '/');
    }
    if (!url.endsWith('/')) {
      url = '$url/';
    }

    final productLink =
        '${url}share/products/$productSlug?country_iso2=$countryCode';

    final l10n = context.tr;
    final cleanTitle = productTitle.trim();
    final leadText = l10n(
      'checkOutThisProduct',
      fallback: 'Check out this product!',
    );

    final shareText = cleanTitle.isNotEmpty
        ? '$leadText $cleanTitle $productLink'
        : '$leadText $productLink';

    // Compute origin rect for iPad / macOS share popover
    Rect? sharePositionOrigin;
    try {
      final box = context.findRenderObject() as RenderBox?;
      if (box != null && box.hasSize) {
        final position = box.localToGlobal(Offset.zero);
        final size = box.size;
        final mediaQuery = MediaQuery.maybeOf(context);
        if (mediaQuery != null) {
          final screen = mediaQuery.size;
          final left = position.dx
              .clamp(0.0, (screen.width - 20.0).clamp(0.0, double.infinity));
          final top = position.dy
              .clamp(0.0, (screen.height - 20.0).clamp(0.0, double.infinity));
          final width = size.width
              .clamp(1.0, (screen.width - left).clamp(1.0, double.infinity));
          final height = size.height
              .clamp(1.0, (screen.height - top).clamp(1.0, double.infinity));
          sharePositionOrigin = Rect.fromLTWH(left, top, width, height);
        } else {
          sharePositionOrigin = position & size;
        }
      }
    } catch (e) {
      debugPrint('Error getting sharePositionOrigin: $e');
    }

    // On iPad, sharePositionOrigin must be non-zero and within coordinate space
    if (sharePositionOrigin == null || sharePositionOrigin.isEmpty) {
      final mediaQuery = MediaQuery.maybeOf(context);
      if (mediaQuery != null) {
        final screen = mediaQuery.size;
        sharePositionOrigin = Rect.fromCenter(
          center: Offset(screen.width / 2, screen.height / 2),
          width: 20,
          height: 20,
        );
      }
    }

    try {
      await SharePlus.instance.share(
        ShareParams(
          text: shareText.trim(),
          title: cleanTitle.isNotEmpty ? cleanTitle : 'Product',
          subject: cleanTitle.isNotEmpty ? cleanTitle : 'Product',
          sharePositionOrigin: sharePositionOrigin,
        ),
      );
    } catch (e, stack) {
      debugPrint('Error sharing via SharePlus: $e\n$stack');
      try {
        // ignore: deprecated_member_use
        await Share.share(
          shareText.trim(),
          subject: cleanTitle.isNotEmpty ? cleanTitle : 'Product',
          sharePositionOrigin: sharePositionOrigin,
        );
      } catch (fallbackError) {
        debugPrint('Fallback Share.share failed: $fallbackError');
        if (context.mounted) {
          CustomSnackBar.show(
            context: context,
            message: context.tr(
              'somethingWentWrong',
              fallback: 'Unable to share product at this time',
            ),
            type: CustomSnackBarType.error,
          );
        }
      }
    }
  }
}
