import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/screens/product_detail_page/model/product_detail_model.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:hyper_commerce/utils/widgets/custom_scaffold/custom_scaffold.dart';
import '../model/product_compare_model.dart';
import '../widgets/compare_attribute_row.dart';
import '../widgets/compare_product_card.dart';
import '../widgets/product_picker_bottom_sheet.dart';

class ProductComparePage extends StatefulWidget {
  final ProductData product;
  final ProductData? compareProduct;
  final List<ProductData>? similarProducts;

  const ProductComparePage({
    super.key,
    required this.product,
    this.compareProduct,
    this.similarProducts,
  });

  @override
  State<ProductComparePage> createState() => _ProductComparePageState();
}

class _ProductComparePageState extends State<ProductComparePage> {
  late ProductData _productA;
  ProductData? _productB;
  late List<ProductData> _availableAlternatives;

  @override
  void initState() {
    super.initState();
    _productA = widget.product;
    _availableAlternatives = (widget.similarProducts ?? [])
        .where((p) => p.id != _productA.id)
        .toList();

    if (widget.compareProduct != null) {
      _productB = widget.compareProduct;
    } else if (_availableAlternatives.isNotEmpty) {
      _productB = _availableAlternatives.first;
    }
  }

  void _onChangeProductB() async {
    if (_availableAlternatives.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('noMoreSimilarProducts', fallback: 'No other similar products available to compare.')),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final selected = await ProductPickerBottomSheet.show(
      context: context,
      products: _availableAlternatives,
      currentSelectedId: _productB?.id ?? -1,
    );

    if (selected != null && mounted) {
      setState(() {
        _productB = selected;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final isDark = theme.brightness == Brightness.dark;

    final comparisonGroups = _productB != null
        ? ProductCompareEngine.buildComparison(
            context: context,
            productA: _productA,
            productB: _productB!,
          )
        : <CompareSectionGroup>[];

    final dividerColor = isDark
        ? Colors.white.withValues(alpha: 0.1)
        : const Color(0xFFEEEEEE);

    return CustomScaffold(
      /*backgroundColor: isDark ? const Color(0xFF121212) : Colors.white,
      appBar: AppBar(
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: AppTheme.primaryColor,
        leading: IconButton(
          icon: Icon(
            TablerIcons.arrow_left,
            size: 22.sp,
            color: Colors.white,
          ),
          onPressed: () => context.pop(),
        ),
        title: Text(
          context.tr('compare', fallback: 'Compare'),
          style: TextStyle(
            fontSize: 18.sp,
            fontWeight: FontWeight.w600,
            color: Colors.white,
          ),
        ),
      ),*/
      appBarBgColor: AppTheme.primaryColor,
      title: context.tr('compare', fallback: 'Compare'),
      showAppBar: true,
      body: Column(
        children: [
          // ── Pinned Top Header: Two Product Columns with Vertical Divider ──
          Container(
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkProductCardColor : Colors.white,
              border: Border(
                bottom: BorderSide(
                  color: isDark ? Colors.white.withValues(alpha: 0.12) : const Color(0xFFE0E0E0),
                  width: 1,
                ),
              ),
            ),
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Left Column: Product A
                  Expanded(
                    child: CompareProductCard(
                      product: _productA,
                      isChangeable: false,
                    ),
                  ),

                  // Center Vertical Divider
                  VerticalDivider(
                    color: dividerColor,
                    width: 1,
                    thickness: 1,
                  ),

                  // Right Column: Product B or Empty Slot
                  Expanded(
                    child: _productB != null
                        ? CompareProductCard(
                            product: _productB!,
                            isChangeable: true,
                            onChange: _onChangeProductB,
                          )
                        : _buildEmptyProductSlot(context),
                  ),
                ],
              ),
            ),
          ),

          // ── Scrollable Comparison Body ─────────────────────────────────────
          Expanded(
            child: _productB == null
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          TablerIcons.git_compare,
                          size: 48.sp,
                          color: scheme.outline,
                        ),
                        SizedBox(height: 12.h),
                        Text(
                          context.tr('selectProductToCompare', fallback: 'Select a product to start comparing'),
                          style: TextStyle(
                            fontSize: 14.sp,
                            fontWeight: FontWeight.w600,
                            color: scheme.onSurface,
                          ),
                        ),
                        SizedBox(height: 12.h),
                        ElevatedButton.icon(
                          onPressed: _onChangeProductB,
                          icon: const Icon(Icons.add),
                          label: Text(context.tr('chooseProduct', fallback: 'Choose Product')),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppTheme.primaryColor,
                            foregroundColor: Colors.white,
                          ),
                        ),
                      ],
                    ),
                  )
                : ListView.builder(
                    padding: EdgeInsets.only(bottom: 24.h),
                    itemCount: comparisonGroups.length,
                    itemBuilder: (context, groupIndex) {
                      final group = comparisonGroups[groupIndex];

                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (group.sectionTitle.isNotEmpty)
                            CompareSectionHeaderWidget(title: group.sectionTitle),
                          ...group.rows.map(
                            (row) => CompareAttributeRowWidget(row: row),
                          ),
                        ],
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyProductSlot(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return GestureDetector(
      onTap: _onChangeProductB,
      behavior: HitTestBehavior.opaque,
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: EdgeInsets.all(12.r),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: scheme.primary.withValues(alpha: 0.08),
              ),
              child: Icon(
                Icons.add,
                size: 26.sp,
                color: AppTheme.primaryColor,
              ),
            ),
            SizedBox(height: 8.h),
            Text(
              context.tr('addProduct', fallback: '+ Add Product'),
              style: TextStyle(
                fontSize: 12.sp,
                fontWeight: FontWeight.w600,
                color: AppTheme.primaryColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
