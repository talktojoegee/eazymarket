import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import '../../../../services/address/selected_address_hive.dart';
import '../../../../services/location/location_service.dart';
import '../../../../services/location/user_location_hive.dart';
import '../../../../utils/widgets/address_bottom_sheet.dart';


class HomeTopAddressSection extends StatelessWidget {
  final Color? textColor;

  const HomeTopAddressSection({
    super.key,
    required this.textColor,
  });

  String _resolveLabel(BuildContext context) {
    final selected = HiveSelectedAddressHelper.getSelectedAddress();

    if (selected != null) {
      final addressLine = [
        selected.addressLine1,
        selected.landmark,
        selected.city,
      ]
          .where((e) => e.toString().trim().isNotEmpty)
          .join(', ');
      final line = _firstNonEmpty([selected.addressLine1, selected.city]);
      if (line != null) return addressLine;
    }
    final location = LocationService.getStoredLocation();
    if (location != null) {
      final addressLine = [
        location.fullAddress,
        location.area,
        location.city,
        location.state,
      ]
          .where((e) => e.toString().trim().isNotEmpty)
          .join(', ');

      log('Address Line $addressLine');
      if (addressLine.isNotEmpty) return addressLine;
    }
    if (location != null) {
      final line = _firstNonEmpty(
          [location.fullAddress, location.area, location.city, location.state]);
      if (line != null) return line;
    }
    return context.tr('selectDeliveryLocation', fallback: 'Select Delivery Location');
  }

  String? _firstNonEmpty(List<String?> values) {
    for (final v in values) {
      if (v != null && v.trim().isNotEmpty) return v.trim();
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {

    return ListenableBuilder(
      listenable: Listenable.merge([
        HiveSelectedAddressHelper.listenable(),
        HiveLocationHelper.currentLocationListenable(),
      ]),
      builder: (context, _) {
        final label = _resolveLabel(context);
        return GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () => AddressBottomSheet.show(context),
          child: Container(
            // constraints: BoxConstraints(maxWidth: 250.w),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              color: Colors.transparent
              // color: Colors.black.withValues(alpha: 0.1)
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(TablerIcons.map_pin_filled, size: 18, color: textColor),
                const SizedBox(width: 5),
                Flexible(
                  child: Text(
                    label,
                    style: AppTextStyles.bodySmall.copyWith(
                      color: textColor,
                      height: 1.1,
                      fontWeight: FontWeight.w500,
                    ),
                    overflow: TextOverflow.ellipsis,
                    maxLines: 1,
                  ),
                ),
                const SizedBox(width: 4),
                Icon(
                  TablerIcons.chevron_down,
                  size: 14,
                  color: textColor,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
