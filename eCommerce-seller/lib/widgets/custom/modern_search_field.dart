import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/bloc/store_switcher/store_switcher_cubit.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_filter_sheet.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_selection_sheet.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_svg.dart';

class ModernSearchBar extends StatefulWidget {
  final String? hint;
  final TextEditingController? controller;
  final ValueChanged<String>? onChanged;
  final bool showFilters;
  final bool showStore;
  final FilterType filterType;

  const ModernSearchBar({
    super.key,
    this.hint,
    this.controller,
    this.onChanged,
    this.showFilters = false,
    this.showStore = false,
    required this.filterType
  });

  @override
  State<ModernSearchBar> createState() => _ModernSearchBarState();
}

class _ModernSearchBarState extends State<ModernSearchBar> {
  late TextEditingController _controller;
  final FocusNode _focusNode = FocusNode();

  @override
  void initState() {
    super.initState();
    _controller = widget.controller ?? TextEditingController();

    _controller.addListener(() {
      setState(() {});
    });
  }

  @override
  void dispose() {
    _focusNode.dispose();
    if (widget.controller == null) {
      _controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Row(
      children: [
        /// 🔍 SEARCH FIELD
        Expanded(
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            height: 52,
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
              borderRadius: BorderRadius.circular(18),

              /// 🌟 SHADOW MAGIC
              boxShadow: [
                /// Main soft shadow
                BoxShadow(
                  color: Colors.black.withValues(alpha: isDark ? 0.4 : 0.06),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),

                /// Subtle top highlight (premium feel)
                if (!isDark)
                  BoxShadow(
                    color: Colors.white.withValues(alpha: 0.8),
                    blurRadius: 10,
                    offset: const Offset(-2, -2),
                  ),
              ],

              /// Light border for sharp UI
              border: Border.all(
                color: isDark
                    ? Colors.white.withValues(alpha: 0.05)
                    : Colors.black.withValues(alpha: 0.04),
              ),
            ),
            child: TextField(
              controller: _controller,
              focusNode: _focusNode,
              onChanged: widget.onChanged,
              style: TextStyle(
                fontSize: 15,
                color: theme.colorScheme.onBackground,
              ),
              decoration: InputDecoration(
                hintText: widget.hint ?? "Search products",
                hintStyle: TextStyle(
                  color: Colors.grey.shade500,
                  fontSize: 14,
                ),

                /// 🔍 ICON
                prefixIcon: Padding(
                  padding: const EdgeInsets.all(12),
                  child: CustomSvg(
                    svgPath: ImagesPath.searchSvg,
                    width: 18,
                    height: 18,
                    color: Colors.grey.shade500,
                  ),
                ),

                /// ❌ CLEAR BUTTON
                suffixIcon: _controller.text.isNotEmpty
                    ? GestureDetector(
                  onTap: () {
                    _controller.clear();
                    widget.onChanged?.call('');
                  },
                  child: Container(
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.grey.withValues(alpha: 0.1),
                    ),
                    child: const Padding(
                      padding: EdgeInsets.all(6),
                      child: Icon(Icons.close, size: 16),
                    ),
                  ),
                )
                    : null,

                border: InputBorder.none,
                contentPadding:
                const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
        ),

        /// ⚙️ FILTER BUTTON
        if (widget.showFilters)...[
          const SizedBox(width: 10),
          _ActionButton(
            icon: ImagesPath.filtersSvg,
            onTap: () => _showFilterSheet(context),
          ),
        ],

        if (widget.showStore) ...[
          const SizedBox(width: 8),

          /// 🏪 STORE BUTTON
          BlocBuilder<StoreSwitcherCubit, StoreSwitcherState>(
            builder: (context, state) {
              return _ActionButton(
                icon: ImagesPath.storeSvg,
                onTap: () => _openStoreSheet(context, state),
              );
            },
          ),
        ],
      ],
    );
  }

  /// 🔥 FILTER BOTTOM SHEET
  void _showFilterSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useRootNavigator: true,
      shape: const RoundedRectangleBorder(
        borderRadius:
        BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => FilterSheet(type: widget.filterType),
    );
  }

  /// 🏪 STORE SWITCH SHEET
  void _openStoreSheet(
      BuildContext context, StoreSwitcherState state) {
    CustomSelectionSheet.show<Store>(
      context: context,
      title:
      context.tr('switchStore', fallback: 'Switch Store'),
      selectedValue: state.selectedStore,
      items: state.stores
          .where(
            (s) =>
        s.verificationStatus != 'not_approved' &&
            s.visibilityStatus != 'draft',
      )
          .map(
            (s) => SelectionItem<Store>(
          label: s.name,
          value: s,
          image: s.logo,
        ),
      )
          .toList(),
      onSelected: (selected) {
        context.read<StoreSwitcherCubit>().selectStore(selected);
      },
    );
  }
}

class _ActionButton extends StatelessWidget {
  final String icon;
  final VoidCallback onTap;

  const _ActionButton({
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        height: 48,
        width: 48,
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
          borderRadius: BorderRadius.circular(14),

          /// 🌟 SHADOW
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: isDark ? 0.4 : 0.08),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],

          /// subtle border
          border: Border.all(
            color: isDark
                ? Colors.white.withValues(alpha: 0.05)
                : Colors.black.withValues(alpha: 0.04),
          ),
        ),
        child: Center(
          child: CustomSvg(
            svgPath: icon,
            width: 20,
            height: 20,
            color: Colors.grey.shade600,
          ),
        ),
      ),
    );
  }
}