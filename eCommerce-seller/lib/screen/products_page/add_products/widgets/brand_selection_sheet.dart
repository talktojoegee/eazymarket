import 'dart:async';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/brands/bloc/brands_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/brands/model/brands_model.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';

/// Brand-picker bottom sheet.
///
/// Redesign: drag handle at the top; header row with sheet title + subtitle
/// + circular close; search field; brand rows use a leading amber avatar
/// with the brand's first letter, tap ripple, amber-tinted bg + amber
/// border + amber check when selected (replaces the plain ListTile).
class BrandSelectionSheet extends StatefulWidget {
  final int? selectedBrandId;
  final Function(Brand) onSelected;

  const BrandSelectionSheet({
    super.key,
    this.selectedBrandId,
    required this.onSelected,
  });

  @override
  State<BrandSelectionSheet> createState() => _BrandSelectionSheetState();
}

class _BrandSelectionSheetState extends State<BrandSelectionSheet> {
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    context.read<BrandsBloc>().add(LoadBrandsInitial());
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      context.read<BrandsBloc>().add(LoadMoreBrands());
    }
  }

  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      context.read<BrandsBloc>().add(SearchBrands(query));
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      height: MediaQuery.of(context).size.height * 0.72,
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Handle
          Padding(
            padding: const EdgeInsets.only(top: 10, bottom: 4),
            child: Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: theme.colorScheme.outlineVariant,
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
            ),
          ),

          // Header row
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 12, 12),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr('selectBrand'),
                        style: TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                          color: theme.colorScheme.tertiary,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        "Type to search, tap a brand to pick it.",
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: theme.colorScheme.tertiary
                              .withValues(alpha: 0.55),
                        ),
                      ),
                    ],
                  ),
                ),
                _SheetClose(onTap: () => Navigator.pop(context)),
              ],
            ),
          ),

          // Search
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: CustomTextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              hint: context.tr('searchBrand'),
              prefixIcon: Icons.search_rounded,
            ),
          ),

          const SizedBox(height: 12),
          Divider(
            height: 1,
            thickness: 1,
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
          ),

          // List
          Expanded(
            child: BlocBuilder<BrandsBloc, BrandsState>(
              builder: (context, state) {
                if (state.isInitialLoading) {
                  return const Center(child: CustomLoadingIndicator());
                }

                if (state.items.isEmpty) {
                  return Center(
                    child: EmptyStateWidget(
                      svgPath: ImagesPath.noProductFoundSvg,
                      title: 'No brands found',
                      subtitle: 'No brands match your search yet.',
                      actionText: 'Refresh',
                      onAction: () {
                        context.read<BrandsBloc>().add(LoadBrandsInitial());
                      },
                    ),
                  );
                }

                return ListView.separated(
                  controller: _scrollController,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  itemCount: state.items.length + (state.hasMore ? 1 : 0),
                  separatorBuilder: (context, index) =>
                      const SizedBox(height: 4),
                  itemBuilder: (context, index) {
                    if (index == state.items.length) {
                      return const Center(
                        child: Padding(
                          padding: EdgeInsets.all(16.0),
                          child: CustomLoadingIndicator(
                            size: 20,
                            strokeWidth: 2,
                          ),
                        ),
                      );
                    }

                    final brand = state.items[index];
                    final isSelected = brand.id == widget.selectedBrandId;

                    return _BrandTile(
                      title: brand.title,
                      isSelected: isSelected,
                      onTap: () {
                        widget.onSelected(brand);
                        Navigator.pop(context);
                      },
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _BrandTile extends StatelessWidget {
  final String title;
  final bool isSelected;
  final VoidCallback onTap;

  const _BrandTile({
    required this.title,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final String initial = title.trim().isEmpty
        ? "?"
        : title.trim().characters.first.toUpperCase();

    final Color bg = isSelected
        ? AppColors.primaryColor.withValues(alpha: 0.10)
        : Colors.transparent;
    final Color border = isSelected
        ? AppColors.primaryColor
        : Colors.transparent;

    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: border, width: 1),
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: isSelected
                      ? AppColors.primaryColor
                      : AppColors.primaryColor.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: Text(
                  initial,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color:
                        isSelected ? Colors.black : AppColors.primaryColor,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    fontSize: 14.5,
                    fontWeight:
                        isSelected ? FontWeight.w700 : FontWeight.w500,
                    color: theme.colorScheme.tertiary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (isSelected)
                Container(
                  width: 22,
                  height: 22,
                  decoration: const BoxDecoration(
                    color: AppColors.primaryColor,
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: const Icon(
                    Icons.check_rounded,
                    size: 14,
                    color: Colors.black,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SheetClose extends StatelessWidget {
  final VoidCallback onTap;

  const _SheetClose({required this.onTap});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: theme.colorScheme.outlineVariant.withValues(alpha: 0.35),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 34,
          height: 34,
          child: Icon(
            Icons.close_rounded,
            size: 18,
            color: theme.colorScheme.tertiary.withValues(alpha: 0.75),
          ),
        ),
      ),
    );
  }
}
