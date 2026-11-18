import 'package:flutter/material.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_detail_model.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';

class CompareAttributeRow {
  final String title;
  final String valueA;
  final String valueB;
  final bool isDifferent;
  final int? winnerIndex; // 1 for A, 2 for B, null for neutral/tie
  final String? badgeA;
  final String? badgeB;

  const CompareAttributeRow({
    required this.title,
    required this.valueA,
    required this.valueB,
    required this.isDifferent,
    this.winnerIndex,
    this.badgeA,
    this.badgeB,
  });
}

class CompareSectionGroup {
  final String sectionTitle;
  final List<CompareAttributeRow> rows;

  const CompareSectionGroup({
    required this.sectionTitle,
    required this.rows,
  });
}

class ProductCompareEngine {
  static List<CompareSectionGroup> buildComparison({
    required BuildContext context,
    required ProductData productA,
    required ProductData productB,
  }) {
    final groups = <CompareSectionGroup>[];

    final defaultVariantA = productA.variants.firstWhere(
      (v) => v.isDefault,
      orElse: () => productA.variants.isNotEmpty ? productA.variants.first : ProductVariants(),
    );
    final defaultVariantB = productB.variants.firstWhere(
      (v) => v.isDefault,
      orElse: () => productB.variants.isNotEmpty ? productB.variants.first : ProductVariants(),
    );

    final currency = defaultVariantA.currencySymbol.isNotEmpty
        ? defaultVariantA.currencySymbol
        : (defaultVariantB.currencySymbol.isNotEmpty ? defaultVariantB.currencySymbol : '₹');

    // ── 1. Delivery & Services (Top Section above Highlights) ────────────────
    final deliveryA = productA.estimatedDeliveryTime.trim();
    final deliveryB = productB.estimatedDeliveryTime.trim();

    final deliveryRows = <CompareAttributeRow>[
      CompareAttributeRow(
        title: context.tr('delivery', fallback: 'Delivery'),
        valueA: deliveryA.isNotEmpty ? 'Delivery in $deliveryA\nFree' : 'Standard Delivery\nFree',
        valueB: deliveryB.isNotEmpty ? 'Delivery in $deliveryB\nFree' : 'Standard Delivery\nFree',
        isDifferent: deliveryA != deliveryB,
      ),
      CompareAttributeRow(
        title: context.tr('cashOnDelivery', fallback: 'Cash On Delivery'),
        valueA: productA.allowCod ? 'Cash On Delivery Available' : 'N/A',
        valueB: productB.allowCod ? 'Cash On Delivery Available' : 'N/A',
        isDifferent: productA.allowCod != productB.allowCod,
      ),
      CompareAttributeRow(
        title: context.tr('returnPolicy', fallback: 'Return Policy'),
        valueA: productA.isReturnable
            ? (productA.returnableDays > 0 ? '${productA.returnableDays} Days Replacement' : 'Replacement Policy')
            : 'Non-Returnable',
        valueB: productB.isReturnable
            ? (productB.returnableDays > 0 ? '${productB.returnableDays} Days Replacement' : 'Replacement Policy')
            : 'Non-Returnable',
        isDifferent: productA.isReturnable != productB.isReturnable ||
            productA.returnableDays != productB.returnableDays,
      ),
    ];
    groups.add(CompareSectionGroup(
      sectionTitle: '', // Empty means top section without header banner
      rows: deliveryRows,
    ));

    // ── 2. Highlights Section (Flipkart style) ──────────────────────────────
    final brandA = productA.brandName.trim().isNotEmpty
        ? productA.brandName.trim()
        : productA.brand.trim();
    final brandB = productB.brandName.trim().isNotEmpty
        ? productB.brandName.trim()
        : productB.brand.trim();

    final ratingA = double.tryParse(productA.ratings.toString()) ?? 0.0;
    final ratingB = double.tryParse(productB.ratings.toString()) ?? 0.0;

    final priceA = defaultVariantA.specialPrice > 0
        ? defaultVariantA.specialPrice
        : defaultVariantA.price;
    final priceB = defaultVariantB.specialPrice > 0
        ? defaultVariantB.specialPrice
        : defaultVariantB.price;
    final origPriceA = defaultVariantA.price;
    final origPriceB = defaultVariantB.price;

    final discountA = origPriceA > priceA
        ? (((origPriceA - priceA) / origPriceA) * 100).round()
        : 0;
    final discountB = origPriceB > priceB
        ? (((origPriceB - priceB) / origPriceB) * 100).round()
        : 0;

    final highlightRows = <CompareAttributeRow>[
      if (brandA.isNotEmpty || brandB.isNotEmpty)
        CompareAttributeRow(
          title: context.tr('brand', fallback: 'Brand'),
          valueA: brandA.isNotEmpty ? brandA : 'N/A',
          valueB: brandB.isNotEmpty ? brandB : 'N/A',
          isDifferent: brandA.toLowerCase() != brandB.toLowerCase(),
        ),
      if (ratingA > 0 || ratingB > 0)
        CompareAttributeRow(
          title: context.tr('rating', fallback: 'Rating'),
          valueA: ratingA > 0 ? '$ratingA ★ (${productA.ratingCount} reviews)' : 'N/A',
          valueB: ratingB > 0 ? '$ratingB ★ (${productB.ratingCount} reviews)' : 'N/A',
          isDifferent: ratingA != ratingB,
        ),
      if (discountA > 0 || discountB > 0)
        CompareAttributeRow(
          title: context.tr('discount', fallback: 'Special Offer'),
          valueA: discountA > 0 ? '$discountA% OFF' : 'Standard Price',
          valueB: discountB > 0 ? '$discountB% OFF' : 'Standard Price',
          isDifferent: discountA != discountB,
        ),
      CompareAttributeRow(
        title: context.tr('stockStatus', fallback: 'Availability'),
        valueA: defaultVariantA.isInStock ? 'In Stock' : 'Currently Out of Stock',
        valueB: defaultVariantB.isInStock ? 'In Stock' : 'Currently Out of Stock',
        isDifferent: defaultVariantA.isInStock != defaultVariantB.isInStock,
      ),
    ];

    // Add variant attributes to Highlights (e.g. RAM, ROM, Size, Weight)
    final allAttrKeys = <String>{};
    final mapAttrA = <String, String>{};
    final mapAttrB = <String, String>{};

    for (final attr in productA.attributes) {
      if (attr.name.isNotEmpty) {
        allAttrKeys.add(attr.name);
        mapAttrA[attr.name] = attr.values.join(', ');
      }
    }
    for (final attr in productB.attributes) {
      if (attr.name.isNotEmpty) {
        allAttrKeys.add(attr.name);
        mapAttrB[attr.name] = attr.values.join(', ');
      }
    }

    for (final key in allAttrKeys) {
      final valA = mapAttrA[key]?.trim();
      final valB = mapAttrB[key]?.trim();
      final displayA = (valA != null && valA.isNotEmpty) ? '$valA $key' : 'N/A';
      final displayB = (valB != null && valB.isNotEmpty) ? '$valB $key' : 'N/A';
      highlightRows.add(CompareAttributeRow(
        title: key,
        valueA: displayA,
        valueB: displayB,
        isDifferent: displayA.toLowerCase() != displayB.toLowerCase(),
      ));
    }

    groups.add(CompareSectionGroup(
      sectionTitle: 'Highlights',
      rows: highlightRows,
    ));

    // ── 3. Specifications Section ────────────────────────────────────────────
    final specRows = <CompareAttributeRow>[];
    final allCustomKeys = <String>{};
    final mapCustomA = <String, String>{};
    final mapCustomB = <String, String>{};

    for (final cf in productA.customFields) {
      if (cf.key.isNotEmpty) {
        allCustomKeys.add(cf.key);
        mapCustomA[cf.key] = cf.value?.toString() ?? '';
      }
    }
    for (final cf in productB.customFields) {
      if (cf.key.isNotEmpty) {
        allCustomKeys.add(cf.key);
        mapCustomB[cf.key] = cf.value?.toString() ?? '';
      }
    }

    for (final key in allCustomKeys) {
      final valA = mapCustomA[key]?.trim() ?? '';
      final valB = mapCustomB[key]?.trim() ?? '';
      final displayA = valA.isNotEmpty ? valA : 'N/A';
      final displayB = valB.isNotEmpty ? valB : 'N/A';
      specRows.add(CompareAttributeRow(
        title: key,
        valueA: displayA,
        valueB: displayB,
        isDifferent: displayA.toLowerCase() != displayB.toLowerCase(),
      ));
    }

    if (productA.madeIn.isNotEmpty || productB.madeIn.isNotEmpty) {
      final valA = productA.madeIn.trim();
      final valB = productB.madeIn.trim();
      specRows.add(CompareAttributeRow(
        title: 'Country of Origin',
        valueA: valA.isNotEmpty ? valA : 'N/A',
        valueB: valB.isNotEmpty ? valB : 'N/A',
        isDifferent: valA.toLowerCase() != valB.toLowerCase(),
      ));
    }

    final indicatorA = _formatIndicator(productA.indicator);
    final indicatorB = _formatIndicator(productB.indicator);
    if (indicatorA.isNotEmpty || indicatorB.isNotEmpty) {
      specRows.add(CompareAttributeRow(
        title: 'Dietary Preference',
        valueA: indicatorA.isNotEmpty ? indicatorA : 'N/A',
        valueB: indicatorB.isNotEmpty ? indicatorB : 'N/A',
        isDifferent: indicatorA.toLowerCase() != indicatorB.toLowerCase(),
      ));
    }

    if (specRows.isNotEmpty) {
      groups.add(CompareSectionGroup(
        sectionTitle: 'General & Specifications',
        rows: specRows,
      ));
    }

    // ── 4. Warranty & Guarantee Section ──────────────────────────────────────
    final warrantyRows = <CompareAttributeRow>[
      if (productA.warrantyPeriod.isNotEmpty || productB.warrantyPeriod.isNotEmpty)
        CompareAttributeRow(
          title: 'Warranty',
          valueA: productA.warrantyPeriod.isNotEmpty ? '${productA.warrantyPeriod} Warranty' : 'N/A',
          valueB: productB.warrantyPeriod.isNotEmpty ? '${productB.warrantyPeriod} Warranty' : 'N/A',
          isDifferent: productA.warrantyPeriod != productB.warrantyPeriod,
        ),
      if (productA.guaranteePeriod.isNotEmpty || productB.guaranteePeriod.isNotEmpty)
        CompareAttributeRow(
          title: 'Guarantee',
          valueA: productA.guaranteePeriod.isNotEmpty ? '${productA.guaranteePeriod} Guarantee' : 'N/A',
          valueB: productB.guaranteePeriod.isNotEmpty ? '${productB.guaranteePeriod} Guarantee' : 'N/A',
          isDifferent: productA.guaranteePeriod != productB.guaranteePeriod,
        ),
    ];

    if (warrantyRows.isNotEmpty) {
      groups.add(CompareSectionGroup(
        sectionTitle: 'Warranty Details',
        rows: warrantyRows,
      ));
    }

    return groups;
  }

  static String _formatIndicator(String indicator) {
    final lower = indicator.toLowerCase().trim();
    if (lower == '1' || lower == 'veg') return '100% Vegetarian';
    if (lower == '2' || lower == 'non_veg' || lower == 'non-veg') return 'Non-Vegetarian';
    return indicator;
  }
}
