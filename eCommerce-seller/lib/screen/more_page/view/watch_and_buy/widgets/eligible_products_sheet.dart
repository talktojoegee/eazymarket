// ignore_for_file: deprecated_member_use

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';
import '../bloc/eligible_products/eligible_products_bloc.dart';
import '../model/eligible_product_model.dart';
import '../repo/watch_and_buy_repo.dart';

class ProductSelectionResult {
  final EligibleProductModel? primaryProduct;
  final List<EligibleProductModel> selectedProducts;

  ProductSelectionResult({
    this.primaryProduct,
    this.selectedProducts = const [],
  });
}

class EligibleProductsSheet extends StatefulWidget {
  final int maxSelections;
  final bool requirePrimary;
  final EligibleProductModel? initialPrimary;
  final List<EligibleProductModel> initialSelections;

  const EligibleProductsSheet({
    super.key,
    this.maxSelections = 10,
    this.requirePrimary = false,
    this.initialPrimary,
    this.initialSelections = const [],
  });

  static Future<ProductSelectionResult?> show(
    BuildContext context, {
    int maxSelections = 10,
    bool requirePrimary = false,
    EligibleProductModel? initialPrimary,
    List<EligibleProductModel> initialSelections = const [],
  }) {
    return showModalBottomSheet<ProductSelectionResult>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => BlocProvider(
        create: (_) => EligibleProductsBloc(repo: WatchAndBuyRepo())
          ..add(const FetchEligibleProductsEvent()),
        child: EligibleProductsSheet(
          maxSelections: maxSelections,
          requirePrimary: requirePrimary,
          initialPrimary: initialPrimary,
          initialSelections: initialSelections,
        ),
      ),
    );
  }

  @override
  State<EligibleProductsSheet> createState() => _EligibleProductsSheetState();
}

class _EligibleProductsSheetState extends State<EligibleProductsSheet> {
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  Timer? _debounce;

  EligibleProductModel? _primaryProduct;
  final Map<String, EligibleProductModel> _selectedProducts = {};

  String? _limitWarningMessage;
  Timer? _limitWarningTimer;
  int _warningTimestamp = 0;

  @override
  void initState() {
    super.initState();
    _primaryProduct = widget.initialPrimary;
    for (final p in widget.initialSelections) {
      _selectedProducts[p.uniqueKey] = p;
    }
    if (_primaryProduct != null) {
      _selectedProducts[_primaryProduct!.uniqueKey] = _primaryProduct!;
    }

    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _limitWarningTimer?.cancel();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      context.read<EligibleProductsBloc>().add(const LoadMoreEligibleProductsEvent());
    }
  }

  void _onSearchChanged(String query) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) {
        context.read<EligibleProductsBloc>().add(SearchEligibleProductsEvent(query.trim()));
      }
    });
    setState(() {});
  }

  void _showLimitMessage(String message) {
    _limitWarningTimer?.cancel();
    setState(() {
      _limitWarningMessage = message;
      _warningTimestamp = DateTime.now().millisecondsSinceEpoch;
    });
    _limitWarningTimer = Timer(const Duration(seconds: 3), () {
      if (mounted) {
        setState(() {
          _limitWarningMessage = null;
        });
      }
    });
  }

  void _toggleSelect(EligibleProductModel product) {
    if (!_selectedProducts.containsKey(product.uniqueKey) &&
        _selectedProducts.length >= widget.maxSelections) {
      _showLimitMessage('You can select at most ${widget.maxSelections} products');
      return;
    }

    setState(() {
      if (_selectedProducts.containsKey(product.uniqueKey)) {
        _selectedProducts.remove(product.uniqueKey);
        if (_primaryProduct?.uniqueKey == product.uniqueKey) {
          _primaryProduct = _selectedProducts.isNotEmpty ? _selectedProducts.values.first : null;
        }
        if (_selectedProducts.length < widget.maxSelections && _limitWarningMessage != null) {
          _limitWarningMessage = null;
        }
      } else {
        _selectedProducts[product.uniqueKey] = product;
        if (widget.requirePrimary && _primaryProduct == null) {
          _primaryProduct = product;
        }
      }
    });
  }

  void _setPrimary(EligibleProductModel product) {
    setState(() {
      _selectedProducts[product.uniqueKey] = product;
      _primaryProduct = product;
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      height: MediaQuery.of(context).size.height * 0.88,
      padding: EdgeInsets.only(bottom: bottomInset),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Stack(
        children: [
          Column(
            children: [
              // Drag Handle
          const SizedBox(height: 10),
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: isDark ? Colors.white24 : Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),

          // Header
          Container(
            padding: const EdgeInsets.fromLTRB(16, 10, 12, 10),
            decoration: BoxDecoration(
              border: Border(bottom: BorderSide(color: isDark ? Colors.white12 : const Color(0xFFEBEBEB))),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Select Tagged Products',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Selected: ${_selectedProducts.length}/${widget.maxSelections}'
                        '${widget.requirePrimary ? ' • Tap star for primary' : ''}',
                        style: TextStyle(fontSize: 12, color: isDark ? Colors.white60 : const Color(0xFF8E8E93)),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close, size: 20),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
          ),

          // Search Field
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 6),
            child: Container(
              height: 46,
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF2C2C2E) : const Color(0xFFF2F4F7),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: isDark ? Colors.white10 : Colors.black.withOpacity(0.04),
                ),
              ),
              child: TextField(
                controller: _searchController,
                onChanged: _onSearchChanged,
                style: TextStyle(
                  fontSize: 14,
                  color: isDark ? Colors.white : const Color(0xFF1F2024),
                ),
                decoration: InputDecoration(
                  hintText: 'Search products by title...',
                  hintStyle: TextStyle(
                    fontSize: 13.5,
                    color: isDark ? Colors.white38 : const Color(0xFF8E8E93),
                  ),
                  prefixIcon: Icon(
                    Icons.search,
                    size: 20,
                    color: isDark ? Colors.white54 : const Color(0xFF8E8E93),
                  ),
                  suffixIcon: _searchController.text.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.close, size: 18),
                          color: isDark ? Colors.white54 : const Color(0xFF8E8E93),
                          onPressed: () {
                            _searchController.clear();
                            _onSearchChanged('');
                          },
                        )
                      : null,
                  border: InputBorder.none,
                  contentPadding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
          ),

          // Products List
          Expanded(
            child: BlocBuilder<EligibleProductsBloc, EligibleProductsState>(
              builder: (context, state) {
                if (state is EligibleProductsLoading) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (state is EligibleProductsError) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(state.error, textAlign: TextAlign.center),
                          const SizedBox(height: 12),
                          PrimaryButton(
                            text: 'Retry',
                            width: 120,
                            onPressed: () => context.read<EligibleProductsBloc>().add(
                                  SearchEligibleProductsEvent(_searchController.text.trim()),
                                ),
                          ),
                        ],
                      ),
                    ),
                  );
                }

                List<EligibleProductModel> products = [];
                bool hasMore = false;

                if (state is EligibleProductsLoaded) {
                  products = state.products;
                  hasMore = state.hasMore;
                } else if (state is EligibleProductsLoadingMore) {
                  products = state.currentProducts;
                  hasMore = true;
                }

                if (products.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.search_off_rounded,
                          size: 48,
                          color: isDark ? Colors.white38 : Colors.grey.shade400,
                        ),
                        const SizedBox(height: 10),
                        Text(
                          _searchController.text.isNotEmpty
                              ? 'No products match "${_searchController.text}"'
                              : 'No eligible products found',
                          style: TextStyle(color: isDark ? Colors.white60 : const Color(0xFF8E8E93), fontSize: 13.5),
                        ),
                      ],
                    ),
                  );
                }

                return ListView.separated(
                  controller: _scrollController,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  itemCount: products.length + (hasMore ? 1 : 0),
                  separatorBuilder: (_, __) => Divider(height: 1, color: isDark ? Colors.white10 : const Color(0xFFEBEBEB)),
                  itemBuilder: (context, index) {
                    if (index == products.length) {
                      return const Padding(
                        padding: EdgeInsets.symmetric(vertical: 16),
                        child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                      );
                    }

                    final item = products[index];
                    final isSelected = _selectedProducts.containsKey(item.uniqueKey);
                    final isPrimary = _primaryProduct?.uniqueKey == item.uniqueKey;

                    return ListTile(
                      contentPadding: const EdgeInsets.symmetric(vertical: 4),
                      leading: ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: item.productImage != null && item.productImage!.isNotEmpty
                            ? CustomNetworkImage(
                                imageUrl: item.productImage!,
                                width: 48,
                                height: 48,
                                fit: BoxFit.cover,
                              )
                            : Container(
                                width: 48,
                                height: 48,
                                color: isDark ? const Color(0xFF2C2C2E) : Colors.grey.shade200,
                                child: const Icon(Icons.image_outlined, color: Colors.grey),
                              ),
                      ),
                      title: Text(
                        item.productTitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                      ),
                      subtitle: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const SizedBox(height: 2),
                          Row(
                            children: [
                              if (item.variantTitle != null && item.variantTitle!.isNotEmpty) ...[
                                Expanded(
                                  child: Text(
                                    item.variantTitle!,
                                    style: TextStyle(fontSize: 11.5, color: isDark ? Colors.white60 : Colors.black54),
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ],
                          ),
                          if (item.isOutOfStock)
                            const Padding(
                              padding: EdgeInsets.only(top: 2),
                              child: Text(
                                'Out of stock',
                                style: TextStyle(color: Colors.red, fontSize: 11, fontWeight: FontWeight.bold),
                              ),
                            ),
                        ],
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (widget.requirePrimary && isSelected)
                            IconButton(
                              icon: Icon(
                                isPrimary ? Icons.star_rounded : Icons.star_outline_rounded,
                                color: isPrimary ? Colors.amber : Colors.grey,
                                size: 24,
                              ),
                              tooltip: isPrimary ? 'Primary product' : 'Set as primary',
                              onPressed: () => _setPrimary(item),
                            ),
                          Checkbox(
                            value: isSelected,
                            activeColor: AppColors.primaryColor,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                            onChanged: (val) => _toggleSelect(item),
                          ),
                        ],
                      ),
                      onTap: () => _toggleSelect(item),
                    );
                  },
                );
              },
            ),
          ),

          // Bottom Action Bar
          Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
              border: Border(top: BorderSide(color: isDark ? Colors.white12 : const Color(0xFFEBEBEB))),
            ),
            child: Row(
              children: [
                Expanded(
                  child: SecondaryButton(
                    text: 'Clear',
                    onPressed: () {
                      setState(() {
                        _selectedProducts.clear();
                        _primaryProduct = null;
                      });
                    },
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 2,
                  child: PrimaryButton(
                    text: 'Done (${_selectedProducts.length})',
                    onPressed: () {
                      if (widget.requirePrimary && _selectedProducts.isNotEmpty && _primaryProduct == null) {
                        _primaryProduct = _selectedProducts.values.first;
                      }
                      Navigator.pop(
                        context,
                        ProductSelectionResult(
                          primaryProduct: _primaryProduct,
                          selectedProducts: _selectedProducts.values.toList(),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      if (_limitWarningMessage != null)
        Positioned(
          left: 16,
          right: 16,
          bottom: 96,
          child: _buildLimitToast(isDark),
        ),
    ],
  ),
);
  }

  Widget _buildLimitToast(bool isDark) {
    return TweenAnimationBuilder<double>(
      key: ValueKey(_warningTimestamp),
      duration: const Duration(milliseconds: 260),
      curve: Curves.easeOutCubic,
      tween: Tween(begin: 0.0, end: 1.0),
      builder: (context, val, child) {
        return Opacity(
          opacity: val.clamp(0.0, 1.0),
          child: Transform.translate(
            offset: Offset(0, (1 - val) * 16),
            child: child,
          ),
        );
      },
      child: Material(
        color: Colors.transparent,
        child: GestureDetector(
          onTap: () {
            _limitWarningTimer?.cancel();
            setState(() => _limitWarningMessage = null);
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF2C2C2E) : const Color(0xFF1E1E1E),
              borderRadius: BorderRadius.circular(12),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.28),
                  blurRadius: 14,
                  offset: const Offset(0, 4),
                ),
              ],
              border: Border.all(
                color: isDark ? Colors.white12 : Colors.black12,
                width: 1,
              ),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.warning_amber_rounded,
                  color: AppColors.primaryColor,
                  size: 22,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _limitWarningMessage ?? '',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                const Icon(
                  Icons.close,
                  color: Colors.white60,
                  size: 16,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
