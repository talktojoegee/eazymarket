import 'package:file_picker/file_picker.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/auth/model/registration_field_model.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_document_tile.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';
import 'package:hyper_commerce_seller/utils/validator_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

class RequiredDocumentsStep extends StatefulWidget {
  final GlobalKey<FormState> formKey;
  final Function(String field, String path) onFilePicked;
  final String? businessLicensePath;
  final String? articlesOfIncorporationPath;
  final String? nationalIdCardPath;
  final String? authorizedSignaturePath;
  final String? addressProofPath;

  // Dynamic fields support
  final List<RegistrationField>? dynamicFields;
  final Map<String, String>? dynamicDocPaths;
  final Function(String key, String path)? onDynamicFilePicked;

  const RequiredDocumentsStep({
    super.key,
    required this.formKey,
    required this.onFilePicked,
    this.businessLicensePath,
    this.articlesOfIncorporationPath,
    this.nationalIdCardPath,
    this.authorizedSignaturePath,
    this.addressProofPath,
    this.dynamicFields,
    this.dynamicDocPaths,
    this.onDynamicFilePicked,
  });

  @override
  State<RequiredDocumentsStep> createState() => _RequiredDocumentsStepState();
}

class _RequiredDocumentsStepState extends State<RequiredDocumentsStep> {
  Future<void> _pickImage(String field, {bool isDynamic = false}) async {
    final path = await ImagePickerUtils.pickMedia(context, mediaType: MediaType.image);
    if (path != null) {
      if (isDynamic) {
        widget.onDynamicFilePicked?.call(field, path);
      } else {
        widget.onFilePicked(field, path);
      }
    }
  }

  Future<void> _pickFile(String field, {bool isDynamic = false}) async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    );
    if (result == null || result.files.isEmpty) return;
    final picked = result.files.single;
    if (picked.path == null) return;
    final sizeMb = picked.size / (1024 * 1024);
    if (sizeMb > 2) {
      if (mounted) {
        showCustomSnackbar(context: context, message: "File must be 2 MB or smaller", isError: true);
      }
      return;
    }
    if (isDynamic) {
      widget.onDynamicFilePicked?.call(field, picked.path!);
    } else {
      widget.onFilePicked(field, picked.path!);
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
          Text(
            "Photograph each document or pick a file. JPG, PNG or WEBP up to 2 MB.",
            style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600, height: 1.5),
          ),
          const SizedBox(height: 16),
          if (hasDynamic)
            ..._buildDynamicFields()
          else
            ..._buildStaticFields(),
        ],
      ),
    );
  }

  List<Widget> _buildDynamicFields() {
    final widgets = <Widget>[];
    for (int i = 0; i < widget.dynamicFields!.length; i++) {
      final field = widget.dynamicFields![i];
      final path = widget.dynamicDocPaths?[field.key];
      final isImage = field.type.toLowerCase() == 'image';

      if (i > 0) widgets.add(const SizedBox(height: 12));
      widgets.add(
        _docField(
          title: field.label,
          subtitle: "${isImage ? 'Image' : 'File'} · max 2 MB${field.required ? ' (Required)' : ''}",
          path: path,
          isRequired: field.required,
          onPick: () => isImage ? _pickImage(field.key, isDynamic: true) : _pickFile(field.key, isDynamic: true),
          onRemove: () => widget.onDynamicFilePicked?.call(field.key, ''),
        ),
      );
    }
    return widgets;
  }

  List<Widget> _buildStaticFields() {
    return [
      _docField(
        title: context.tr('nationalIdCard', fallback: "National ID card"),
        subtitle: "Image · max 2 MB",
        path: widget.nationalIdCardPath,
        onPick: () => _pickImage('National ID Card'),
        onRemove: () => widget.onFilePicked('National ID Card', ''),
      ),
      const SizedBox(height: 12),
      _docField(
        title: context.tr('businessLicense', fallback: "Business licence"),
        subtitle: "Image · max 2 MB",
        path: widget.businessLicensePath,
        onPick: () => _pickImage('Business License'),
        onRemove: () => widget.onFilePicked('Business License', ''),
      ),
      const SizedBox(height: 12),
      _docField(
        title: context.tr('articlesOfIncorporation', fallback: "Articles of incorporation"),
        subtitle: "Image · max 2 MB",
        path: widget.articlesOfIncorporationPath,
        onPick: () => _pickImage('Articles of Incorporation'),
        onRemove: () => widget.onFilePicked('Articles of Incorporation', ''),
      ),
      const SizedBox(height: 12),
      _docField(
        title: context.tr('authorizedSignature', fallback: "Authorised signature"),
        subtitle: "Image · max 2 MB",
        path: widget.authorizedSignaturePath,
        onPick: () => _pickImage('Authorized Signature'),
        onRemove: () => widget.onFilePicked('Authorized Signature', ''),
      ),
      const SizedBox(height: 12),
      _docField(
        title: "Address proof",
        subtitle: "PDF or image · max 2 MB",
        path: widget.addressProofPath,
        onPick: () => _pickFile('Address Proof'),
        onRemove: () => widget.onFilePicked('Address Proof', ''),
      ),
    ];
  }

  Widget _docField({
    required String title,
    required String subtitle,
    required String? path,
    required VoidCallback onPick,
    required VoidCallback onRemove,
    bool isRequired = true,
  }) {
    return FormField<String>(
      autovalidateMode: AutovalidateMode.onUserInteraction,
      validator: isRequired ? (_) => ValidatorUtils.validateFile(context, path) : null,
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AuthDocumentTile(
            title: title,
            subtitle: subtitle,
            filePath: path,
            onTap: () {
              onPick();
              Future.microtask(() {
                state.didChange(path);
              });
            },
            onRemove: () {
              onRemove();
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
    );
  }
}
