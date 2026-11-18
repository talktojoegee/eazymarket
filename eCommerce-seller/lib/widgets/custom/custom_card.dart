import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/widgets/attribute_card.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/widgets/attribute_value_card.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/brands/widgets/brand_card.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/categories/widgets/category_card.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/widgets/roles_card.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/widgets/store_card.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/system_users/widgets/system_user_card.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/tax_group/widgets/tax_group_card.dart';
import 'package:hyper_commerce_seller/screen/order_page/widgets/order_card.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/bloc/products_bloc/products_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/widgets/product_card.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/widgets/faq_card.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_drop_menu.dart';

import '../../screen/products_page/add_ons/addon_groups/widgets/addon_group_card.dart';
import '../../screen/products_page/product_addons/product_addons_group/widgets/Product_addon_card.dart';
import '../../screen/more_page/view/store_addon_inventory/store_addon_list/widgets/store_addon_body.dart';
import 'drop_menu_controller.dart';

enum CardType {
  brand,
  category,
  taxGroup,
  product,
  store,
  attribute,
  attributeValue,
  order,
  faq,
  roles,
  systemUser,
  addonGroup,
  productAddOn,
  inventoryAddOn
}

class CustomCard extends StatelessWidget {
  final CardType type;
  final Map<String, dynamic> data;
  final ScreenType screenType;

  final VoidCallback? onEdit;
  final VoidCallback? onDelete;
  final Function(String)? onToggleStatus;
  final VoidCallback? onTap;
  final Widget? extraWidgets;

  const CustomCard({
    super.key,
    required this.type,
    required this.data,
    required this.screenType,
    this.onEdit,
    this.onDelete,
    this.onToggleStatus,
    this.onTap,
    this.extraWidgets,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: EdgeInsets.only(bottom: UIUtils.gapMD(screenType)),
        padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
        decoration: BoxDecoration(
          color: isDark
              ? Theme.of(context).colorScheme.surfaceContainer
              : Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
          border: Border.all(
            color: Theme.of(context).colorScheme.outlineVariant,
            width: 0.5,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildModernHeader(context),

            SizedBox(height: UIUtils.gapMD(screenType)),

            _buildBody(context),

            if (extraWidgets != null) ...[
              SizedBox(height: UIUtils.gapMD(screenType)),
              extraWidgets!,
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    switch (type) {
      case CardType.brand:
        return BrandCard(data: data, screenType: screenType);
      case CardType.category:
        return CategoryCard(data: data, screenType: screenType);
      case CardType.taxGroup:
        return TaxGroupCard(data: data, screenType: screenType);
      case CardType.product:
        return ProductCard(data: data, screenType: screenType);
      case CardType.store:
        return StoreCard(data: data, screenType: screenType);
      case CardType.attribute:
        return AttributeCard(data: data, screenType: screenType);
      case CardType.attributeValue:
        return AttributeValueCard(data: data, screenType: screenType);
      case CardType.order:
        return OrderCard(data: data, screenType: screenType, onStatusUpdate: onToggleStatus);
      case CardType.faq:
        return FaqCard(data: data, screenType: screenType);
      case CardType.roles:
        return RolesCard(data: data, screenType: screenType);
      case CardType.systemUser:
        return SystemUserCard(data: data, screenType: screenType);
      case CardType.addonGroup:
        return AddonGroupCard(data: data, screenType: screenType);
      case CardType.productAddOn:
        return ProductAddonCard(data: data, screenType: screenType);
      case CardType.inventoryAddOn:
        return StoreAddonBody(data: data, screenType: screenType);
    }
  }

  Widget _buildModernHeader(BuildContext context) {
    final status = data['fulfillment_status']?.toString().toLowerCase();

    Color bgColor;
    Color textColor;

    switch (status) {
      case 'active':
        bgColor = Colors.green.withValues(alpha: 0.1);
        textColor = Colors.green;
        break;
      case 'cancelled':
        bgColor = Colors.red.withValues(alpha: 0.1);
        textColor = Colors.red;
        break;
      default:
        bgColor = Colors.grey.withValues(alpha: 0.1);
        textColor = Colors.orangeAccent;
    }

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(
            color: Colors.grey.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            "Order #${data['id'] ?? ''}",
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),

        // STATUS CHIP
        if(status != null || onEdit != null || onDelete != null)
          if (status != null && onEdit == null || onDelete == null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
              decoration: BoxDecoration(
                color: bgColor,
                borderRadius: BorderRadius.circular(7),
              ),
              child: Text(
                capitalizeWords(status ?? ''),
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                  color: textColor,
                ),
              ),
            ),

        // Keep menu if needed
        if (onEdit != null || onDelete != null)
          Padding(
            padding: const EdgeInsets.only(left: 6),
            child: CustomDropMenu(
              items: [
                MenuItem(
                  label: context.tr('edit'),
                  icon: Icons.edit,
                  onTap: () => onEdit!(),
                ),
                MenuItem(
                  label: context.tr('delete'),
                  icon: Icons.delete,
                  onTap: () => onDelete!(),
                  textColor: Colors.red,
                  iconColor: Colors.red,
                ),
              ],
              dotsColor: Colors.grey.shade500,
            ),
          ),
      ],
    );
  }
}

String capitalizeWords(String text) {
  return text.split(' ').map((word) => word.isEmpty ? word : word[0].toUpperCase() + word.substring(1)).join(' ');
}
