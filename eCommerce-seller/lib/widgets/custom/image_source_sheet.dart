import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';

enum ImageSourceType { camera, gallery }

class ImageSourceSheet extends StatelessWidget {
  final MediaType mediaType;
  final String? customTitle;

  const ImageSourceSheet({
    super.key,
    this.mediaType = MediaType.image,
    this.customTitle,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final isVideo = mediaType == MediaType.video;

    final title = customTitle ?? (isVideo ? "Add a video" : "Add a document");
    final cameraLabel = isVideo ? "Record" : "Take photo";
    final galleryLabel = isVideo ? "Gallery" : "Choose photo";

    return Container(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: scheme.outlineVariant,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                title,
                style: AppTextStyles.titleLarge.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.4,
                  color: scheme.tertiary,
                ),
              ),
            ),
            const SizedBox(height: 4),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                "Choose how to add it.",
                style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600),
              ),
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(
                  child: _option(
                    context: context,
                    icon: isVideo ? Icons.videocam_outlined : Icons.photo_camera_outlined,
                    label: cameraLabel,
                    onTap: () => Navigator.pop(context, ImageSourceType.camera),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: _option(
                    context: context,
                    icon: isVideo ? Icons.video_library_outlined : Icons.photo_library_outlined,
                    label: galleryLabel,
                    onTap: () => Navigator.pop(context, ImageSourceType.gallery),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text(
                  "Cancel",
                  style: AppTextStyles.titleMedium.copyWith(
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _option({
    required BuildContext context,
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    final scheme = Theme.of(context).colorScheme;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 20),
        decoration: BoxDecoration(
          color: scheme.surface,
          border: Border.all(color: scheme.outlineVariant, width: 1.2),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          children: [
            Container(
              width: 52,
              height: 52,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.primaryColor.withValues(alpha: 0.14),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 26, color: AppColors.primaryColor),
            ),
            const SizedBox(height: 12),
            Text(
              label,
              style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w700, color: scheme.tertiary),
            ),
          ],
        ),
      ),
    );
  }
}
