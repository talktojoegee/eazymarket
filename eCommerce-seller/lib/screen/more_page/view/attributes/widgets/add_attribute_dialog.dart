import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/model/attributes_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/bloc/attributes_bloc/attributes_bloc.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_dropdown.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/bloc/screen_size/screen_size_bloc.dart';

class AddAttributeDialog extends StatefulWidget {
  final Attribute? attribute;
  const AddAttributeDialog({super.key, this.attribute});

  @override
  State<AddAttributeDialog> createState() => _AddAttributeDialogState();
}

class _AddAttributeDialogState extends State<AddAttributeDialog> {
  final _formKey = GlobalKey<FormState>();
  late TextEditingController _titleController;
  late TextEditingController _labelController;
  String _selectedSwatchType = 'text';

  final List<String> _swatchTypes = ['text', 'color', 'image'];

  @override
  void initState() {
    super.initState();
    _titleController = TextEditingController(
      text: widget.attribute?.title ?? '',
    );
    _labelController = TextEditingController(
      text: widget.attribute?.label ?? '',
    );
    _selectedSwatchType = widget.attribute?.swatcheType.toLowerCase() ?? 'text';
    if (!_swatchTypes.contains(_selectedSwatchType)) {
      _selectedSwatchType = 'text';
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _labelController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.read<ScreenSizeBloc>().state.screenType;
    return BlocListener<AttributesBloc, AttributesState>(
      listener: (context, state) {
        if (state.operationSuccess == true) {
          Navigator.pop(context);
          context.read<AttributesBloc>().add(ClearAttributeOperationState());
        } else if (state.operationSuccess == false && state.error != null) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(state.error!), backgroundColor: Colors.red),
          );
          context.read<AttributesBloc>().add(ClearAttributeOperationState());
        }
      },
      child: BlocBuilder<AttributesBloc, AttributesState>(
        builder: (context, state) {
          return Container(
            // Capped and scrollable so the keyboard can't push the form
            // off-screen on shorter devices.
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.9,
            ),
            decoration: BoxDecoration(
              color: Theme.of(context).scaffoldBackgroundColor,
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(20),
              ),
            ),
            padding: EdgeInsets.only(
              bottom: MediaQuery.of(context).viewInsets.bottom + 20,
              top: 20,
              left: 20,
              right: 20,
            ),
            child: SingleChildScrollView(
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          widget.attribute == null
                              ? context.tr('addAttribute', fallback: "Create New Attribute")
                              : context.tr('editAttribute', fallback: "Edit Attribute"),
                          style: TextStyle(
                            fontSize: UIUtils.body(screenType),
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(context),
                          icon: const Icon(Icons.close),
                        ),
                      ],
                    ),
                    const Divider(),
                    SizedBox(height: UIUtils.gapMD(screenType)),
                    CustomTextField(
                      label: context.tr('title', fallback: "Title"),
                      controller: _titleController,
                      hint: context.tr('attributeName', fallback: "Attribute Name"),
                      isRequired: true,
                      validator: (v) => v!.isEmpty ? "Title is required" : null,
                    ),
                    SizedBox(height: UIUtils.gapMD(screenType)),
                    CustomTextField(
                      label: context.tr('label', fallback: "Label"),
                      controller: _labelController,
                      hint: context.tr('label', fallback: "Label"),
                      isRequired: true,
                      validator: (v) => v!.isEmpty ? "Label is required" : null,
                    ),
                    SizedBox(height: UIUtils.gapMD(screenType)),
                    CustomDropdown<String>(
                      label: context.tr('swatchType', fallback: "Swatch Type"),
                      value: _selectedSwatchType,
                      items: _swatchTypes.map((type) {
                        return CustomDropdownItem(
                          label: type[0].toUpperCase() + type.substring(1),
                          value: type,
                        );
                      }).toList(),
                      onChanged: (v) =>
                          setState(() => _selectedSwatchType = v!),
                      isRequired: true,
                    ),
                    SizedBox(height: UIUtils.gapLG(screenType)),
                    PrimaryButton(
                      text: widget.attribute == null
                          ? context.tr('add', fallback: "Create")
                          : context.tr('update', fallback: "Update"),
                      onPressed: _submit,
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  void _submit() {
    if (_formKey.currentState!.validate()) {
      final data = {
        "title": _titleController.text,
        "label": _labelController.text,
        "swatche_type": _selectedSwatchType,
      };

      if (!DemoGuard.shouldProceed(context)) {
        Navigator.pop(context);
        return;
      }

      if (widget.attribute == null) {
        context.read<AttributesBloc>().add(AddAttribute(data));
      } else {
        context.read<AttributesBloc>().add(
              UpdateAttribute(widget.attribute!.id, data),
            );
      }
      Navigator.pop(context); // Removed: let BlocListener handle it
    }
  }
}
