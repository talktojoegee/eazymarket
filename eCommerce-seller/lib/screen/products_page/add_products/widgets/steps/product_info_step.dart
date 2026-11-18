import 'package:country_picker/country_picker.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/brands/bloc/brands_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/brand_selection_sheet.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/critical_field_shield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_dropdown.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';

/// Step 2 — Product info.
///
/// Redesign: fields grouped into two section cards ("Basics", "Custom
/// fields"). Brand and Made-in become explicit chevron-selector rows
/// (not disguised text fields) so it's clear tapping opens a sheet.
/// Custom fields get an inline delete affordance and a full-width
/// ghost-amber "+ Add field" button. Bloc/state wiring unchanged.
class ProductInfoStep extends StatefulWidget {
  const ProductInfoStep({super.key});

  @override
  State<ProductInfoStep> createState() => _ProductInfoStepState();
}

class _ProductInfoStepState extends State<ProductInfoStep> {
  final TextEditingController _brandController = TextEditingController();
  final List<Map<String, dynamic>> _customFields = [];
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _hsnController = TextEditingController();
  final TextEditingController _madeInController = TextEditingController();
  final FocusNode _titleFocus = FocusNode();
  final FocusNode _hsnFocus = FocusNode();

  String? _selectedIndicator;
  int? _selectedBrandId;

  @override
  void initState() {
    super.initState();
    // Load brands
    context.read<BrandsBloc>().add(LoadBrandsInitial());

    // Initialize from existing state
    final productData = context.read<AddProductBloc>().state.productData;
    _titleController.text = productData.title ?? "";
    _hsnController.text = productData.hsnCode ?? "";
    _madeInController.text = productData.madeIn ?? "";
    _selectedIndicator = productData.indicator;
    _selectedBrandId = productData.brandId;
    _brandController.text = productData.brandName ?? "";

    if (productData.customFields.isNotEmpty) {
      for (var field in productData.customFields) {
        if (field.containsKey('key') && field.containsKey('value')) {
          _customFields.add({
            'key': TextEditingController(text: field['key']),
            'value': TextEditingController(text: field['value']),
            'keyFocus': FocusNode(),
            'valueFocus': FocusNode(),
          });
        }
      }
    }
  }

  void _showBrandSelection() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => BrandSelectionSheet(
        selectedBrandId: _selectedBrandId,
        onSelected: (brand) {
          setState(() {
            _selectedBrandId = brand.id;
            _brandController.text = brand.title;
          });
          _updateBloc();
        },
      ),
    );
  }

  void _addCustomField() {
    setState(() {
      _customFields.add({
        'key': TextEditingController(),
        'value': TextEditingController(),
        'keyFocus': FocusNode(),
        'valueFocus': FocusNode(),
      });
    });
  }

  void _removeCustomField(int index) {
    setState(() {
      _customFields[index]['key']?.dispose();
      _customFields[index]['value']?.dispose();
      _customFields[index]['keyFocus']?.dispose();
      _customFields[index]['valueFocus']?.dispose();
      _customFields.removeAt(index);
    });
    _updateBloc();
  }

  void _showCountryPicker() {
    showCountryPicker(
      context: context,
      useSafeArea: true,
      showSearch: true,
      countryListTheme: CountryListThemeData(
        backgroundColor: Theme.of(context).cardColor,
        inputDecoration: InputDecoration(
          hintText: 'Search',
          hintStyle: const TextStyle(
            color: Colors.black54,
            fontSize: 15,
            fontWeight: FontWeight.w400,
          ),
          filled: true,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 14,
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(
              color: AppColors.primaryColor,
              width: 1.5,
            ),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Colors.red, width: 1),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Colors.red, width: 1.5),
          ),
          disabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: Colors.grey.shade300, width: 1),
          ),
        ),
        searchTextStyle: const TextStyle(color: AppColors.primaryColor),
      ),
      showPhoneCode: false,
      searchAutofocus: true,
      onSelect: (Country country) {
        setState(() {
          _madeInController.text = country.name;
        });
        _updateBloc();
      },
    );
  }

  @override
  void dispose() {
    _titleController.dispose();
    _hsnController.dispose();
    _madeInController.dispose();
    _brandController.dispose();
    _titleFocus.dispose();
    _hsnFocus.dispose();
    for (var field in _customFields) {
      field['key']?.dispose();
      field['value']?.dispose();
      field['keyFocus']?.dispose();
      field['valueFocus']?.dispose();
    }
    super.dispose();
  }

  void _updateBloc() {
    final bloc = context.read<AddProductBloc>();
    final currentData = bloc.state.productData;

    final List<Map<String, String>> customFieldsData = [];
    for (var field in _customFields) {
      final key = (field['key'] as TextEditingController).text;
      final value = (field['value'] as TextEditingController).text;
      if (key.isNotEmpty || value.isNotEmpty) {
        customFieldsData.add({'key': key, 'value': value});
      }
    }

    bloc.add(
      UpdateProductData(
        currentData.copyWith(
          title: _titleController.text,
          brandId: _selectedBrandId,
          brandName: _brandController.text,
          madeIn: _madeInController.text,
          hsnCode: _hsnController.text,
          indicator: _selectedIndicator,
          customFields: customFieldsData,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionCard(
          title: "Basics",
          children: [
            CustomTextField(
              label: context.tr('title'),
              criticalFieldKey: 'title',
              isRequired: true,
              hint: context.tr('enterProductTitle'),
              controller: _titleController,
              focusNode: _titleFocus,
              onChanged: (v) => _updateBloc(),
            ),
            const SizedBox(height: 18),
            _SelectorRow(
              label: context.tr('brand'),
              criticalFieldKey: 'brand_id',
              value: _brandController.text,
              placeholder: context.tr('selectBrand'),
              onTap: _showBrandSelection,
            ),
            const SizedBox(height: 18),
            _SelectorRow(
              label: context.tr('madeIn'),
              value: _madeInController.text,
              placeholder: context.tr('selectCountry'),
              onTap: _showCountryPicker,
            ),
            const SizedBox(height: 18),
            CustomTextField(
              label: context.tr('hsnCode'),
              hint: context.tr('enterHsnCode'),
              controller: _hsnController,
              focusNode: _hsnFocus,
              onChanged: (v) => _updateBloc(),
            ),
            const SizedBox(height: 18),
            CustomDropdown<String>(
              label: context.tr('indicator'),
              criticalFieldKey: 'indicator',
              hint: context.tr('selectIndicator'),
              value: _selectedIndicator,
              items: [
                CustomDropdownItem(label: context.tr('none'), value: ''),
                CustomDropdownItem(label: context.tr('veg'), value: "veg"),
                CustomDropdownItem(label: context.tr('nonVeg'), value: "non-veg"),
              ],
              onChanged: (val) {
                setState(() => _selectedIndicator = val);
                _updateBloc();
              },
            ),
          ],
        ),
        const SizedBox(height: 14),
        _SectionCard(
          title: context.tr('customFields'),
          trailing: _customFields.isEmpty
              ? null
              : Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.outlineVariant
                        .withValues(alpha: 0.4),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    "${_customFields.length}",
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: theme.colorScheme.tertiary
                          .withValues(alpha: 0.7),
                    ),
                  ),
                ),
          children: [
            if (_customFields.isEmpty) ...[
              Text(
                context.tr('addCustomFieldHelper'),
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: theme.colorScheme.tertiary
                      .withValues(alpha: 0.55),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 12),
            ] else ...[
              ...List.generate(
                _customFields.length,
                (index) => Padding(
                  padding: EdgeInsets.only(
                    bottom: index == _customFields.length - 1 ? 4 : 12,
                  ),
                  child: _buildCustomFieldRow(index, screenType),
                ),
              ),
              const SizedBox(height: 8),
            ],
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _addCustomField,
                icon: const Icon(Icons.add_rounded, size: 18),
                label: Text(context.tr('addField')),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.primaryColor,
                  side: const BorderSide(
                    color: AppColors.primaryColor,
                    width: 1.5,
                  ),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  textStyle: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildCustomFieldRow(int index, ScreenType screenType) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(
          flex: 2,
          child: CustomTextField(
            controller: _customFields[index]['key'],
            focusNode: _customFields[index]['keyFocus'],
            hint: context.tr('fieldName'),
            onChanged: (v) => _updateBloc(),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          flex: 2,
          child: CustomTextField(
            controller: _customFields[index]['value'],
            focusNode: _customFields[index]['valueFocus'],
            hint: context.tr('value'),
            onChanged: (v) => _updateBloc(),
          ),
        ),
        const SizedBox(width: 6),
        _DeleteChip(onTap: () => _removeCustomField(index)),
      ],
    );
  }
}

/// Reusable section card shell — 16-radius, cardColor, hairline border,
/// header + hairline divider + 16px padded content.
class _SectionCard extends StatelessWidget {
  final String title;
  final List<Widget> children;
  final Widget? trailing;

  const _SectionCard({
    required this.title,
    required this.children,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: theme.colorScheme.tertiary,
                    ),
                  ),
                ),
                if (trailing != null) trailing!,
              ],
            ),
          ),
          Divider(
            height: 1,
            thickness: 1,
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: children,
            ),
          ),
        ],
      ),
    );
  }
}

/// Chevron-selector row — a tappable outlined container that reads clearly
/// as "opens a picker". Replaces the AbsorbPointer(TextField) pattern.
class _SelectorRow extends StatelessWidget {
  final String label;
  final String value;
  final String placeholder;
  final VoidCallback onTap;
  final String? criticalFieldKey;

  const _SelectorRow({
    required this.label,
    required this.value,
    required this.placeholder,
    required this.onTap,
    this.criticalFieldKey,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final tertiary = theme.colorScheme.tertiary;
    final hasValue = value.trim().isNotEmpty;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        RichText(
          text: TextSpan(
            text: label,
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w500,
              color: tertiary,
            ),
            children: [
              if (criticalFieldKey != null &&
                  HiveStorage.isCriticalField(criticalFieldKey!))
                WidgetSpan(
                  alignment: PlaceholderAlignment.middle,
                  child: Padding(
                    padding: const EdgeInsets.only(left: 4.0),
                    child: CriticalFieldShield(fieldKey: criticalFieldKey!),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Material(
          color: isDark ? const Color(0xFF0D1117) : Colors.white,
          borderRadius: BorderRadius.circular(8),
          child: InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: onTap,
            child: Container(
              padding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 14,
              ),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: isDark
                      ? Colors.grey.shade700
                      : Colors.grey.shade300,
                ),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      hasValue ? value : placeholder,
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight:
                            hasValue ? FontWeight.w600 : FontWeight.w400,
                        color: hasValue ? tertiary : Colors.grey.shade500,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Icon(
                    Icons.keyboard_arrow_down_rounded,
                    size: 22,
                    color: tertiary.withValues(alpha: 0.55),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// Compact red-tinted delete affordance used inside the custom-fields row.
class _DeleteChip extends StatelessWidget {
  final VoidCallback onTap;

  const _DeleteChip({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.errorColor.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          width: 42,
          height: 46,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: AppColors.errorColor.withValues(alpha: 0.35),
            ),
          ),
          child: const Icon(
            Icons.delete_outline_rounded,
            size: 20,
            color: AppColors.errorColor,
          ),
        ),
      ),
    );
  }
}
