import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/custom_field_widget.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';

/// One user-defined section of a product (title + description + ordered
/// list of fields).
///
/// Redesign: 14-radius card with a "Section #N" amber pill up top and a
/// red-tinted delete affordance; inputs stack vertically (was a
/// horizontal 3-column Row that wrapped badly on narrow phones); the
/// "+ Add field" trigger becomes a ghost-amber outlined button that
/// spans the width.
class CustomProductSectionWidget extends StatefulWidget {
  final int index;
  final CustomProductSection section;
  final VoidCallback onRemove;
  final Function(CustomProductSection) onChanged;

  const CustomProductSectionWidget({
    super.key,
    required this.index,
    required this.section,
    required this.onRemove,
    required this.onChanged,
  });

  @override
  State<CustomProductSectionWidget> createState() =>
      _CustomProductSectionWidgetState();
}

class _CustomProductSectionWidgetState
    extends State<CustomProductSectionWidget> {
  late TextEditingController _titleController;
  late TextEditingController _descriptionController;
  late TextEditingController _sortOrderController;

  @override
  void initState() {
    super.initState();
    _titleController = TextEditingController(text: widget.section.title);
    _descriptionController = TextEditingController(
      text: widget.section.description,
    );
    _sortOrderController = TextEditingController(
      text: widget.section.sortOrder.toString(),
    );
  }

  @override
  void didUpdateWidget(CustomProductSectionWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.section.title != _titleController.text) {
      _titleController.text = widget.section.title ?? "";
    }
    if (widget.section.description != _descriptionController.text) {
      _descriptionController.text = widget.section.description ?? "";
    }
    if (widget.section.sortOrder.toString() != _sortOrderController.text) {
      _sortOrderController.text = widget.section.sortOrder.toString();
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _sortOrderController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: theme.cardColor,
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 8,
                  vertical: 3,
                ),
                decoration: BoxDecoration(
                  color: AppColors.primaryColor.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  "Section #${widget.index + 1}",
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryColor,
                    letterSpacing: 0.03,
                  ),
                ),
              ),
              const Spacer(),
              _DeleteChip(onTap: widget.onRemove),
            ],
          ),
          const SizedBox(height: 14),
          CustomTextField(
            label: "Title",
            hint: "Enter section title",
            controller: _titleController,
            onChanged: (val) => widget.onChanged(
              widget.section.copyWith(title: val),
            ),
          ),
          const SizedBox(height: 12),
          CustomTextField(
            label: "Description",
            hint: "Enter section description",
            controller: _descriptionController,
            onChanged: (val) => widget.onChanged(
              widget.section.copyWith(description: val),
            ),
          ),
          const SizedBox(height: 12),
          CustomTextField(
            label: "Sort order",
            hint: "0",
            keyboardType: TextInputType.number,
            controller: _sortOrderController,
            onChanged: (val) => widget.onChanged(
              widget.section.copyWith(sortOrder: int.tryParse(val) ?? 0),
            ),
          ),
          const SizedBox(height: 16),
          Divider(
            height: 1,
            thickness: 1,
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Text(
                "Fields",
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                  color: theme.colorScheme.tertiary,
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 6,
                  vertical: 2,
                ),
                decoration: BoxDecoration(
                  color:
                      theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  "${widget.section.fields.length}",
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: theme.colorScheme.tertiary
                        .withValues(alpha: 0.7),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ...widget.section.fields.asMap().entries.map((entry) {
            final fieldIndex = entry.key;
            final field = entry.value;
            return CustomProductFieldWidget(
              sectionIndex: widget.index,
              fieldIndex: fieldIndex,
              field: field,
              onRemove: () {
                final newFields = List<CustomProductField>.from(
                  widget.section.fields,
                )..removeAt(fieldIndex);
                widget.onChanged(widget.section.copyWith(fields: newFields));
              },
              onChanged: (updatedField) {
                final newFields = List<CustomProductField>.from(
                  widget.section.fields,
                );
                newFields[fieldIndex] = updatedField;
                widget.onChanged(widget.section.copyWith(fields: newFields));
              },
            );
          }),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () {
                final newFields = List<CustomProductField>.from(
                  widget.section.fields,
                )..add(CustomProductField(title: ""));
                widget.onChanged(widget.section.copyWith(fields: newFields));
              },
              icon: const Icon(Icons.add_rounded, size: 18),
              label: const Text("Add field"),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.primaryColor,
                side: const BorderSide(
                  color: AppColors.primaryColor,
                  width: 1.5,
                ),
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 11,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                textStyle: const TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DeleteChip extends StatelessWidget {
  final VoidCallback onTap;

  const _DeleteChip({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.errorColor.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: onTap,
        child: Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: AppColors.errorColor.withValues(alpha: 0.35),
            ),
          ),
          child: const Icon(
            Icons.delete_outline_rounded,
            size: 16,
            color: AppColors.errorColor,
          ),
        ),
      ),
    );
  }
}
