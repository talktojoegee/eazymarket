import 'dart:io';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

/// Compact document-upload row used on the registration Documents and Bank
/// steps. Presentational only — the parent supplies [onTap] (which runs the
/// image/file picker) and [onRemove]. When a file is present it shows a
/// thumbnail (images) and a preview button (full-screen for images, OS viewer
/// for PDFs).
class AuthDocumentTile extends StatelessWidget {
  final String title;
  final String subtitle;
  final String? filePath;
  final VoidCallback onTap;
  final VoidCallback onRemove;

  const AuthDocumentTile({
    super.key,
    required this.title,
    required this.subtitle,
    required this.filePath,
    required this.onTap,
    required this.onRemove,
  });

  static const Color _green = Color(0xFF1D9A63);
  static const List<String> _imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic'];

  bool get _hasFile => (filePath ?? '').isNotEmpty;
  bool get _isImage => _hasFile && _imageExts.contains(filePath!.split('.').last.toLowerCase());

  Future<void> _preview(BuildContext context) async {
    final path = filePath ?? '';
    if (path.isEmpty) return;
    if (_isImage) {
      await showDialog(
        context: context,
        barrierColor: Colors.black.withValues(alpha: 0.9),
        builder: (_) => _ImagePreviewDialog(path: path),
      );
    } else {
      final ok = await launchUrl(Uri.file(path), mode: LaunchMode.externalApplication);
      if (!ok && context.mounted) {
        showCustomSnackbar(context: context, message: "No app available to open this file", isError: true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final tertiary = theme.colorScheme.tertiary;
    final fileName = _hasFile ? filePath!.split('/').last : '';
    final fill = isDark ? AppColors.mainDarkContainerBgColor : Colors.white;
    final border = _hasFile
        ? _green.withValues(alpha: 0.45)
        : (isDark ? AppColors.darkOutline : Colors.grey.shade300);

    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: fill,
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: border, width: 1.2),
        ),
        child: Row(
          children: [
            _badge(isDark),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(title, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w700, color: tertiary)),
                  const SizedBox(height: 2),
                  Text(
                    _hasFile ? fileName : subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.label.copyWith(color: Colors.grey.shade500),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            if (_hasFile) ...[
              _iconButton(Icons.visibility_outlined, AppColors.primaryColor, () => _preview(context)),
              const SizedBox(width: 2),
              _iconButton(Icons.close_rounded, Colors.grey.shade500, onRemove),
            ] else
              Text(
                "Add",
                style: AppTextStyles.label.copyWith(color: AppColors.primaryColor, fontWeight: FontWeight.w700),
              ),
          ],
        ),
      ),
    );
  }

  Widget _badge(bool isDark) {
    if (_hasFile && _isImage) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(11),
        child: Image.file(
          File(filePath!),
          width: 42,
          height: 42,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => _iconBadge(Icons.image_outlined, _green, true),
        ),
      );
    }
    if (_hasFile) {
      return _iconBadge(Icons.description_outlined, _green, true);
    }
    return _iconBadge(Icons.upload_file_outlined, Colors.grey.shade500, false);
  }

  Widget _iconBadge(IconData icon, Color color, bool tinted) {
    return Container(
      width: 42,
      height: 42,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: tinted ? _green.withValues(alpha: 0.12) : const Color(0xFFF1F4F8),
        borderRadius: BorderRadius.circular(11),
      ),
      child: Icon(icon, color: color, size: 20),
    );
  }

  Widget _iconButton(IconData icon, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Padding(
        padding: const EdgeInsets.all(6),
        child: Icon(icon, size: 19, color: color),
      ),
    );
  }
}

class _ImagePreviewDialog extends StatelessWidget {
  final String path;
  const _ImagePreviewDialog({required this.path});

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(16),
      child: Stack(
        alignment: Alignment.center,
        children: [
          InteractiveViewer(
            minScale: 0.8,
            maxScale: 4,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: Image.file(File(path), fit: BoxFit.contain),
            ),
          ),
          Positioned(
            top: 4,
            right: 4,
            child: GestureDetector(
              onTap: () => Navigator.pop(context),
              behavior: HitTestBehavior.opaque,
              child: Container(
                padding: const EdgeInsets.all(6),
                decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                child: const Icon(Icons.close_rounded, color: Colors.white, size: 22),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
