import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import '../../../../../config/colors.dart';
import '../../../../../utils/ui_utils.dart';
import '../../../../../widgets/custom/custom_dropdown.dart';
import '../../../../../widgets/custom/custom_textfield.dart';
import '../view/add_items_in_group_page.dart';

class AddOnsItemCard extends StatefulWidget {
  final int index;
  final AddonItemController controller;
  final ScreenType screenType;
  final VoidCallback onDelete;
  final VoidCallback onChanged;

  const AddOnsItemCard({super.key,
    required this.index,
    required this.controller,
    required this.screenType,
    required this.onDelete,
    required this.onChanged,
  });

  @override
  State<AddOnsItemCard> createState() => _AddOnsItemCardState();
}

class _AddOnsItemCardState extends State<AddOnsItemCard> {
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  "${context.tr('itemNo', fallback: "Item #")} ${widget.index + 1}",
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                if (widget.index > 0)
                  IconButton(
                    icon: const Icon(Icons.delete_outline, color: Colors.red),
                    onPressed: widget.onDelete,
                  ),
              ],
            ),
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              children: [
                CustomTextField(
                  label: context.tr('itemTitle', fallback: "Item Title"),
                  hint: "e.g Extra Cheese",
                  controller: widget.controller.titleController,
                  onChanged: (val) => widget.onChanged(),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: CustomTextField(
                        isRequired: true,
                        label: context.tr('price', fallback: "Price"),
                        hint: "e.g \$19",
                        controller: widget.controller.priceController,
                        keyboardType: TextInputType.number,
                        onChanged: (val) => widget.onChanged(),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: CustomTextField(
                        label: context.tr('cost', fallback: "Cost"),
                        hint: "e.g \$10",
                        controller: widget.controller.costController,
                        keyboardType: TextInputType.number,
                        onChanged: (val) => widget.onChanged(),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                CustomDropdown<String?>(
                  label: context.tr('indicator', fallback: "Indicator"),
                  value: widget.controller.indicator,
                  items: [
                    CustomDropdownItem(label: context.tr('none', fallback: "None"), value: null),
                    CustomDropdownItem(label: context.tr('veg', fallback: "Veg"), value: "veg"),
                    CustomDropdownItem(label: context.tr('nonVeg', fallback: "Non-Veg"), value: "non_veg"),
                  ],
                  onChanged: (val) {
                    setState(() => widget.controller.indicator = val);
                    widget.onChanged();
                  },
                ),
                const SizedBox(height: 16),
                CustomDropdown<String>(
                  label: context.tr('status', fallback: "Status"),
                  value: widget.controller.status,
                  items: [
                    CustomDropdownItem(label: context.tr('active', fallback: "Active"), value: "active"),
                    CustomDropdownItem(label: context.tr('inactive', fallback: "Inactive"), value: "inactive"),
                  ],
                  onChanged: (val) {
                    if (val != null) {
                      setState(() => widget.controller.status = val);
                      widget.onChanged();
                    }
                  },
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      context.tr('availability', fallback: "Available"),
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    Switch(
                      value: widget.controller.isAvailable,
                      onChanged: (val) {
                        setState(() => widget.controller.isAvailable = val);
                        widget.onChanged();
                      },
                      activeThumbColor: Colors.white,
                      activeTrackColor: AppColors.primaryColor,
                      inactiveThumbColor: Colors.white,
                      inactiveTrackColor: Colors.grey.shade300,
                      trackOutlineColor: WidgetStateProperty.all(Colors.transparent),
                      trackOutlineWidth: WidgetStateProperty.all(0),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
