// ignore_for_file: use_build_context_synchronously

import 'dart:io';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/critical_field_shield.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/custom_section_widget.dart';
import 'package:hyper_commerce_seller/service/external_link.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_dropdown.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_upload_area.dart';

/// Step 5 — Product images.
///
/// Redesign is visual only — state/bloc unchanged.
/// - Main image = dashed CustomUploadArea (already themed), wrapped in a
///   "Main image" section card.
/// - Additional images = horizontal `Wrap` of 84×84 thumbs with an X on
///   each and a dashed "+ Add" tile at the end (was a vertical stack of
///   full-width upload areas, one per image).
/// - Image fit / video type / video link grouped in a "Display & video"
///   section card.
/// - Custom product sections in a section card with a ghost-amber
///   "+ Add section" chip in the header.
class ProductImagesStep extends StatefulWidget {
  const ProductImagesStep({super.key});

  @override
  State<ProductImagesStep> createState() => _ProductImagesStepState();
}

class _ProductImagesStepState extends State<ProductImagesStep> {
  String? _mainImage;
  final List<String> _additionalImages = [];
  String? _imageFit = "cover";
  String? _videoType;
  static const int maxImages = 15;

  final TextEditingController _videoLinkController = TextEditingController();

  @override
  void initState() {
    super.initState();
    final productData = context.read<AddProductBloc>().state.productData;
    _mainImage = productData.mainImage;
    _additionalImages.addAll(productData.otherImages);
    _imageFit = productData.imageFit.toLowerCase();
    _videoType = productData.videoType;
    _videoLinkController.text = productData.videoLink ?? "";
  }

  @override
  void dispose() {
    _videoLinkController.dispose();
    super.dispose();
  }

  void _updateBloc() {
    final bloc = context.read<AddProductBloc>();
    final currentData = bloc.state.productData;
    bloc.add(
      UpdateProductData(
        currentData.copyWith(
          mainImage: _mainImage,
          otherImages: _additionalImages,
          imageFit: _imageFit,
          videoType: _videoType,
        ),
      ),
    );
  }

  Future<void> _pickMainImage() async {
    final imagePath = await ImagePickerUtils.pickMedia(
      context,
      mediaType: MediaType.image,
    );
    if (imagePath != null) {
      setState(() {
        _mainImage = imagePath;
      });
      _updateBloc();
    }
  }

  Future<void> _pickAdditionalImage() async {
    final remainingSlots = maxImages - _additionalImages.length;
    if (remainingSlots <= 0) {
      showCustomSnackbar(
        context: context,
        message: "Maximum limit of $maxImages additional images reached.",
        isWarning: true,
      );
      return;
    }

    final pickedPaths = await ImagePickerUtils.pickMultipleImages(
      context,
      limit: remainingSlots,
    );

    if (pickedPaths.isNotEmpty) {
      bool overflow = false;
      List<String> pathsToAdd = pickedPaths;
      if (pathsToAdd.length > remainingSlots) {
        pathsToAdd = pathsToAdd.sublist(0, remainingSlots);
        overflow = true;
      }

      setState(() {
        _additionalImages.addAll(pathsToAdd);
      });
      _updateBloc();

      if (overflow) {
        showCustomSnackbar(
          context: context,
          message:
              "Maximum limit of $maxImages additional images allowed. Only $remainingSlots image(s) added.",
          isWarning: true,
        );
      }
    }
  }

  void _removeMainImage() {
    setState(() {
      _mainImage = null;
    });
    final bloc = context.read<AddProductBloc>();
    bloc.add(
      UpdateProductData(
        bloc.state.productData.copyWith(clearMainImage: true),
      ),
    );
  }

  void _removeAdditionalImage(int index) {
    setState(() {
      _additionalImages.removeAt(index);
    });
    _updateBloc();
  }

  Future<void> _pickVideo() async {
    final videoPath = await ImagePickerUtils.pickMedia(
      context,
      mediaType: MediaType.video,
    );
    if (videoPath != null) {
      final bloc = context.read<AddProductBloc>();
      bloc.add(
        UpdateProductData(
          bloc.state.productData.copyWith(productVideo: videoPath),
        ),
      );
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final productData = context.watch<AddProductBloc>().state.productData;
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // ── Main image ─────────────────────────────────────────────
        _SectionCard(
          title: context.tr('mainImage'),
          criticalFieldKey: 'media',
          isRequired: true,
          children: [
            CustomUploadArea(
              hint: context.tr('uploadImage'),
              fileName: _mainImage,
              onTap: _pickMainImage,
              onRemove: _removeMainImage,
            ),
          ],
        ),
        const SizedBox(height: 14),

        // ── Additional images ──────────────────────────────────────
        _SectionCard(
          title: context.tr('additionalImages'),
          criticalFieldKey: 'media',
          trailing: _CountBadge(
            count: _additionalImages.length,
            maxCount: maxImages,
          ),
          children: [
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                ..._additionalImages.asMap().entries.map(
                      (entry) => _ImageThumb(
                        path: entry.value,
                        onRemove: () => _removeAdditionalImage(entry.key),
                      ),
                    ),
                _AddThumb(onTap: _pickAdditionalImage),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              "You can select up to $maxImages additional images by clicking the add button.",
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w500,
                color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
                height: 1.4,
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),

        // ── Display & video ────────────────────────────────────────
        _SectionCard(
          title: "Display & video",
          children: [
            CustomDropdown<String>(
              label: context.tr('imageFit'),
              hint: context.tr('selectImageFit'),
              value: _imageFit,
              items: [
                CustomDropdownItem(label: context.tr('cover'), value: "cover"),
                CustomDropdownItem(label: context.tr('contain'), value: "contain"),
              ],
              onChanged: (val) {
                setState(() => _imageFit = val);
                _updateBloc();
              },
            ),
            const SizedBox(height: 18),
            CustomDropdown<String>(
              label: context.tr('videoType'),
              hint: context.tr('selectVideoType'),
              value: _videoType,
              items: [
                CustomDropdownItem(label: context.tr('youtube'), value: "youtube"),
                CustomDropdownItem(
                  label: context.tr('selfHosted'),
                  value: "self_hosted",
                ),
              ],
              onChanged: (val) {
                setState(() => _videoType = val);
                _updateBloc();
              },
            ),
            if (_videoType == "youtube") ...[
              const SizedBox(height: 18),
              CustomTextField(
                label: context.tr('videoLink'),
                hint: context.tr('enterYoutubeUrl'),
                controller: _videoLinkController,
                onChanged: (v) {
                  final bloc = context.read<AddProductBloc>();
                  bloc.add(
                    UpdateProductData(
                      bloc.state.productData.copyWith(videoLink: v),
                    ),
                  );
                },
              ),
            ] else if (_videoType == "self_hosted") ...[
              const SizedBox(height: 18),
              _buildSelfHostedVideo(productData, theme, null),
            ],
          ],
        ),
        const SizedBox(height: 14),

        // ── Recommended Kit Section Card ───────────────────────────
        if (productData.type.toLowerCase() == 'kit') ...[
          const _KitRecommendedSectionCard(),
          const SizedBox(height: 14),
        ],

        // ── Custom product sections ────────────────────────────────
        _SectionCard(
          title: "Custom product sections",
          trailing: _HeaderAddChip(
            label: "Add section",
            onTap: () {
              final currentSections = List<CustomProductSection>.from(
                productData.customProductSections,
              );
              currentSections.add(CustomProductSection(title: ""));
              final bloc = context.read<AddProductBloc>();
              bloc.add(
                UpdateProductData(
                  productData.copyWith(
                    customProductSections: currentSections,
                  ),
                ),
              );
            },
          ),
          children: [
            if (productData.customProductSections.isEmpty)
              const _EmptyHint(
                text:
                    "Add custom sections with fields. Upload an image for each field if needed.",
              )
            else
              ...productData.customProductSections.asMap().entries.map(
                (entry) {
                  final index = entry.key;
                  final section = entry.value;
                  return Padding(
                    padding: EdgeInsets.only(
                      bottom: index ==
                              productData.customProductSections.length - 1
                          ? 0
                          : 12,
                    ),
                    child: CustomProductSectionWidget(
                      index: index,
                      section: section,
                      onRemove: () {
                        final currentSections =
                            List<CustomProductSection>.from(
                          productData.customProductSections,
                        );
                        currentSections.removeAt(index);
                        final bloc = context.read<AddProductBloc>();
                        bloc.add(
                          UpdateProductData(
                            productData.copyWith(
                              customProductSections: currentSections,
                            ),
                          ),
                        );
                      },
                      onChanged: (updatedSection) {
                        final currentSections =
                            List<CustomProductSection>.from(
                          productData.customProductSections,
                        );
                        currentSections[index] = updatedSection;
                        final bloc = context.read<AddProductBloc>();
                        bloc.add(
                          UpdateProductData(
                            productData.copyWith(
                              customProductSections: currentSections,
                            ),
                          ),
                        );
                      },
                    ),
                  );
                },
              ),
          ],
        ),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildSelfHostedVideo(
    ProductData productData,
    ThemeData theme,
    dynamic l10n,
  ) {
    // ── Case 1: Existing remote video (edit mode) ──
    if (productData.videoLink != null &&
        productData.videoLink!.startsWith('https') &&
        (productData.productVideo == null ||
            !File(productData.productVideo!).existsSync())) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            "Current video",
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: theme.colorScheme.tertiary.withValues(alpha: 0.6),
              letterSpacing: 0.04,
            ),
          ),
          const SizedBox(height: 6),
          Material(
            color: AppColors.primaryColor.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(10),
            child: InkWell(
              borderRadius: BorderRadius.circular(10),
              onTap: () {
                ExternalLink.showVideo(
                  productData.videoLink!,
                  context: context,
                );
              },
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: AppColors.primaryColor.withValues(alpha: 0.4),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.play_circle_fill_rounded,
                      color: AppColors.primaryColor,
                      size: 20,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        productData.videoLink!.split('/').last,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: theme.colorScheme.tertiary,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const Icon(
                      Icons.open_in_new_rounded,
                      size: 16,
                      color: AppColors.primaryColor,
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            "Remote file — will be kept unless replaced.",
            style: TextStyle(
              fontSize: 11.5,
              fontStyle: FontStyle.italic,
              color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
            ),
          ),
          const SizedBox(height: 12),
          CustomUploadArea(
            hint: "Replace video (optional)",
            fileName: null,
            onTap: _pickVideo,
            onRemove: () {
              final bloc = context.read<AddProductBloc>();
              bloc.add(
                UpdateProductData(
                  bloc.state.productData.copyWith(
                    clearProductVideo: true,
                    clearVideoLink: true,
                  ),
                ),
              );
              setState(() {});
            },
          ),
        ],
      );
    }

    // ── Case 2: New picked local video or no video yet ──
    return CustomUploadArea(
      hint: context.tr('uploadVideo'),
      fileName: productData.productVideo,
      onTap: _pickVideo,
      onRemove: () {
        final bloc = context.read<AddProductBloc>();
        bloc.add(
          UpdateProductData(
            bloc.state.productData.copyWith(clearProductVideo: true),
          ),
        );
        setState(() {});
      },
    );
  }
}

// ─── Local wizard widgets (duplicated per-batch until shared) ─────────

class _SectionCard extends StatelessWidget {
  final String title;
  final bool isRequired;
  final String? criticalFieldKey;
  final List<Widget> children;
  final Widget? trailing;

  const _SectionCard({
    required this.title,
    required this.children,
    this.isRequired = false,
    this.criticalFieldKey,
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
                  child: RichText(
                    text: TextSpan(
                      text: title,
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: theme.colorScheme.tertiary,
                      ),
                      children: [
                        if (criticalFieldKey != null &&
                            HiveStorage.isCriticalField(criticalFieldKey!))
                          WidgetSpan(
                            alignment: PlaceholderAlignment.middle,
                            child: Padding(
                              padding: const EdgeInsets.only(left: 4.0),
                              child: CriticalFieldShield(
                                fieldKey: criticalFieldKey!,
                                size: 16,
                              ),
                            ),
                          ),
                        if (isRequired)
                          const TextSpan(
                            text: ' *',
                            style: TextStyle(color: Colors.red),
                          ),
                      ],
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
  final int maxCount;
  const _CountBadge({required this.count, this.maxCount = 15});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isMax = count >= maxCount;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: isMax
            ? AppColors.errorColor.withValues(alpha: 0.12)
            : theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        "$count/$maxCount",
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: isMax
              ? AppColors.errorColor
              : theme.colorScheme.tertiary.withValues(alpha: 0.7),
        ),
      ),
    );
  }
}

class _HeaderAddChip extends StatelessWidget {
  final String label;
  final VoidCallback onTap;

  const _HeaderAddChip({required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AppColors.primaryColor, width: 1.2),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.add_rounded,
                size: 16,
                color: AppColors.primaryColor,
              ),
              const SizedBox(width: 4),
              Text(
                label,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryColor,
                ),
              ),
            ],
          ),
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

/// 84×84 thumbnail preview with an X-remove overlay.
/// Falls back to a filename chip if the file no longer exists on disk
/// (typical when editing a product whose images live on the server).
class _ImageThumb extends StatelessWidget {
  final String path;
  final VoidCallback onRemove;

  const _ImageThumb({required this.path, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final borderColor =
        theme.colorScheme.outlineVariant.withValues(alpha: 0.6);

    Widget content;
    final file = File(path);
    if (file.existsSync()) {
      content = ClipRRect(
        borderRadius: BorderRadius.circular(11),
        child: Image.file(file, width: 84, height: 84, fit: BoxFit.cover),
      );
    } else {
      // Remote path or missing file — show a placeholder tile.
      content = Container(
        width: 84,
        height: 84,
        alignment: Alignment.center,
        padding: const EdgeInsets.all(6),
        decoration: BoxDecoration(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.18),
          borderRadius: BorderRadius.circular(11),
        ),
        child: Icon(
          Icons.image_rounded,
          color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
          size: 26,
        ),
      );
    }

    return SizedBox(
      width: 84,
      height: 84,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: borderColor),
            ),
            child: content,
          ),
          Positioned(
            top: -6,
            right: -6,
            child: GestureDetector(
              onTap: onRemove,
              behavior: HitTestBehavior.opaque,
              child: Container(
                width: 22,
                height: 22,
                decoration: BoxDecoration(
                  color: theme.cardColor,
                  shape: BoxShape.circle,
                  border: Border.all(color: borderColor),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.12),
                      blurRadius: 4,
                      offset: const Offset(0, 1),
                    ),
                  ],
                ),
                alignment: Alignment.center,
                child: const Icon(
                  Icons.close_rounded,
                  size: 14,
                  color: AppColors.errorColor,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// 84×84 dashed-border "+ Add" tile that sits at the end of the strip.
class _AddThumb extends StatelessWidget {
  final VoidCallback onTap;

  const _AddThumb({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 84,
      height: 84,
      child: CustomUploadArea(
        hint: "Add",
        icon: Icons.add_rounded,
        height: 84,
        onTap: onTap,
      ),
    );
  }
}

/// Recommended section card for kit products guiding sellers to add
/// a "What's Included in This Kit?" section.
class _KitRecommendedSectionCard extends StatelessWidget {
  const _KitRecommendedSectionCard();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark
            ? AppColors.primaryColor.withValues(alpha: 0.08)
            : AppColors.primaryColor.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.primaryColor.withValues(alpha: isDark ? 0.35 : 0.3),
          width: 1.2,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: AppColors.primaryColor
                  .withValues(alpha: isDark ? 0.22 : 0.16),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(
              Icons.tips_and_updates_rounded,
              color: AppColors.primaryColor,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
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
                        color: AppColors.primaryColor.withValues(alpha: 0.18),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        context.tr('badgeRecommended', fallback: 'RECOMMENDED'),
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primaryColor,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                RichText(
                  text: TextSpan(
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w400,
                      color: theme.colorScheme.tertiary.withValues(alpha: 0.85),
                      height: 1.45,
                    ),
                    children: [
                      const TextSpan(text: 'Add a section titled '),
                      TextSpan(
                        text: '"What\'s Included in This Kit?"',
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: theme.colorScheme.tertiary,
                        ),
                      ),
                      const TextSpan(
                        text:
                            ' so users can quickly review the contents and make informed purchasing decisions.',
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

