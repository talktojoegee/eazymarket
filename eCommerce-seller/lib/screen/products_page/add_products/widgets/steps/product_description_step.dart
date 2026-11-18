import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_quill/flutter_quill.dart';
import 'package:flutter_quill_delta_from_html/flutter_quill_delta_from_html.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/critical_field_shield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:vsc_quill_delta_to_html/vsc_quill_delta_to_html.dart';

/// Step 6 — Description & tags.
///
/// Redesign: two section cards.
///   Description → short description + Quill editor (toolbar restyled
///                 with amber-tinted active buttons and a subtle divider).
///   Tags        → input row with amber "+ add" button + amber-tinted
///                 tag chips (were grey Chips with red delete cross).
///
/// Bloc/state wiring, Quill init, and HTML round-trip are all preserved.
class ProductDescriptionStep extends StatefulWidget {
  const ProductDescriptionStep({super.key});

  @override
  State<ProductDescriptionStep> createState() => _ProductDescriptionStepState();
}

class _ProductDescriptionStepState extends State<ProductDescriptionStep> {
  final TextEditingController _shortDescController = TextEditingController();
  final TextEditingController _tagController = TextEditingController();
  final List<String> _tags = [];

  final FocusNode _shortDescFocus = FocusNode();
  final FocusNode _tagFocus = FocusNode();
  final FocusNode _fullDescFocus = FocusNode();

  final QuillController _quillController = QuillController.basic();

  void _updateBloc() {
    final bloc = context.read<AddProductBloc>();
    final currentData = bloc.state.productData;
    final deltaOps = _quillController.document.toDelta().toJson();

    final converter = QuillDeltaToHtmlConverter(
      deltaOps,
      ConverterOptions(),
    );

    final html = converter.convert();

    bloc.add(
      UpdateProductData(
        currentData.copyWith(
          shortDescription: _shortDescController.text,
          description: html.trim().isEmpty ? "" : html,
          tags: _tags,
        ),
      ),
    );
  }

  void _addTag() {
    final tag = _tagController.text.trim();
    if (tag.isNotEmpty && !_tags.contains(tag)) {
      setState(() {
        _tags.add(tag);
        _tagController.clear();
      });
      _updateBloc();
    }
  }

  void _removeTag(String tag) {
    setState(() {
      _tags.remove(tag);
    });
    _updateBloc();
  }

  @override
  void initState() {
    super.initState();
    final productData = context.read<AddProductBloc>().state.productData;
    _shortDescController.text = productData.shortDescription ?? "";
    if (productData.tags.isNotEmpty) {
      _tags.addAll(productData.tags);
    }

    if (productData.description != null &&
        productData.description!.isNotEmpty) {
      try {
        final delta = HtmlToDelta().convert(productData.description!);
        if (delta.isNotEmpty) {
          _quillController.document = Document.fromDelta(delta);
        }
      } catch (_) {
        try {
          _quillController.document = Document()..insert(0, productData.description!);
        } catch (_) {}
      }
    }

    _shortDescController.addListener(_updateBloc);
    _quillController.addListener(_updateBloc);
  }

  @override
  void dispose() {
    _shortDescController.dispose();
    _tagController.dispose();
    _shortDescFocus.dispose();
    _tagFocus.dispose();
    _quillController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionCard(
          title: "Description",
          children: [
            CustomTextField(
              label: context.tr('shortDescription'),
              criticalFieldKey: 'short_description',
              isRequired: true,
              hint: context.tr('enterShortDescription'),
              maxLines: 2,
              focusNode: _shortDescFocus,
              controller: _shortDescController,
            ),
            const SizedBox(height: 18),
            _EditorLabel(
              label: context.tr('productDescription'),
              criticalFieldKey: 'description',
              isRequired: true,
            ),
            const SizedBox(height: 8),
            _buildEditor(context),
          ],
        ),
        const SizedBox(height: 14),
        _SectionCard(
          title: context.tr('tags'),
          trailing: _tags.isEmpty ? null : _CountBadge(count: _tags.length),
          children: [
            _buildTagsInput(context),
            if (_tags.isNotEmpty) ...[
              const SizedBox(height: 14),
              _buildTagsList(),
            ] else ...[
              const SizedBox(height: 10),
              const _EmptyHint(
                text:
                    "Add short searchable tags (e.g. \"organic\", \"gluten-free\"). Press + to add.",
              ),
            ],
          ],
        ),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildEditor(BuildContext context) {
    final theme = Theme.of(context);
    final borderColor =
        theme.colorScheme.outlineVariant.withValues(alpha: 0.6);

    return Container(
      height: 300,
      decoration: BoxDecoration(
        color: theme.brightness == Brightness.dark
            ? const Color(0xFF0D1117)
            : Colors.white,
        border: Border.all(color: borderColor),
        borderRadius: BorderRadius.circular(12),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          QuillSimpleToolbar(
            controller: _quillController,
            config: QuillSimpleToolbarConfig(
              showFontFamily: false,
              showFontSize: false,
              showSearchButton: false,
              showInlineCode: false,
              showSubscript: false,
              showSuperscript: false,
              toolbarSectionSpacing: 4,
              multiRowsDisplay: false,
              decoration: BoxDecoration(
                color: theme.brightness == Brightness.dark
                    ? const Color(0xFF161B22)
                    : const Color(0xFFFAFBFC),
                border: Border(
                  bottom: BorderSide(color: borderColor),
                ),
              ),
              buttonOptions: const QuillSimpleToolbarButtonOptions(
                base: QuillToolbarBaseButtonOptions(
                  iconTheme: QuillIconTheme(
                    iconButtonSelectedData: IconButtonData(
                      color: AppColors.primaryColor,
                    ),
                  ),
                ),
              ),
            ),
          ),
          Expanded(
            child: QuillEditor.basic(
              controller: _quillController,
              focusNode: _fullDescFocus,
              config: QuillEditorConfig(
                showCursor: true,
                expands: true,
                padding: const EdgeInsets.all(14),
                textSelectionThemeData: const TextSelectionThemeData(
                  cursorColor: AppColors.primaryColor,
                ),
                embedBuilders: const [
                  QuillImageEmbedBuilder(),
                ],
                unknownEmbedBuilder: const QuillUnknownEmbedBuilder(),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTagsInput(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final borderColor =
        theme.colorScheme.outlineVariant.withValues(alpha: 0.6);

    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF0D1117) : Colors.white,
        border: Border.all(color: borderColor),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _tagController,
              focusNode: _tagFocus,
              cursorColor: AppColors.primaryColor,
              onSubmitted: (_) => _addTag(),
              style: TextStyle(
                fontSize: 15,
                color: theme.colorScheme.tertiary,
              ),
              decoration: InputDecoration(
                hintText: "Enter a tag",
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
          Padding(
            padding: const EdgeInsets.all(6),
            child: ValueListenableBuilder<TextEditingValue>(
              valueListenable: _tagController,
              builder: (context, value, child) {
                final bool hasText = value.text.trim().isNotEmpty;
                return Material(
                  color: hasText
                      ? AppColors.primaryColor
                      : theme.colorScheme.outlineVariant
                          .withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(8),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: hasText ? _addTag : null,
                    child: SizedBox(
                      width: 40,
                      height: 40,
                      child: Icon(
                        Icons.add_rounded,
                        size: 22,
                        color: hasText ? Colors.black : Colors.white70,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTagsList() {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: _tags
          .map((tag) => _TagChip(label: tag, onRemove: () => _removeTag(tag)))
          .toList(),
    );
  }
}

// ─── Local wizard widgets (duplicated per-batch until shared) ─────────

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
            padding: const EdgeInsets.fromLTRB(16, 14, 12, 12),
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

class _CountBadge extends StatelessWidget {
  final int count;
  const _CountBadge({required this.count});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        "$count",
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: theme.colorScheme.tertiary.withValues(alpha: 0.7),
        ),
      ),
    );
  }
}

class _EmptyHint extends StatelessWidget {
  final String text;
  const _EmptyHint({required this.text});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Text(
      text,
      style: TextStyle(
        fontSize: 12.5,
        fontWeight: FontWeight.w500,
        color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
        height: 1.4,
      ),
    );
  }
}

class _EditorLabel extends StatelessWidget {
  final String label;
  final bool isRequired;
  final String? criticalFieldKey;

  const _EditorLabel({
    required this.label,
    this.isRequired = false,
    this.criticalFieldKey,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return RichText(
      text: TextSpan(
        text: label,
        style: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: theme.colorScheme.tertiary,
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
    );
  }
}

/// Amber-tinted tag chip with an inline dismiss icon.
class _TagChip extends StatelessWidget {
  final String label;
  final VoidCallback onRemove;

  const _TagChip({required this.label, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.primaryColor.withValues(alpha: 0.15),
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 6, 6, 6),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: AppColors.primaryColor.withValues(alpha: 0.4),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: Color(0xFF8A5A00),
              ),
            ),
            const SizedBox(width: 4),
            InkWell(
              customBorder: const CircleBorder(),
              onTap: onRemove,
              child: Container(
                width: 20,
                height: 20,
                alignment: Alignment.center,
                child: const Icon(
                  Icons.close_rounded,
                  size: 14,
                  color: Color(0xFF8A5A00),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Renders embedded images (e.g. from HTML descriptions) inside the Quill editor.
class QuillImageEmbedBuilder extends EmbedBuilder {
  const QuillImageEmbedBuilder();

  @override
  String get key => 'image';

  @override
  Widget build(BuildContext context, EmbedContext embedContext) {
    final imageUrl = embedContext.node.value.data.toString();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: Image.network(
          imageUrl,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              border: Border.all(color: Colors.grey.shade300),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.broken_image, size: 20, color: Colors.grey),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    imageUrl,
                    style: const TextStyle(fontSize: 12, color: Colors.grey),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Fallback builder for any unrecognized embed type to prevent UnimplementedError crashes.
class QuillUnknownEmbedBuilder extends EmbedBuilder {
  const QuillUnknownEmbedBuilder();

  @override
  String get key => 'unknown';

  @override
  Widget build(BuildContext context, EmbedContext embedContext) {
    return const SizedBox.shrink();
  }
}

