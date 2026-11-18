import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_dropdown.dart';

/// Step 3 — Policies & features.
///
/// Redesign: three section cards.
///   Order quantities  → horizontal – [value] + steppers (was a vertical
///                       ▲/▼ arrow column glued to a text field).
///   Policies          → full-width switch rows separated by hairlines
///                       (was a loose `Wrap` of switch-labels-in-a-column).
///                       Dependent controls (attachment mode, cancelable
///                       till, returnable days) inline under their toggle.
///   Warranty & guarantee → value input + unit dropdown; helper below.
///
/// Bloc/state wiring is unchanged.
class PoliciesFeaturesStep extends StatefulWidget {
  const PoliciesFeaturesStep({super.key});

  @override
  State<PoliciesFeaturesStep> createState() => _PoliciesFeaturesStepState();
}

class _PoliciesFeaturesStepState extends State<PoliciesFeaturesStep> {
  final TextEditingController _minOrderController =
      TextEditingController(text: "1");
  final TextEditingController _stepSizeController =
      TextEditingController(text: "1");
  final TextEditingController _totalAllowedController =
      TextEditingController(text: "");
  final TextEditingController _returnableDaysController =
      TextEditingController(text: "");
  final TextEditingController _warrantyController =
      TextEditingController(text: "");
  final TextEditingController _guaranteeController =
      TextEditingController(text: "");

  final FocusNode _minOrderFocus = FocusNode();
  final FocusNode _stepSizeFocus = FocusNode();
  final FocusNode _totalAllowedFocus = FocusNode();
  final FocusNode _returnableDaysFocus = FocusNode();
  final FocusNode _warrantyFocus = FocusNode();
  final FocusNode _guaranteeFocus = FocusNode();

  bool _isReturnable = false;
  bool _isCancelable = false;
  bool _isCodAllowed = true;
  bool _requiredOTP = false;
  bool _featuredProduct = false;
  bool _isAttachmentRequired = false;
  String? _attachmentMode;
  String? _cancelableTill;
  String _warrantyUnit = "Month";
  String _guaranteeUnit = "Month";

  void _updateBloc() {
    final bloc = context.read<AddProductBloc>();
    final currentData = bloc.state.productData;
    bloc.add(
      UpdateProductData(
        currentData.copyWith(
          minimumOrderQuantity: int.tryParse(_minOrderController.text) ?? 1,
          quantityStepSize: int.tryParse(_stepSizeController.text) ?? 1,
          totalAllowedQuantity:
              int.tryParse(_totalAllowedController.text) ?? 0,
          isReturnable: _isReturnable,
          returnableDays:
              int.tryParse(_returnableDaysController.text) ?? 0,
          isCancelable: _isCancelable,
          cancelableTill: _cancelableTill,
          isCodAllowed: _isCodAllowed,
          requiresOtp: _requiredOTP,
          featured: _featuredProduct,
          isAttachmentRequired: _isAttachmentRequired,
          attachmentMode: _attachmentMode,
          warrantyPeriod: _warrantyController.text.isNotEmpty
              ? "${_warrantyController.text} $_warrantyUnit"
              : null,
          guaranteePeriod: _guaranteeController.text.isNotEmpty
              ? "${_guaranteeController.text} $_guaranteeUnit"
              : null,
        ),
      ),
    );
  }

  void _updateController(TextEditingController controller, int delta) {
    int current = int.tryParse(controller.text) ?? 0;
    int newValue = current + delta;
    if (newValue < 0) newValue = 0;
    setState(() {
      controller.text = newValue.toString();
    });
    _updateBloc();
  }

  @override
  void initState() {
    super.initState();
    // Initialize from existing state
    final productData = context.read<AddProductBloc>().state.productData;

    _minOrderController.text = productData.minimumOrderQuantity.toString();
    _stepSizeController.text = productData.quantityStepSize.toString();
    _totalAllowedController.text =
        productData.totalAllowedQuantity.toString();
    _returnableDaysController.text =
        productData.returnableDays > 0
            ? productData.returnableDays.toString()
            : "";

    _isReturnable = productData.isReturnable;
    _isCancelable = productData.isCancelable;
    _isCodAllowed = productData.isCodAllowed;
    _requiredOTP = productData.requiresOtp;
    _featuredProduct = productData.featured;
    _isAttachmentRequired = productData.isAttachmentRequired;
    _attachmentMode = productData.attachmentMode;
    // Drop legacy values that are no longer accepted by the API so the
    // dropdown doesn't start on an unselectable option.
    _cancelableTill = AddProductBloc.cancelableTillOptions
            .contains(productData.cancelableTill)
        ? productData.cancelableTill
        : null;

    // Parse Warranty
    if (productData.warrantyPeriod != null &&
        productData.warrantyPeriod!.isNotEmpty) {
      final parts = productData.warrantyPeriod!.trim().split(' ');
      if (parts.isNotEmpty) _warrantyController.text = parts[0];
      if (parts.length > 1) {
        final unit = parts[1].toLowerCase();
        if (unit.contains('day')) {
          _warrantyUnit = "Days";
        } else if (unit.contains('month')) {
          _warrantyUnit = "Month";
        } else if (unit.contains('year')) {
          _warrantyUnit = "Year";
        }
      }
    }

    // Parse Guarantee
    if (productData.guaranteePeriod != null &&
        productData.guaranteePeriod!.isNotEmpty) {
      final parts = productData.guaranteePeriod!.trim().split(' ');
      if (parts.isNotEmpty) _guaranteeController.text = parts[0];
      if (parts.length > 1) {
        final unit = parts[1].toLowerCase();
        if (unit.contains('day')) {
          _guaranteeUnit = "Days";
        } else if (unit.contains('month')) {
          _guaranteeUnit = "Month";
        } else if (unit.contains('year')) {
          _guaranteeUnit = "Year";
        }
      }
    }
  }

  @override
  void dispose() {
    _minOrderController.dispose();
    _stepSizeController.dispose();
    _totalAllowedController.dispose();
    _returnableDaysController.dispose();
    _warrantyController.dispose();
    _guaranteeController.dispose();
    _minOrderFocus.dispose();
    _stepSizeFocus.dispose();
    _totalAllowedFocus.dispose();
    _returnableDaysFocus.dispose();
    _warrantyFocus.dispose();
    _guaranteeFocus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // ── Order quantities ─────────────────────────────────────────
        _SectionCard(
          title: "Order quantities",
          children: [
            _NumberStepperField(
              label: context.tr('minimumOrderQuantity'),
              isRequired: true,
              controller: _minOrderController,
              focusNode: _minOrderFocus,
              subLabel: context.tr('byDefaultMinimumQuantity'),
              onDelta: (d) => _updateController(_minOrderController, d),
              onChanged: (_) => _updateBloc(),
            ),
            const SizedBox(height: 18),
            _NumberStepperField(
              label: context.tr('quantityStepSize'),
              isRequired: true,
              controller: _stepSizeController,
              focusNode: _stepSizeFocus,
              onDelta: (d) => _updateController(_stepSizeController, d),
              onChanged: (_) => _updateBloc(),
            ),
            const SizedBox(height: 18),
            _NumberStepperField(
              label: context.tr('totalAllowedQuantity'),
              isRequired: true,
              controller: _totalAllowedController,
              focusNode: _totalAllowedFocus,
              hint: context.tr('enterQuantity'),
              subLabel: context.tr('leaveEmptyForUnlimitedQuantity'),
              onDelta: (d) => _updateController(_totalAllowedController, d),
              onChanged: (_) => _updateBloc(),
            ),
          ],
        ),

        const SizedBox(height: 14),

        // ── Policies (switches + dependent controls) ─────────────────
        _SectionCard(
          title: "Policies",
          children: [
            _SwitchTile(
              label: context.tr('isReturnable'),
              subLabel: "Buyer can request a return within a window.",
              value: _isReturnable,
              onChanged: (val) {
                setState(() => _isReturnable = val);
                _updateBloc();
              },
            ),
            if (_isReturnable)
              _InlineDependent(
                child: _NumberStepperField(
                  label: context.tr('returnableDays'),
                  controller: _returnableDaysController,
                  focusNode: _returnableDaysFocus,
                  hint: context.tr('eg7'),
                  subLabel: context.tr('requiredIfReturnable'),
                  onDelta: (d) =>
                      _updateController(_returnableDaysController, d),
                  onChanged: (_) => _updateBloc(),
                ),
              ),
            _hair(context),
            _SwitchTile(
              label: context.tr('isCancelable'),
              subLabel: "Buyer can cancel this order before dispatch.",
              value: _isCancelable,
              onChanged: (val) {
                setState(() => _isCancelable = val);
                _updateBloc();
              },
            ),
            if (_isCancelable)
              _InlineDependent(
                child: CustomDropdown<String>(
                  label: context.tr('cancelableTill'),
                  hint: context.tr('selectOption'),
                  value: _cancelableTill,
                  items: [
                    CustomDropdownItem(
                      label: context.tr('cancelableTillUnfulfilled'),
                      value: "unfulfilled",
                    ),
                    CustomDropdownItem(
                      label: context.tr('cancelableTillInProgress'),
                      value: "in_progress",
                    ),
                    CustomDropdownItem(
                      label: context.tr('cancelableTillOnHold'),
                      value: "on_hold",
                    ),
                  ],
                  onChanged: (val) {
                    setState(() => _cancelableTill = val);
                    _updateBloc();
                  },
                ),
              ),
            _hair(context),
            _SwitchTile(
              label: "Allow COD",
              subLabel:
                  "If off, Cash on Delivery is hidden whenever this product is in the cart.",
              value: _isCodAllowed,
              onChanged: (val) {
                setState(() => _isCodAllowed = val);
                _updateBloc();
              },
            ),
            _hair(context),
            _SwitchTile(
              label: context.tr('requiredOtp'),
              subLabel: "Delivery is unlocked only after a valid OTP.",
              value: _requiredOTP,
              onChanged: (val) {
                setState(() => _requiredOTP = val);
                _updateBloc();
              },
            ),
            _hair(context),
            _SwitchTile(
              label: context.tr('featuredProduct'),
              subLabel: "Highlighted in listings and home feed.",
              value: _featuredProduct,
              onChanged: (val) {
                setState(() => _featuredProduct = val);
                _updateBloc();
              },
            ),
            _hair(context),
            _SwitchTile(
              label: context.tr('isAttachmentRequired'),
              subLabel: "Buyer must upload a file when adding to cart.",
              value: _isAttachmentRequired,
              onChanged: (val) {
                setState(() {
                  _isAttachmentRequired = val;
                  if (val && _attachmentMode == null) {
                    _attachmentMode = "required";
                  }
                });
                _updateBloc();
              },
            ),
            if (_isAttachmentRequired)
              _InlineDependent(
                child: CustomDropdown<String>(
                  label: "Attachment mode",
                  hint: context.tr('selectOption'),
                  value: _attachmentMode,
                  items: [
                    CustomDropdownItem(label: "Required", value: "required"),
                    CustomDropdownItem(label: "Optional", value: "optional"),
                  ],
                  onChanged: (val) {
                    setState(() => _attachmentMode = val);
                    _updateBloc();
                  },
                ),
              ),
          ],
        ),

        const SizedBox(height: 14),

        // ── Warranty & guarantee ────────────────────────────────────
        _SectionCard(
          title: "Warranty & guarantee",
          children: [
            _PeriodField(
              label: context.tr('warrantyPeriod'),
              controller: _warrantyController,
              focusNode: _warrantyFocus,
              unitValue: _warrantyUnit,
              onUnitChanged: (val) {
                setState(() => _warrantyUnit = val!);
                _updateBloc();
              },
              onChanged: (_) => _updateBloc(),
            ),
            const SizedBox(height: 18),
            _PeriodField(
              label: context.tr('guaranteePeriod'),
              controller: _guaranteeController,
              focusNode: _guaranteeFocus,
              unitValue: _guaranteeUnit,
              onUnitChanged: (val) {
                setState(() => _guaranteeUnit = val!);
                _updateBloc();
              },
              onChanged: (_) => _updateBloc(),
            ),
          ],
        ),

        const SizedBox(height: 12),
      ],
    );
  }

  // Hairline used between switch rows inside the Policies card.
  Widget _hair(BuildContext context) => Divider(
        height: 1,
        thickness: 1,
        color: Theme.of(context)
            .colorScheme
            .outlineVariant
            .withValues(alpha: 0.5),
      );
}

/// Section card shell (duplicated locally per-batch until a shared wizard
/// widgets file lands — matches the shell used in product_info_step).
class _SectionCard extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const _SectionCard({
    required this.title,
    required this.children,
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

/// Horizontal – [value] + stepper embedded in an outlined 8-radius shell.
/// Numeric text is centred; buttons occupy the full field height so the
/// tap targets are comfortable on mobile.
class _NumberStepperField extends StatelessWidget {
  final String label;
  final bool isRequired;
  final TextEditingController controller;
  final FocusNode focusNode;
  final String? hint;
  final String? subLabel;
  final ValueChanged<int> onDelta;
  final ValueChanged<String> onChanged;

  const _NumberStepperField({
    required this.label,
    required this.controller,
    required this.focusNode,
    required this.onDelta,
    required this.onChanged,
    this.isRequired = false,
    this.hint,
    this.subLabel,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final borderColor =
        isDark ? Colors.grey.shade700 : Colors.grey.shade300;
    final tertiary = theme.colorScheme.tertiary;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
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
              if (isRequired)
                const TextSpan(
                  text: ' *',
                  style: TextStyle(
                    color: Colors.red,
                    fontWeight: FontWeight.w600,
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF0D1117) : Colors.white,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: borderColor),
          ),
          child: Row(
            children: [
              _StepperBtn(
                icon: Icons.remove_rounded,
                onTap: () => onDelta(-1),
              ),
              Container(
                width: 1,
                height: 28,
                color: borderColor,
              ),
              Expanded(
                child: TextField(
                  controller: controller,
                  focusNode: focusNode,
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  cursorColor: AppColors.primaryColor,
                  onChanged: onChanged,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: tertiary,
                  ),
                  decoration: InputDecoration(
                    hintText: hint ?? "0",
                    hintStyle: TextStyle(
                      color: Colors.grey.shade500,
                      fontSize: 15,
                      fontWeight: FontWeight.w500,
                    ),
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding:
                        const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
              Container(
                width: 1,
                height: 28,
                color: borderColor,
              ),
              _StepperBtn(
                icon: Icons.add_rounded,
                onTap: () => onDelta(1),
              ),
            ],
          ),
        ),
        if (subLabel != null) ...[
          const SizedBox(height: 6),
          Text(
            subLabel!,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: tertiary.withValues(alpha: 0.55),
              height: 1.35,
            ),
          ),
        ],
      ],
    );
  }
}

class _StepperBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _StepperBtn({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: SizedBox(
          width: 44,
          height: 46,
          child: Icon(
            icon,
            size: 20,
            color: Theme.of(context)
                .colorScheme
                .tertiary
                .withValues(alpha: 0.75),
          ),
        ),
      ),
    );
  }
}

/// A full-width switch row: label + optional sub-label on the left, Switch
/// on the right. Meant to be stacked inside the Policies card with
/// hairline dividers between rows.
class _SwitchTile extends StatelessWidget {
  final String label;
  final String? subLabel;
  final bool value;
  final ValueChanged<bool> onChanged;

  const _SwitchTile({
    required this.label,
    required this.value,
    required this.onChanged,
    this.subLabel,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: () => onChanged(!value),
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    label,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: theme.colorScheme.tertiary,
                    ),
                  ),
                  if (subLabel != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      subLabel!,
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w500,
                        color: theme.colorScheme.tertiary
                            .withValues(alpha: 0.55),
                        height: 1.35,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 12),
            Switch(
              value: value,
              onChanged: onChanged,
              activeThumbColor: Colors.white,
              activeTrackColor: AppColors.primaryColor,
              inactiveThumbColor: Colors.white,
              inactiveTrackColor: Colors.grey.shade300,
              trackOutlineColor:
                  WidgetStateProperty.all(Colors.transparent),
              trackOutlineWidth: WidgetStateProperty.all(0),
            ),
          ],
        ),
      ),
    );
  }
}

/// Container for a dependent control that appears under a switch —
/// visually offsets it slightly and adds a top gap so it reads as
/// "belonging to" the switch above.
class _InlineDependent extends StatelessWidget {
  final Widget child;
  const _InlineDependent({required this.child});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 4, bottom: 12),
      child: child,
    );
  }
}

/// Period field — number input + unit dropdown side-by-side, with a
/// human-readable helper line below (e.g. "Warranty period is for 1 year
/// 3 months").
class _PeriodField extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final FocusNode focusNode;
  final String unitValue;
  final ValueChanged<String?> onUnitChanged;
  final ValueChanged<String> onChanged;

  const _PeriodField({
    required this.label,
    required this.controller,
    required this.focusNode,
    required this.unitValue,
    required this.onUnitChanged,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final borderColor =
        isDark ? Colors.grey.shade700 : Colors.grey.shade300;
    final tertiary = theme.colorScheme.tertiary;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: tertiary,
          ),
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              flex: 2,
              child: Container(
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF0D1117) : Colors.white,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: borderColor),
                ),
                child: TextField(
                  controller: controller,
                  focusNode: focusNode,
                  keyboardType: TextInputType.number,
                  cursorColor: AppColors.primaryColor,
                  onChanged: onChanged,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: tertiary,
                  ),
                  decoration: InputDecoration(
                    hintText: "0",
                    hintStyle: TextStyle(
                      color: Colors.grey.shade500,
                      fontSize: 15,
                    ),
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 14,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              flex: 1,
              child: CustomDropdown<String>(
                value: unitValue,
                items: [
                  CustomDropdownItem(
                    label: context.tr('days', fallback: "Days"),
                    value: "Days",
                  ),
                  CustomDropdownItem(
                    label: context.tr('month', fallback: "Month"),
                    value: "Month",
                  ),
                  CustomDropdownItem(
                    label: context.tr('year', fallback: "Year"),
                    value: "Year",
                  ),
                ],
                onChanged: onUnitChanged,
              ),
            ),
          ],
        ),
        if (_helperText(context).isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            _helperText(context),
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: tertiary.withValues(alpha: 0.55),
              height: 1.35,
            ),
          ),
        ],
      ],
    );
  }

  String _helperText(BuildContext context) {
    if (controller.text.isEmpty) return "";
    final double? value = double.tryParse(controller.text);
    if (value == null) return "";

    if (unitValue == "Year") {
      int years = value.floor();
      int months = ((value - years) * 12).round();
      if (months == 12) {
        years++;
        months = 0;
      }
      final parts = <String>[];
      if (years > 0) {
        parts.add(
          "$years ${years > 1 ? (context.tr('years', fallback: "years")) : (context.tr('year', fallback: "year"))}",
        );
      }
      if (months > 0) {
        parts.add(
          "$months ${months > 1 ? (context.tr('months', fallback: "months")) : (context.tr('month', fallback: "month"))}",
        );
      }
      if (parts.isEmpty) {
        return "$label ${context.tr('isFor', fallback: "is for")} 0 ${context.tr('months', fallback: "months")}";
      }
      return "$label ${context.tr('isFor', fallback: "is for")} ${parts.join(' ${context.tr('and', fallback: "and")} ')}";
    }

    // Month / Days
    int months = value.round();
    return "$label ${context.tr('isFor', fallback: "is for")} $months ${months != 1 ? (context.tr('months', fallback: "months")) : (context.tr('month', fallback: "month"))}";
  }
}
