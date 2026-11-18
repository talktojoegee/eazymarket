import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_upload_area.dart';

/// One field inside a custom product section.
///
/// Redesign: card gets a proper 12-radius shell, "Field #N" is a small
/// amber pill top-left, remove is a red-tinted delete chip matching the
/// rest of the wizard (was a plain red TextButton). Layout unchanged.
class CustomProductFieldWidget extends StatefulWidget {
  final int sectionIndex;
  final int fieldIndex;
  final CustomProductField field;
  final VoidCallback onRemove;
  final Function(CustomProductField) onChanged;

  const CustomProductFieldWidget({
    super.key,
    required this.sectionIndex,
    required this.fieldIndex,
    required this.field,
    required this.onRemove,
    required this.onChanged,
  });

  @override
  State<CustomProductFieldWidget> createState() =>
      _CustomProductFieldWidgetState();
}

class _CustomProductFieldWidgetState extends State<CustomProductFieldWidget> {
  late TextEditingController _titleController;
  late TextEditingController _descriptionController;
  late TextEditingController _sortOrderController;

  @override
  void initState() {
    super.initState();
    _titleController = TextEditingController(text: widget.field.title);
    _descriptionController =
        TextEditingController(text: widget.field.description);
    _sortOrderController =
        TextEditingController(text: widget.field.sortOrder.toString());
  }

  @override
  void didUpdateWidget(CustomProductFieldWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.field.title != _titleController.text) {
      _titleController.text = widget.field.title ?? "";
    }
    if (widget.field.description != _descriptionController.text) {
      _descriptionController.text = widget.field.description ?? "";
    }
    if (widget.field.sortOrder.toString() != _sortOrderController.text) {
      _sortOrderController.text = widget.field.sortOrder.toString();
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
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: theme.colorScheme.outlineVariant.withValues(alpha: 0.15),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
        borderRadius: BorderRadius.circular(12),
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
                  "Field #${widget.fieldIndex + 1}",
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
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 2,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CustomTextField(
                      label: context.tr('title'),
                      hint: "Enter title",
                      controller: _titleController,
                      onChanged: (val) => widget.onChanged(
                        widget.field.copyWith(title: val),
                      ),
                    ),
                    const SizedBox(height: 12),
                    CustomTextField(
                      label: context.tr('shortDescription'),
                      hint: "Enter description",
                      controller: _descriptionController,
                      onChanged: (val) => widget.onChanged(
                        widget.field.copyWith(description: val),
                      ),
                    ),
                    const SizedBox(height: 12),
                    CustomTextField(
                      label: "Sort order",
                      hint: "0",
                      keyboardType: TextInputType.number,
                      controller: _sortOrderController,
                      onChanged: (val) => widget.onChanged(
                        widget.field.copyWith(
                          sortOrder: int.tryParse(val) ?? 0,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      "Image",
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                        color: theme.colorScheme.tertiary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    CustomUploadArea(
                      height: 120,
                      hint: "Browse",
                      fileName: widget.field.image,
                      onTap: () async {
                        final path = await ImagePickerUtils.pickMedia(
                          context,
                          mediaType: MediaType.image,
                        );
                        if (path != null) {
                          widget.onChanged(
                            widget.field.copyWith(image: path),
                          );
                        }
                      },
                      onRemove: () => widget.onChanged(
                        widget.field.copyWith(clearImage: true),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Red-tinted delete affordance — same visual as other wizard steps.
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
