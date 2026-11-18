import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/model/product_faq_model.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_selection_sheet.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/widgets/product_selection_sheet.dart';

class AddFaqBottomSheet extends StatefulWidget {
  final ProductFaq? faq;
  final int? productId;
  final Function(
    int? id,
    int? productId,
    String question,
    String answer,
    String status,
  )? onSave;

  const AddFaqBottomSheet({super.key, this.faq, this.productId, this.onSave});

  @override
  State<AddFaqBottomSheet> createState() => _AddFaqBottomSheetState();

  static void show(
    BuildContext context, {
    ProductFaq? faq,
    int? productId,
    Function(
      int? id,
      int? productId,
      String question,
      String answer,
      String status,
    )? onSave,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: AddFaqBottomSheet(
          faq: faq,
          productId: productId,
          onSave: onSave,
        ),
      ),
    );
  }
}

class _AddFaqBottomSheetState extends State<AddFaqBottomSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _questionController;
  late final TextEditingController _answerController;
  late String _status;
  int? _selectedProductId;
  String? _selectedProductName;

  @override
  void initState() {
    super.initState();
    _questionController = TextEditingController(text: widget.faq?.question);
    _answerController = TextEditingController(text: widget.faq?.answer);
    _status = widget.faq?.status ?? 'active';
    _selectedProductId = widget.productId ?? widget.faq?.productId;
    _selectedProductName = widget.faq?.product?.title;
  }

  @override
  void dispose() {
    _questionController.dispose();
    _answerController.dispose();
    super.dispose();
  }

  void _handleSave() {
    if (_formKey.currentState!.validate()) {
      if (!DemoGuard.shouldProceed(context)) {
        Navigator.pop(context);
        return;
      }
      if (_selectedProductId == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please select a product')),
        );
        return;
      }

      widget.onSave?.call(
        widget.faq?.id,
        _selectedProductId,
        _questionController.text,
        _answerController.text,
        _status,
      );
      Navigator.pop(context);
    }
  }

  String _getLocalizedStatus(String status, dynamic l10n) {
    switch (status.toLowerCase()) {
      case 'active':
        return (context.tr('active', fallback: 'Active')).toUpperCase();
      case 'inactive':
        return (context.tr('inactive', fallback: 'Inactive')).toUpperCase();
      default:
        return status.toUpperCase();
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final theme = Theme.of(context);
    final isEdit = widget.faq != null;

    return Container(
      // Cap the sheet so it can never grow past the screen, and let the body
      // scroll — with the keyboard up this content is taller than most phones.
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.9,
      ),
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(UIUtils.radiusLG(screenType)),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(height: 12),
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: Colors.grey.shade300,
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          Flexible(
            child: SingleChildScrollView(
              padding: UIUtils.pagePadding(screenType),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(height: UIUtils.gapMD(screenType)),
                  Text(
                    isEdit
                        ? context.tr('editProductFaq', fallback: 'Edit Product FAQ')
                        : context.tr('addProductFaq', fallback: 'Add Product FAQ'),
                    style: TextStyle(
                      fontSize: UIUtils.pageTitle(screenType),
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  SizedBox(height: UIUtils.gapLG(screenType)),
                  Form(
                    key: _formKey,
                    child: Column(
                      children: [
                        if (widget.productId == null && !isEdit) ...[
                          CustomTextField(
                            label: context.tr('product', fallback: 'Product'),
                            hint: context.tr('selectProduct', fallback: 'Select product'),
                            readOnly: true,
                            isRequired: true,
                            controller: TextEditingController(
                              text: _selectedProductName,
                            ),
                            onTap: () {
                              ProductSelectionSheet.show(
                                context,
                                onSelected: (product) {
                                  setState(() {
                                    _selectedProductId = product.id;
                                    _selectedProductName = product.title;
                                  });
                                },
                              );
                            },
                          ),
                          SizedBox(height: UIUtils.gapMD(screenType)),
                        ],
                        CustomTextField(
                          controller: _questionController,
                          label: context.tr('question', fallback: 'Question'),
                          hint: context.tr('enterQuestion', fallback: 'Enter question'),
                          isRequired: true,
                          maxLines: 2,
                          maxLength: 1000,
                          validator: (value) {
                            if (value == null || value.isEmpty) {
                              return context.tr('pleaseEnterAnswer', fallback: 'Please enter a question');
                            }
                            return null;
                          },
                        ),
                        SizedBox(height: UIUtils.gapMD(screenType)),
                        CustomTextField(
                          controller: _answerController,
                          label: context.tr('answer', fallback: 'Answer'),
                          hint: context.tr('enterAnswer', fallback: 'Enter answer'),
                          isRequired: true,
                          maxLines: 4,
                          maxLength: 4000,
                          validator: (value) {
                            if (value == null || value.isEmpty) {
                              return context.tr('pleaseEnterAnswer', fallback: 'Please enter an answer');
                            }
                            return null;
                          },
                        ),
                        SizedBox(height: UIUtils.gapMD(screenType)),
                        CustomTextField(
                          label: context.tr('status', fallback: 'Status'),
                          hint: context.tr('selectStatus', fallback: 'Select status'),
                          readOnly: true,
                          isRequired: true,
                          controller: TextEditingController(
                            text: _getLocalizedStatus(_status, null),
                          ),
                          suffixIcon: Icon(Icons.arrow_drop_down),
                          onTap: () {
                            CustomSelectionSheet.show(
                              context: context,
                              title: context.tr('selectStatus', fallback: 'Select Status'),
                              items: [
                                SelectionItem(
                                  label: context.tr('active').toUpperCase(),
                                  value: 'active',
                                ),
                                SelectionItem(
                                  label: context.tr('inactive').toUpperCase(),
                                  value: 'inactive',
                                ),
                              ],
                              selectedValue: _status,
                              onSelected: (value) {
                                setState(() {
                                  _status = value;
                                });
                              },
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                  SizedBox(height: UIUtils.gapXL(screenType)),
                  SizedBox(
                    width: double.infinity,
                    child: PrimaryButton(
                      text: isEdit
                          ? context.tr('updateFaq', fallback: 'Update FAQ')
                          : context.tr('addFaq', fallback: 'Add FAQ'),
                      onPressed: _handleSave,
                    ),
                  ),
                  SizedBox(height: UIUtils.gapMD(screenType)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
