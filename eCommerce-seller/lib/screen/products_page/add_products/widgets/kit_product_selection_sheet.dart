// ignore_for_file: deprecated_member_use

import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/model/product_model.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/repo/products_repo.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_filter_sheet.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/modern_search_field.dart';

class KitProductSelectionSheet extends StatefulWidget {
  final int storeId;
  final Function(KitComponentData) onSelected;

  const KitProductSelectionSheet({
    super.key,
    required this.storeId,
    required this.onSelected,
  });

  static Future<void> show(
    BuildContext context, {
    required int storeId,
    required Function(KitComponentData) onSelected,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => KitProductSelectionSheet(
        storeId: storeId,
        onSelected: onSelected,
      ),
    );
  }

  @override
  State<KitProductSelectionSheet> createState() =>
      _KitProductSelectionSheetState();
}

class _KitProductSelectionSheetState extends State<KitProductSelectionSheet> {
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final ProductsRepo _repo = ProductsRepo();

  final List<Product> _products = [];
  bool _isLoading = false;
  int _page = 1;
  bool _hasMore = true;
  String _lastSearch = '';
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _fetchProducts();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      if (!_isLoading && _hasMore) {
        _fetchProducts();
      }
    }
  }

  Future<void> _fetchProducts({bool isRefresh = false}) async {
    if (_isLoading) return;

    setState(() {
      _isLoading = true;
      if (isRefresh) {
        _page = 1;
        _products.clear();
      }
    });
    try {
      final String query = _searchController.text.trim();
      final response = await _repo.getProducts(
        page: _page,
        perPage: 30,
        storeId: widget.storeId,
        search: query.isNotEmpty ? query : null,
        status: 'active',
      );

      final productsResponse = ProductsResponse.fromJson(response);
      if (productsResponse.success == true && productsResponse.data != null) {
        final newProducts = (productsResponse.data!.products ?? [])
            .where((p) => p.status.trim().toLowerCase() == 'active')
            .toList();
        setState(() {
          _products.addAll(newProducts);
          _hasMore = (productsResponse.data!.currentPage ?? 1) <
              (productsResponse.data!.lastPage ?? 1);
          if (_hasMore) _page++;
        });
      }
    } catch (e) {
      debugPrint('Error fetching kit eligible products: $e');
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final screenType = context.screenType;

    return Container(
      height: MediaQuery.of(context).size.height * 0.82,
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(UIUtils.radiusLG(screenType)),
        ),
      ),
      child: Column(
        children: [
          const SizedBox(height: 12),
          Container(
            width: 44,
            height: 4,
            decoration: BoxDecoration(
              color: theme.colorScheme.outlineVariant.withOpacity(0.5),
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          Padding(
            padding:
                const EdgeInsets.symmetric(horizontal: 16.0, vertical: 12.0),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Select Product & Variant',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: theme.colorScheme.onSurface,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Active & approved products in selected store',
                      style: TextStyle(
                        fontSize: 12,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.all(12.0),
            child: ModernSearchBar(
              controller: _searchController,
              hint: 'Search products by name or SKU...',
              showFilters: false,
              filterType: FilterType.product,
              onChanged: (value) {
                if (value != _lastSearch) {
                  _lastSearch = value;
                  _debounce?.cancel();
                  _debounce = Timer(const Duration(milliseconds: 350), () {
                    _fetchProducts(isRefresh: true);
                  });
                }
              },
            ),
          ),
          Expanded(
            child: _isLoading && _products.isEmpty
                ? const Center(child: CustomLoadingIndicator())
                : _products.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.inventory_2_outlined,
                              size: 48,
                              color: theme.colorScheme.outline,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              'No approved products found',
                              style: TextStyle(
                                fontSize: 14,
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      )
                    : ListView.separated(
                        controller: _scrollController,
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 8),
                        itemCount: _products.length + (_hasMore ? 1 : 0),
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, index) {
                          if (index == _products.length) {
                            return const Padding(
                              padding: EdgeInsets.all(16.0),
                              child: Center(child: CustomLoadingIndicator()),
                            );
                          }

                          final product = _products[index];
                          final variants = product.variants ?? [];

                          if (variants.length > 1) {
                            return _ProductWithVariantsCard(
                              product: product,
                              variants: variants,
                              onVariantSelected: (v) {
                                final comp = KitComponentData(
                                  productId: product.id,
                                  productVariantId: v.id ?? product.id,
                                  productTitle: product.title,
                                  variantTitle:
                                      (v.title != null && v.title!.isNotEmpty)
                                          ? v.title!
                                          : product.title,
                                  price: (v.specialPrice != null &&
                                          v.specialPrice! > 0)
                                      ? v.specialPrice!.toDouble()
                                      : (v.price?.toDouble() ?? 0.0),
                                  image: v.image ?? product.mainImage,
                                  quantity: 1,
                                );
                                Navigator.pop(context);
                                widget.onSelected(comp);
                              },
                            );
                          }

                          // Single variant or simple product
                          final singleVariant =
                              variants.isNotEmpty ? variants.first : null;
                          final double price = (singleVariant != null &&
                                  singleVariant.specialPrice != null &&
                                  singleVariant.specialPrice! > 0)
                              ? singleVariant.specialPrice!.toDouble()
                              : (singleVariant?.price?.toDouble() ?? 0.0);

                          return InkWell(
                            onTap: () {
                              final comp = KitComponentData(
                                productId: product.id,
                                productVariantId:
                                    singleVariant?.id ?? product.id,
                                productTitle: product.title,
                                variantTitle: (singleVariant?.title != null &&
                                        singleVariant!.title!.isNotEmpty)
                                    ? singleVariant.title!
                                    : product.title,
                                price: price,
                                image:
                                    singleVariant?.image ?? product.mainImage,
                                quantity: 1,
                              );
                              Navigator.pop(context);
                              widget.onSelected(comp);
                            },
                            borderRadius: BorderRadius.circular(10),
                            child: Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(
                                  color: theme.colorScheme.outlineVariant
                                      .withOpacity(0.4),
                                ),
                              ),
                              child: Row(
                                children: [
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(8),
                                    child: CachedNetworkImage(
                                      imageUrl: product.mainImage,
                                      width: 48,
                                      height: 48,
                                      fit: BoxFit.cover,
                                      errorWidget: (_, __, ___) => Container(
                                        width: 48,
                                        height: 48,
                                        color: Colors.grey.shade200,
                                        child: const Icon(
                                            Icons.image_not_supported,
                                            size: 20,
                                            color: Colors.grey),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          product.title,
                                          style: const TextStyle(
                                            fontSize: 14,
                                            fontWeight: FontWeight.w600,
                                          ),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                        if (product.categoryName != null) ...[
                                          const SizedBox(height: 2),
                                          Text(
                                            product.categoryName!,
                                            style: TextStyle(
                                              fontSize: 11,
                                              color: theme
                                                  .colorScheme.onSurfaceVariant,
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    price > 0
                                        ? '\$${price.toStringAsFixed(2)}'
                                        : '',
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.primaryColor,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          ),
        ],
      ),
    );
  }
}

class _ProductWithVariantsCard extends StatefulWidget {
  final Product product;
  final List<Variant> variants;
  final Function(Variant) onVariantSelected;

  const _ProductWithVariantsCard({
    required this.product,
    required this.variants,
    required this.onVariantSelected,
  });

  @override
  State<_ProductWithVariantsCard> createState() =>
      _ProductWithVariantsCardState();
}

class _ProductWithVariantsCardState extends State<_ProductWithVariantsCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withOpacity(0.4),
        ),
      ),
      child: Column(
        children: [
          InkWell(
            onTap: () => setState(() => _expanded = !_expanded),
            borderRadius: BorderRadius.circular(10),
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: CachedNetworkImage(
                      imageUrl: widget.product.mainImage,
                      width: 48,
                      height: 48,
                      fit: BoxFit.cover,
                      errorWidget: (_, __, ___) => Container(
                        width: 48,
                        height: 48,
                        color: Colors.grey.shade200,
                        child: const Icon(Icons.image_not_supported,
                            size: 20, color: Colors.grey),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.product.title,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${widget.variants.length} variants available (tap to choose)',
                          style: TextStyle(
                            fontSize: 11,
                            color: theme.colorScheme.primary,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Icon(
                    _expanded ? Icons.expand_less : Icons.expand_more,
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ],
              ),
            ),
          ),
          if (_expanded) ...[
            const Divider(height: 1),
            ...widget.variants.map((v) {
              final double price =
                  (v.specialPrice != null && v.specialPrice! > 0)
                      ? v.specialPrice!.toDouble()
                      : (v.price?.toDouble() ?? 0.0);

              return InkWell(
                onTap: () => widget.onVariantSelected(v),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.surfaceVariant.withOpacity(0.2),
                    border: Border(
                      bottom: BorderSide(
                        color:
                            theme.colorScheme.outlineVariant.withOpacity(0.2),
                      ),
                    ),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.subdirectory_arrow_right,
                          size: 16, color: Colors.grey),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '${widget.product.title} — ${v.title ?? ""}',
                          style: const TextStyle(fontSize: 13),
                        ),
                      ),
                      Text(
                        price > 0 ? '\$${price.toStringAsFixed(2)}' : '',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: AppColors.primaryColor,
                        ),
                      ),
                      const SizedBox(width: 8),
                      const Icon(Icons.add_circle_outline,
                          size: 18, color: Colors.blue),
                    ],
                  ),
                ),
              );
            }),
          ],
        ],
      ),
    );
  }
}
