import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_card.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_drop_menu.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/bloc/products_bloc/products_bloc.dart';

class ProductCard extends StatelessWidget {
  final Map<String, dynamic> data;
  final ScreenType screenType;

  const ProductCard({
    super.key,
    required this.data,
    required this.screenType,
  });

  @override
  Widget build(BuildContext context) {
    final price = (data['price'] ?? 0).toString();
    final specialPrice = (data['special_price'] ?? '').toString();
    final hasSpecialPrice = specialPrice.isNotEmpty && specialPrice != price;

    final hasImage =
        data['image'] != null && data['image'].toString().isNotEmpty;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (hasImage) ...[
            _buildImage(),
            SizedBox(width: UIUtils.gapMD(screenType)),
          ],

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                /// TOP ROW
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        data['name'] ?? '',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: UIUtils.body(screenType),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),

                    const SizedBox(width: 8),

                    if (data['status'] != null)
                      _buildStatusChip(
                        context,
                        data['status'],
                        isActive: data['status'] == 'active',
                      ),
                  ],
                ),

                SizedBox(height: UIUtils.gapXS(screenType)),

                /// CATEGORY
                if (data['category'] != null)
                  Text(
                    data['category'],
                    style: TextStyle(
                      fontSize: UIUtils.caption(screenType),
                      color: Colors.grey.shade600,
                    ),
                  ),

                SizedBox(height: UIUtils.gapSM(screenType)),

                /// PRICE ROW
                Row(
                  children: [
                    if (hasSpecialPrice)
                      Text(
                        "${HiveStorage.currencySymbol}$price",
                        style: TextStyle(
                          fontSize: UIUtils.caption(screenType),
                          color: Colors.grey,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),

                    if (hasSpecialPrice)
                      const SizedBox(width: 8),

                    Text(
                      "${HiveStorage.currencySymbol}${hasSpecialPrice ? specialPrice : price}",
                      style: TextStyle(
                        fontSize: UIUtils.body(screenType),
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildImage() {
    return Container(
      width: UIUtils.avatarXL(screenType),
      height: UIUtils.avatarXL(screenType),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        color: Colors.grey.shade100,
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: CachedNetworkImage(
          imageUrl: data['image'] ?? '',
          fit: BoxFit.cover,
        ),
      ),
    );
  }

  Widget _buildStatusChip(
      BuildContext context,
      String label, {
        bool isActive = false,
      }) {

    final bgColor = isActive
        ? Colors.green.withValues(alpha: 0.1)
        : Colors.orange.withValues(alpha: 0.1);

    final textColor = isActive ? Colors.green : Colors.orange;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(20), // pill style
      ),
      child: Text(
        context.translateEnum(label),
        style: TextStyle(
          fontSize: UIUtils.caption(screenType),
          fontWeight: FontWeight.w600,
          color: textColor,
        ),
      ),
    );
  }
}


/*
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_card.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_drop_menu.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/bloc/products_bloc/products_bloc.dart';

class ProductCard extends StatelessWidget {
  final Map<String, dynamic> data;
  final ScreenType screenType;

  const ProductCard({super.key, required this.data, required this.screenType});

  @override
  Widget build(BuildContext context) {
    final price = (data['price'] ?? 0).toString();
    final specialPrice = (data['special_price'] ?? '').toString();
    final hasSpecialPrice = specialPrice.isNotEmpty && specialPrice != price;

    final hasImage =
        data['image'] != null && data['image'].toString().isNotEmpty;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (hasImage) ...[
          _buildImage(),
          SizedBox(width: UIUtils.gapMD(screenType)),
        ],
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      data['name'] ?? '',
                      style: TextStyle(
                        fontSize: UIUtils.tileTitle(screenType),
                        fontWeight: UIUtils.bold,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (data['rating'] != null && (data['rating'] as num) > 0)
                    Row(
                      children: [
                        const Icon(
                          Icons.star_rounded,
                          color: Colors.orange,
                          size: 16,
                        ),
                        const SizedBox(width: 2),
                        Text(
                          data['rating'].toString(),
                          style: TextStyle(
                            fontSize: UIUtils.caption(screenType),
                            fontWeight: UIUtils.bold,
                          ),
                        ),
                      ],
                    ),
                ],
              ),
              SizedBox(height: UIUtils.gapXS(screenType)),
              if (data['category'] != null)
                Text(
                  data['category'],
                  style: TextStyle(
                    fontSize: UIUtils.body(screenType),
                    color: Colors.grey.shade600,
                  ),
                ),
              SizedBox(height: UIUtils.gapSM(screenType)),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        if (hasSpecialPrice)
                          Text(
                            "${HiveStorage.currencySymbol}$price",
                            style: TextStyle(
                              fontSize: UIUtils.caption(screenType),
                              color: Colors.grey,
                              decoration: TextDecoration.lineThrough,
                              decorationColor: AppColors.primaryColor
                                  .withValues(alpha: 10),
                              decorationThickness: 0.7,
                            ),
                          ),
                        SizedBox(width: UIUtils.gapMD(screenType)),
                        Text(
                          "${HiveStorage.currencySymbol}${hasSpecialPrice ? specialPrice : price}",
                          style: TextStyle(
                            fontSize: UIUtils.body(screenType),
                            fontWeight: UIUtils.bold,
                            color: AppColors.primaryColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (data['status'] != null)
                    _buildStatusChip(
                      context,
                      data['status'],
                      isActive: data['status'] == 'active',
                    ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildImage() {
    return ClipRRect(
      borderRadius: BorderRadius.circular(UIUtils.radiusSM(screenType)),
      child: CachedNetworkImage(
        imageUrl: data['image'] ?? 'https://via.placeholder.com/150',
        width: UIUtils.avatarXL(screenType),
        height: UIUtils.avatarXL(screenType),
        fit: BoxFit.cover,
        placeholder: (context, url) => Container(color: Colors.grey.shade100),
        errorWidget: (context, url, error) => Container(
          color: Colors.grey.shade200,
          child: const Icon(Icons.inventory_2, color: Colors.grey),
        ),
      ),
    );
  }

  Widget _buildStatusChip(
    BuildContext context,
    String label, {
    bool isActive = false,
  }) {
    final bgColor = isActive ? Colors.green.shade50 : Colors.orange.shade50;
    final textColor = isActive ? Colors.green.shade800 : Colors.orange.shade800;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        context.translateEnum(label),
        style: TextStyle(
          fontSize: UIUtils.caption(screenType),
          fontWeight: FontWeight.w600,
          color: textColor,
        ),
      ),
    );
  }
}
*/
