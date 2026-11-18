import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/auth/model/registration_field_model.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_document_tile.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_text_field.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';
import 'package:hyper_commerce_seller/utils/validator_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

class BankAccountStep extends StatefulWidget {
  final GlobalKey<FormState> formKey;
  final TextEditingController accountHolderNameController;
  final TextEditingController accountNumberController;
  final TextEditingController bankNameController;
  final TextEditingController branchCodeController;
  final TextEditingController routingNumberController;
  final String accountType; // savings | current | checking
  final ValueChanged<String> onAccountTypeChanged;
  final String? voidedCheckPath;
  final Function(String path) onVoidedCheckPicked;

  // Dynamic fields support
  final List<RegistrationField>? dynamicFields;
  final Map<String, TextEditingController>? dynamicControllers;
  final Map<String, FocusNode>? dynamicFocusNodes;
  final Map<String, String>? dynamicSelectValues;
  final void Function(String key, String value)? onDynamicSelectChanged;
  final Map<String, String>? dynamicFilePaths;
  final void Function(String key, String path)? onDynamicFilePicked;

  const BankAccountStep({
    super.key,
    required this.formKey,
    required this.accountHolderNameController,
    required this.accountNumberController,
    required this.bankNameController,
    required this.branchCodeController,
    required this.routingNumberController,
    required this.accountType,
    required this.onAccountTypeChanged,
    required this.voidedCheckPath,
    required this.onVoidedCheckPicked,
    this.dynamicFields,
    this.dynamicControllers,
    this.dynamicFocusNodes,
    this.dynamicSelectValues,
    this.onDynamicSelectChanged,
    this.dynamicFilePaths,
    this.onDynamicFilePicked,
  });

  @override
  State<BankAccountStep> createState() => _BankAccountStepState();
}

class _BankAccountStepState extends State<BankAccountStep> {
  static const List<(String, String)> _types = [
    ('savings', 'Savings'),
    ('current', 'Current'),
    ('checking', 'Checking'),
  ];

  Future<void> _pickVoidedCheck() async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    );
    if (result == null || result.files.isEmpty) return;
    final picked = result.files.single;
    if (picked.path == null) return;
    if (picked.size / (1024 * 1024) > 2) {
      if (mounted) showCustomSnackbar(context: context, message: "File must be 2 MB or smaller", isError: true);
      return;
    }
    widget.onVoidedCheckPicked(picked.path!);
  }

  Future<void> _pickDynamicFile(String key, {required bool isImage}) async {
    if (isImage) {
      final path = await ImagePickerUtils.pickMedia(context, mediaType: MediaType.image);
      if (path != null) {
        widget.onDynamicFilePicked?.call(key, path);
      }
    } else {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
      );
      if (result == null || result.files.isEmpty) return;
      final picked = result.files.single;
      if (picked.path == null) return;
      if (picked.size / (1024 * 1024) > 2) {
        if (mounted) showCustomSnackbar(context: context, message: "File must be 2 MB or smaller", isError: true);
        return;
      }
      widget.onDynamicFilePicked?.call(key, picked.path!);
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasDynamic = widget.dynamicFields != null && widget.dynamicFields!.isNotEmpty;

    return Form(
      key: widget.formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (hasDynamic)
            ..._buildDynamicFields()
          else
            ..._buildStaticFields(),
        ],
      ),
    );
  }

  List<Widget> _buildDynamicFields() {
    final scheme = Theme.of(context).colorScheme;
    final widgets = <Widget>[];

    for (int i = 0; i < widget.dynamicFields!.length; i++) {
      final field = widget.dynamicFields![i];
      if (i > 0) widgets.add(const SizedBox(height: 20));

      final type = field.type.toLowerCase();
      if (type == 'select') {
        final selectedVal = widget.dynamicSelectValues?[field.key] ??
            (field.defaultValue?.toString()) ??
            (field.options.isNotEmpty ? field.options.first : '');

        widgets.add(
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                field.label + (field.required ? " *" : ""),
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.grey.shade600),
              ),
              const SizedBox(height: 8),
              if (field.options.length <= 4)
                Row(
                  children: [
                    for (int optIdx = 0; optIdx < field.options.length; optIdx++) ...[
                      if (optIdx > 0) const SizedBox(width: 8),
                      Expanded(
                        child: _dynamicOptionChip(
                          key: field.key,
                          value: field.options[optIdx],
                          label: field.options[optIdx].toUpperCase(),
                          selectedValue: selectedVal,
                          scheme: scheme,
                        ),
                      ),
                    ],
                  ],
                )
              else
                DropdownButtonFormField<String>(
                  value: selectedVal.isNotEmpty ? selectedVal : null,
                  decoration: InputDecoration(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  items: field.options.map((opt) {
                    return DropdownMenuItem<String>(
                      value: opt,
                      child: Text(opt),
                    );
                  }).toList(),
                  onChanged: (val) {
                    if (val != null) {
                      widget.onDynamicSelectChanged?.call(field.key, val);
                    }
                  },
                  validator: field.required
                      ? (v) => (v == null || v.isEmpty) ? "Please select ${field.label}" : null
                      : null,
                ),
            ],
          ),
        );
      } else if (type == 'image' || type == 'file') {
        final path = widget.dynamicFilePaths?[field.key];
        widgets.add(
          FormField<String>(
            autovalidateMode: AutovalidateMode.onUserInteraction,
            validator: field.required ? (_) => ValidatorUtils.validateFile(context, path) : null,
            builder: (state) => Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AuthDocumentTile(
                  title: field.label,
                  subtitle: "Upload document · max 2 MB${field.required ? ' (Required)' : ''}",
                  filePath: path,
                  onTap: () async {
                    await _pickDynamicFile(field.key, isImage: type == 'image');
                    state.didChange(widget.dynamicFilePaths?[field.key]);
                  },
                  onRemove: () {
                    widget.onDynamicFilePicked?.call(field.key, '');
                    state.didChange(null);
                  },
                ),
                if (state.hasError)
                  Padding(
                    padding: const EdgeInsets.only(top: 6, left: 4),
                    child: Text(
                      state.errorText!,
                      style: const TextStyle(color: AppColors.darkErrorColor, fontSize: 12),
                    ),
                  ),
              ],
            ),
          ),
        );
      } else {
        // Text / number field
        final controller = widget.dynamicControllers?[field.key];
        final focusNode = widget.dynamicFocusNodes?[field.key];
        final isNumber = field.key.contains('number') || field.key.contains('code') || type == 'number';

        widgets.add(
          AuthTextField(
            label: field.label,
            isRequired: field.required,
            hint: field.placeholder ?? "Enter ${field.label.toLowerCase()}",
            controller: controller ?? TextEditingController(),
            focusNode: focusNode,
            keyboardType: isNumber ? TextInputType.number : TextInputType.text,
            textCapitalization: isNumber ? TextCapitalization.none : TextCapitalization.words,
            textInputAction: TextInputAction.next,
            validator: field.required ? (v) => ValidatorUtils.validateEmpty(context, v) : null,
          ),
        );
      }
    }

    return widgets;
  }

  Widget _dynamicOptionChip({
    required String key,
    required String value,
    required String label,
    required String selectedValue,
    required ColorScheme scheme,
  }) {
    final selected = selectedValue.toLowerCase() == value.toLowerCase();
    return GestureDetector(
      onTap: () => widget.onDynamicSelectChanged?.call(key, value),
      child: Container(
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? AppColors.primaryColor : scheme.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: selected ? AppColors.primaryColor : scheme.outlineVariant,
            width: 1.2,
          ),
        ),
        child: Text(
          label,
          style: AppTextStyles.bodySmall.copyWith(
            fontWeight: FontWeight.w700,
            color: selected ? Colors.black : scheme.tertiary,
          ),
        ),
      ),
    );
  }

  List<Widget> _buildStaticFields() {
    final scheme = Theme.of(context).colorScheme;
    return [
      Text(
        "Account type",
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.grey.shade600),
      ),
      const SizedBox(height: 8),
      Row(
        children: [
          for (int i = 0; i < _types.length; i++) ...[
            if (i > 0) const SizedBox(width: 8),
            Expanded(child: _typeChip(_types[i].$1, _types[i].$2, scheme)),
          ],
        ],
      ),
      const SizedBox(height: 20),
      AuthTextField(
        label: "Account holder name",
        isRequired: true,
        hint: "As printed on the account",
        controller: widget.accountHolderNameController,
        textCapitalization: TextCapitalization.words,
        textInputAction: TextInputAction.next,
        validator: (v) => ValidatorUtils.validateEmpty(context, v),
      ),
      const SizedBox(height: 20),
      AuthTextField(
        label: "Account number",
        isRequired: true,
        hint: "Enter account number",
        controller: widget.accountNumberController,
        keyboardType: TextInputType.number,
        textInputAction: TextInputAction.next,
        validator: (v) => ValidatorUtils.validateEmpty(context, v),
      ),
      const SizedBox(height: 20),
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: AuthTextField(
              label: "Bank name",
              isRequired: true,
              hint: "e.g. HDFC Bank",
              controller: widget.bankNameController,
              textCapitalization: TextCapitalization.words,
              validator: (v) => ValidatorUtils.validateEmpty(context, v),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: AuthTextField(
              label: "Branch code",
              isRequired: true,
              hint: "e.g. 000426",
              controller: widget.branchCodeController,
              validator: (v) => ValidatorUtils.validateEmpty(context, v),
            ),
          ),
        ],
      ),
      const SizedBox(height: 20),
      AuthTextField(
        label: "Routing number",
        isRequired: true,
        hint: "e.g. HDFC0000426",
        controller: widget.routingNumberController,
        validator: (v) => ValidatorUtils.validateEmpty(context, v),
      ),
      const SizedBox(height: 20),
      Text(
        "Voided cheque",
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.grey.shade600),
      ),
      const SizedBox(height: 8),
      FormField<String>(
        autovalidateMode: AutovalidateMode.onUserInteraction,
        validator: (_) => ValidatorUtils.validateFile(context, widget.voidedCheckPath),
        builder: (state) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AuthDocumentTile(
              title: "Voided cheque",
              subtitle: "PDF or image · proves the account is yours",
              filePath: widget.voidedCheckPath,
              onTap: () async {
                await _pickVoidedCheck();
                state.didChange(widget.voidedCheckPath);
              },
              onRemove: () {
                widget.onVoidedCheckPicked('');
                state.didChange(null);
              },
            ),
            if (state.hasError)
              Padding(
                padding: const EdgeInsets.only(top: 6, left: 4),
                child: Text(
                  state.errorText!,
                  style: const TextStyle(color: AppColors.darkErrorColor, fontSize: 12),
                ),
              ),
          ],
        ),
      ),
    ];
  }

  Widget _typeChip(String value, String label, ColorScheme scheme) {
    final selected = widget.accountType == value;
    return GestureDetector(
      onTap: () => widget.onAccountTypeChanged(value),
      child: Container(
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? AppColors.primaryColor : Theme.of(context).colorScheme.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: selected ? AppColors.primaryColor : scheme.outlineVariant,
            width: 1.2,
          ),
        ),
        child: Text(
          label,
          style: AppTextStyles.bodySmall.copyWith(
            fontWeight: FontWeight.w700,
            color: selected ? Colors.black : scheme.tertiary,
          ),
        ),
      ),
    );
  }
}
