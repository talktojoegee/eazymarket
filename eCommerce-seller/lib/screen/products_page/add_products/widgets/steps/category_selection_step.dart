import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/selected_categories/category_expansion_cubit.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/selected_categories/selected_categories_cubit.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/category_list_widget.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/widgets/custom/critical_field_shield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_search_field.dart';

import '../../../../../widgets/custom/custom_filter_sheet.dart';
import '../../../../../widgets/custom/modern_search_field.dart';

class CategorySelectionStep extends StatefulWidget {
  const CategorySelectionStep({super.key});

  @override
  State<CategorySelectionStep> createState() => _CategorySelectionStepState();
}

class _CategorySelectionStepState extends State<CategorySelectionStep> {
  final TextEditingController _searchController = TextEditingController();
  final FocusNode _searchFocus = FocusNode();
  String _searchQuery = "";
  Timer? _debounce;

  @override
  void dispose() {
    _searchController.dispose();
    _searchFocus.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    final bloc = context.read<AddProductBloc>();
    final categoryId = bloc.state.productData.categoryId;
    if (categoryId != null) {
      context.read<SelectedCategoriesCubit>().select(categoryId);
    }
    final lineage = bloc.state.productData.categoryLineage;
    if (lineage.isNotEmpty) {
      context.read<CategoryExpansionCubit>().expandMany(lineage);
    }
  }

  void _onSearchChanged(String value) {
    if (_debounce?.isActive ?? false) _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      if (mounted) {
        setState(() {
          _searchQuery = value;
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return BlocListener<AddProductBloc, AddProductState>(
      listenWhen: (previous, current) {
        return previous.productData.categoryLineage !=
            current.productData.categoryLineage;
      },
      listener: (context, state) {
        if (state.productData.categoryLineage.isNotEmpty) {
          context
              .read<CategoryExpansionCubit>()
              .expandMany(state.productData.categoryLineage);
        }
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                context.tr('category', fallback: 'Category'),
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: theme.colorScheme.tertiary,
                ),
              ),
              const SizedBox(width: 4),
              const CriticalFieldShield(fieldKey: 'category_id'),
            ],
          ),
          const SizedBox(height: 4),
          // Helper subtitle above search
          Padding(
            padding: const EdgeInsets.only(bottom: 10, top: 2),
            child: Text(
              "Pick the category that best describes your product. "
              "Tap a row to expand; tap the leaf to select.",
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w500,
                color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
                height: 1.4,
              ),
            ),
          ),

          ModernSearchBar(
            hint: "Search category",
            // fillColor: theme.cardColor,
            // focusNode: _searchFocus,
            controller: _searchController,
            onChanged: _onSearchChanged,
            showFilters: false,
            filterType: FilterType.product,
          ),
          const SizedBox(height: 12),

          // Selection status ribbon — appears once a category is picked.
          BlocBuilder<SelectedCategoriesCubit, Set<int>>(
            builder: (context, selectedIds) {
              if (selectedIds.isEmpty) {
                return const SizedBox.shrink();
              }
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.stepCompletedColor
                        .withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppColors.stepCompletedColor
                          .withValues(alpha: 0.35),
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 22,
                        height: 22,
                        decoration: const BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.stepCompletedColor,
                        ),
                        alignment: Alignment.center,
                        child: const Icon(
                          Icons.check_rounded,
                          size: 14,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          "Category selected · ready to continue",
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: AppColors.stepCompletedColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),

          // Category tree shell — a section card wraps the whole tree so
          // nested nodes read as one grouped surface instead of floating
          // rows on the page background.
          Container(
            decoration: BoxDecoration(
              color: theme.cardColor,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
              ),
            ),
            padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 6),
            child: CategoryListWidget(
              level: 0,
              search: _searchQuery.isEmpty ? null : _searchQuery,
            ),
          ),
          const SizedBox(height: 12),
        ],
      ),
    );
  }
}
