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

/*class CustomSearchField extends StatelessWidget {
  final String? hint;
  final TextEditingController? controller;
  final ValueChanged<String>? onChanged;
  final VoidCallback? onTap;
  final bool readOnly;
  final bool enabled;
  final Widget? suffixIcon;
  final Widget? prefixIcon;
  final FocusNode? focusNode;
  final EdgeInsetsGeometry? padding;
  final Color? fillColor;
  final double? height;
  final bool? showFilters;
  final bool? showStore;
  final FilterType filterType;

  const CustomSearchField({
    super.key,
    this.hint,
    this.controller,
    this.onChanged,
    this.onTap,
    this.readOnly = false,
    this.enabled = true,
    this.suffixIcon,
    this.prefixIcon,
    this.focusNode,
    this.padding,
    this.fillColor,
    this.height,
    this.showFilters = false,
    this.showStore = false,
    this.filterType = FilterType.order,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final tertiary = theme.colorScheme.tertiary;

    final effectiveController = controller ?? TextEditingController();

    Widget _buildActionButton({
      required String icon,
      required VoidCallback onTap,
      bool isActive = false,
    }) {
      return GestureDetector(
        onTap: onTap,
        child: Container(
          height: 46,
          width: 46,
          decoration: BoxDecoration(
            color: isActive
                ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)
                : (Theme.of(context).brightness == Brightness.dark
                ? const Color(0xFF1E1E1E)
                : const Color(0xFFF5F5F7)),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Center(
            child: CustomSvg(
              svgPath: icon,
              width: 20,
              height: 20,
              color: isActive
                  ? Theme.of(context).colorScheme.primary
                  : Colors.grey.shade600,
            ),
          ),
        ),
      );
    }

    return Row(
      children: [
        /// 🔍 SEARCH (PRIMARY)
        Expanded(
          child: Container(
            height: 50,
            decoration: BoxDecoration(
              color: isDark
                  ? const Color(0xFF1E1E1E)
                  : const Color(0xFFF5F5F7),
              borderRadius: BorderRadius.circular(20),
            ),
            child: TextField(
              controller: effectiveController,
              onChanged: onChanged,
              focusNode: focusNode,
              style: TextStyle(
                fontSize: 15,
                color: theme.colorScheme.onBackground,
              ),
              decoration: InputDecoration(
                hintText: hint ?? "Search products",
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

                /// ❌ CLEAR BUTTON (smart UX)
                suffixIcon: effectiveController.text.isNotEmpty
                    ? GestureDetector(
                  onTap: () => effectiveController.clear(),
                  child: Icon(
                    Icons.close,
                    size: 18,
                    color: Colors.grey.shade500,
                  ),
                )
                    : null,

                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
        ),

        const SizedBox(width: 10),

        /// ⚙️ FILTER BUTTON (SECONDARY)
        _buildActionButton(
          icon: ImagesPath.filtersSvg,
          onTap: () => _showFilterSheet(context),
          isActive: showFilters == true,
        ),

        if (showStore == true) ...[
          const SizedBox(width: 8),

          /// 🏪 STORE BUTTON
          _buildActionButton(
            icon: ImagesPath.storeSvg,
            onTap: (){
              CustomSelectionSheet.show<Store>(
                context: context,
                title:
                context.tr('switchStore', fallback: 'Switch Store'),
                selectedValue:  state.selectedStore,
                items: state.stores
                    .where(
                      (s) =>
                  s.verificationStatus != 'not_approved' &&
                      s.visibilityStatus != 'draft',
                )
                    .map(
                      (s) => SelectionItem<Store>(
                    label: s.name,
                    sublabel: '',
                    value: s,
                    image: s.logo,
                  ),
                )
                    .toList(),
                onSelected: (selected) {
                  context.read<StoreSwitcherCubit>().selectStore(
                    selected,
                  );
                },
              );
            },
          ),
        ]
      ],
    );
  }

  void _showFilterSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => FilterSheet(type: filterType),
    );
  }



}*/

/*class CustomSearchFieldWrapper extends StatelessWidget {
  final String? hint;
  final TextEditingController? controller;
  final ValueChanged<String>? onChanged;
  final VoidCallback? onTap;
  final bool readOnly;
  final Widget? suffixIcon;

  const CustomSearchFieldWrapper({
    super.key,
    this.hint,
    this.controller,
    this.onChanged,
    this.onTap,
    this.readOnly = false,
    this.suffixIcon,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: CustomSearchField(
        hint: hint,
        controller: controller,
        onChanged: onChanged,
        onTap: onTap,
        readOnly: readOnly,
        suffixIcon: suffixIcon,
      ),
    );
  }
}*/




