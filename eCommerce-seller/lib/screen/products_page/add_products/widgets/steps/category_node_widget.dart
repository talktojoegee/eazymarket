import 'dart:developer';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/categories/model/categories_model.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/selected_categories/category_expansion_cubit.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/selected_categories/selected_categories_cubit.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/category_list_widget.dart';

/// One row in the category tree.
///
/// Visual redesign:
/// - Amber-tint background + amber border when selected (was pale blue).
/// - Folder chip becomes a rounded tinted square that fills amber when
///   selected, tints amber when expanded, greys when idle.
/// - Chevron uses its own hitbox and animates via AnimatedRotation.
/// - Right side shows an amber circular check with a black tick on
///   selection (was blue check icon).
class CategoryNodeWidget extends StatefulWidget {
  final Category category;
  final int level;

  const CategoryNodeWidget({
    super.key,
    required this.category,
    required this.level,
  });

  @override
  State<CategoryNodeWidget> createState() => _CategoryNodeWidgetState();
}

class _CategoryNodeWidgetState extends State<CategoryNodeWidget> {
  static const double _indentPerLevel = 18;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return BlocBuilder<CategoryExpansionCubit, Set<int>>(
      builder: (context, expandedIds) {
        final isExpanded = expandedIds.contains(widget.category.id);

        return BlocBuilder<SelectedCategoriesCubit, Set<int>>(
          builder: (context, selectedIds) {
            final isSelected = selectedIds.contains(widget.category.id);
            final hasChildren = widget.category.subcategoryCount > 0;

            final Color rowBg = isSelected
                ? AppColors.primaryColor.withValues(alpha: 0.12)
                : Colors.transparent;
            final Color rowBorder = isSelected
                ? AppColors.primaryColor
                : Colors.transparent;

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: EdgeInsets.only(
                    left: 4 + (widget.level * _indentPerLevel),
                    right: 4,
                    top: 2,
                    bottom: 2,
                  ),
                  child: Material(
                    color: rowBg,
                    borderRadius: BorderRadius.circular(12),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: () => _onSelectionToggle(context),
                      child: Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: rowBorder, width: 1),
                        ),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 8,
                        ),
                        child: Row(
                          children: [
                            _ChevronBtn(
                              isExpanded: isExpanded,
                              enabled: hasChildren,
                              onTap: () =>
                                  _onExpansionToggle(context, isExpanded),
                            ),
                            _FolderChip(
                              isSelected: isSelected,
                              isExpanded: isExpanded,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                widget.category.title,
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: isSelected
                                      ? FontWeight.w700
                                      : FontWeight.w600,
                                  color: isSelected
                                      ? theme.colorScheme.tertiary
                                      : theme.colorScheme.tertiary
                                          .withValues(alpha: 0.85),
                                  height: 1.2,
                                ),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            if (isSelected)
                              Container(
                                width: 24,
                                height: 24,
                                margin: const EdgeInsets.only(left: 6),
                                decoration: const BoxDecoration(
                                  color: AppColors.primaryColor,
                                  shape: BoxShape.circle,
                                ),
                                alignment: Alignment.center,
                                child: const Icon(
                                  Icons.check_rounded,
                                  size: 16,
                                  color: Colors.black,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                if (isExpanded && hasChildren)
                  CategoryListWidget(
                    parentSlug: widget.category.slug,
                    level: widget.level + 1,
                  ),
              ],
            );
          },
        );
      },
    );
  }

  void _onSelectionToggle(BuildContext context) {
    final cubit = context.read<SelectedCategoriesCubit>();
    cubit.toggleSelection(widget.category.id);

    log('User toggled selection for category: '
        '${widget.category.id} (${widget.category.title})');

    // Sync with AddProductBloc — single-select semantics.
    final isCurrentlySelected = cubit.isSelected(widget.category.id);
    final addProductBloc = context.read<AddProductBloc>();
    final currentData = addProductBloc.state.productData;
    final nextSelectedId =
        isCurrentlySelected ? widget.category.id : null;
    addProductBloc.add(
      UpdateProductData(currentData.copyWith(categoryId: nextSelectedId)),
    );
  }

  void _onExpansionToggle(BuildContext context, bool isExpanded) {
    log('Toggling expansion for category: ${widget.category.id}');
    final cubit = context.read<CategoryExpansionCubit>();
    if (isExpanded) {
      cubit.collapse(widget.category.id);
    } else {
      cubit.expand(widget.category.id);
    }
  }
}

class _ChevronBtn extends StatelessWidget {
  final bool isExpanded;
  final bool enabled;
  final VoidCallback onTap;

  const _ChevronBtn({
    required this.isExpanded,
    required this.enabled,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final Color color = enabled
        ? theme.colorScheme.tertiary.withValues(alpha: 0.55)
        : Colors.transparent;

    return SizedBox(
      width: 30,
      height: 30,
      child: enabled
          ? Material(
              color: Colors.transparent,
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: onTap,
                child: AnimatedRotation(
                  turns: isExpanded ? 0.25 : 0.0,
                  duration: const Duration(milliseconds: 180),
                  child: Icon(
                    Icons.chevron_right_rounded,
                    size: 22,
                    color: color,
                  ),
                ),
              ),
            )
          : const SizedBox.shrink(),
    );
  }
}

class _FolderChip extends StatelessWidget {
  final bool isSelected;
  final bool isExpanded;

  const _FolderChip({
    required this.isSelected,
    required this.isExpanded,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    Color bg;
    Color fg;
    if (isSelected) {
      bg = AppColors.primaryColor;
      fg = Colors.black;
    } else if (isExpanded) {
      bg = AppColors.primaryColor.withValues(alpha: 0.16);
      fg = AppColors.primaryColor;
    } else {
      bg = theme.colorScheme.outlineVariant.withValues(alpha: 0.5);
      fg = theme.colorScheme.tertiary.withValues(alpha: 0.6);
    }

    return Container(
      width: 32,
      height: 32,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(9),
      ),
      alignment: Alignment.center,
      child: Icon(
        isExpanded ? Icons.folder_open_rounded : Icons.folder_rounded,
        size: 18,
        color: fg,
      ),
    );
  }
}
