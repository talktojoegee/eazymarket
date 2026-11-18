import 'dart:developer';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:path_provider/path_provider.dart';
import 'package:video_compress/video_compress.dart';

import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/image_source_sheet.dart';

enum MediaType { image, video }

class ImagePickerUtils {
  static final ImagePicker _picker = ImagePicker();

  static Future<String?> pickMedia(
    BuildContext context, {
    MediaType mediaType = MediaType.image,
    double maxSizeMb = 2.0,
    Duration? maxVideoDuration,
  }) async {
    // Fallback limits: 10 MB for video size, 60 seconds for video duration
    final effectiveMaxMb = mediaType == MediaType.image
        ? (maxSizeMb > 0 ? maxSizeMb : 2.0)
        : (maxSizeMb > 0 ? maxSizeMb : 10.0);
    final effectiveMaxDurationSec =
        maxVideoDuration != null && maxVideoDuration.inSeconds > 0
            ? maxVideoDuration.inSeconds
            : 60;

    final ImageSourceType? sourceType =
        await showModalBottomSheet<ImageSourceType>(
          context: context,
          backgroundColor: Colors.transparent,
          builder: (context) => ImageSourceSheet(mediaType: mediaType),
        );

    if (sourceType == null) return null;

    final ImageSource source = sourceType == ImageSourceType.camera
        ? ImageSource.camera
        : ImageSource.gallery;

    XFile? pickedFile;

    if (mediaType == MediaType.image) {
      pickedFile = await _picker.pickImage(
        source: source,
        imageQuality: 85, // ← slightly lower is usually enough
      );
    } else {
      pickedFile = await _picker.pickVideo(
        source: source,
        maxDuration: Duration(seconds: effectiveMaxDurationSec),
      );
    }

    if (pickedFile == null) return null;

    final originalPath = pickedFile.path;
    final originalFile = File(originalPath);
    if (!await originalFile.exists()) return null;

    // 1. Validate video duration immediately upon selection
    double videoDurationSec = 0.0;
    if (mediaType == MediaType.video) {
      try {
        final info = await VideoCompress.getMediaInfo(originalPath);
        final durationMs = info.duration ?? 0.0;
        if (durationMs > 0) {
          videoDurationSec = durationMs / 1000.0;
        }
      } catch (e) {
        log('Error checking video duration: $e');
      }

      if (videoDurationSec > effectiveMaxDurationSec) {
        if (context.mounted) {
          showCustomSnackbar(
            context: context,
            message:
                'Video duration (${videoDurationSec.toStringAsFixed(0)}s) exceeds maximum allowed limit of ${effectiveMaxDurationSec}s',
            isError: true,
          );
        }
        return null;
      }
      // 2. Validate video size immediately upon selection
      final originalBytes = await originalFile.length();
      final originalMb = originalBytes / (1024.0 * 1024.0);

      if (originalMb > effectiveMaxMb) {
        if (context.mounted) {
          showCustomSnackbar(
            context: context,
            message:
                'Video size (${originalMb.toStringAsFixed(1)} MB) exceeds maximum allowed limit of ${effectiveMaxMb.toStringAsFixed(0)} MB',
            isError: true,
          );
        }
        return null;
      }
    }

    if (mediaType == MediaType.image) {
      final originalBytes = await originalFile.length();
      final originalMb = originalBytes / (1024.0 * 1024.0);
      if (originalMb <= effectiveMaxMb) {
        log('Image already ≤ ${effectiveMaxMb}MB (${originalMb.toStringAsFixed(2)} MB)');
        return originalPath;
      }
    }

    // 3. Display progress dialog and process/compress media
    if (context.mounted) {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => PopScope(
          canPop: false,
          child: Center(
            child: mediaType == MediaType.image
                ? const CustomLoadingIndicator(size: 60)
                : const _VideoProgressDialog(),
          ),
        ),
      );
    }

    String? resultPath;
    if (mediaType == MediaType.image) {
      resultPath = await _compressImage(originalPath, effectiveMaxMb);
    } else {
      resultPath = await _compressVideo(
        originalPath,
        effectiveMaxMb,
        durationSec: videoDurationSec,
      );
    }

    if (context.mounted) {
      Navigator.of(context).pop();
    }

    return resultPath;
  }

  static Future<List<String>> pickMultipleImages(
    BuildContext context, {
    double maxSizeMb = 2.0,
    int? limit,
  }) async {
    final ImageSourceType? sourceType =
        await showModalBottomSheet<ImageSourceType>(
          context: context,
          backgroundColor: Colors.transparent,
          builder: (context) =>
              const ImageSourceSheet(mediaType: MediaType.image),
        );

    if (sourceType == null) return [];

    List<XFile> pickedFiles = [];

    if (sourceType == ImageSourceType.camera) {
      final XFile? singleFile = await _picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
      );
      if (singleFile != null) {
        pickedFiles.add(singleFile);
      }
    } else {
      pickedFiles = await _picker.pickMultiImage(
        imageQuality: 85,
        limit: limit,
      );
    }

    if (pickedFiles.isEmpty) return [];

    if (limit != null && pickedFiles.length > limit) {
      pickedFiles = pickedFiles.sublist(0, limit);
    }

    if (context.mounted) {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const PopScope(
          canPop: false,
          child: Center(
            child: CustomLoadingIndicator(size: 60),
          ),
        ),
      );
    }

    List<String> compressedPaths = [];
    for (final file in pickedFiles) {
      final compPath = await _compressImage(file.path, maxSizeMb);
      if (compPath != null) {
        compressedPaths.add(compPath);
      }
    }

    if (context.mounted) {
      Navigator.of(context).pop();
    }

    return compressedPaths;
  }

  static Future<String?> _compressImage(String path, double maxSizeMb) async {
    try {
      final file = File(path);
      if (!await file.exists()) {
        log('Image file does not exist at path: $path');
        return null;
      }

      final bytes = await file.length();
      final sizeMb = bytes / (1024.0 * 1024.0);

      if (sizeMb <= maxSizeMb) {
        log('Image already ≤ ${maxSizeMb}MB → $sizeMb MB');
        return path;
      }

      final tempDir = await getTemporaryDirectory();
      final targetPath =
          '${tempDir.path}/${DateTime.now().millisecondsSinceEpoch}_compressed.jpg';

      int quality = 92;
      XFile? result;

      while (quality >= 30) {
        result = await FlutterImageCompress.compressAndGetFile(
          path,
          targetPath,
          quality: quality,
          format: CompressFormat.jpeg,
          numberOfRetries: 5,
        );

        if (result == null) break;

        final compFile = File(result.path);
        if (!await compFile.exists()) break;

        final compBytes = await compFile.length();
        final compMb = compBytes / (1024.0 * 1024.0);

        log('Image quality $quality → ${compMb.toStringAsFixed(2)} MB');

        if (compMb <= maxSizeMb) {
          try {
            if (await file.exists()) {
              await file.delete();
            }
          } catch (e) {
            log('Could not delete temp file: $e');
          }
          return result.path;
        }

        quality -= 12;
      }

      log('Image compression failed to reach ≤ ${maxSizeMb}MB → using original');
      return path;
    } catch (e, st) {
      log('Error compressing image at path $path: $e\n$st');
      if (await File(path).exists()) {
        return path;
      }
      return null;
    }
  }

  static VideoQuality _selectOptimalVideoQuality({
    required double originalMb,
    required double maxSizeMb,
    required double durationSec,
  }) {
    if (durationSec > 0) {
      // Calculate target bitrate in kbps needed to stay under maxSizeMb
      final targetBitrateKbps = (maxSizeMb * 1024.0 * 8.0) / durationSec;
      // 15% safety factor for audio and container overhead
      final safeBitrateKbps = targetBitrateKbps * 0.85;

      log(
        'Video duration: ${durationSec.toStringAsFixed(1)}s, '
        'Target bitrate: ${targetBitrateKbps.toStringAsFixed(0)} kbps '
        '(safe: ${safeBitrateKbps.toStringAsFixed(0)} kbps)',
      );

      if (safeBitrateKbps >= 1800) {
        return VideoQuality.DefaultQuality; // 720p HD (~1.5-2.0 Mbps)
      } else if (safeBitrateKbps >= 900) {
        return VideoQuality.MediumQuality; // 640p (~1.0 Mbps)
      } else {
        return VideoQuality.LowQuality; // 360p (~450 kbps)
      }
    }

    // Fallback if duration was unavailable from metadata:
    if (maxSizeMb >= 15.0) {
      return VideoQuality.DefaultQuality;
    } else if (maxSizeMb >= 8.0) {
      return originalMb > 60.0
          ? VideoQuality.MediumQuality
          : VideoQuality.DefaultQuality;
    } else {
      return VideoQuality.MediumQuality;
    }
  }

  static Future<String?> _compressVideo(
    String path,
    double maxSizeMb, {
    double durationSec = 0.0,
  }) async {
    try {
      final originalFile = File(path);
      if (!await originalFile.exists()) {
        log('Video file does not exist at path: $path');
        return null;
      }

      final originalBytes = await originalFile.length();
      final originalMb = originalBytes / (1024.0 * 1024.0);

      log('Original video size: ${originalMb.toStringAsFixed(2)} MB (target ≤ $maxSizeMb MB)');

      if (originalMb < 1.0) {
        log('Video already very small (< 1MB) → skipping compression');
        return path;
      }

      // If duration was not passed in, attempt to retrieve it now
      if (durationSec <= 0) {
        try {
          final info = await VideoCompress.getMediaInfo(path);
          final dMs = info.duration ?? 0.0;
          if (dMs > 0) durationSec = dMs / 1000.0;
        } catch (e) {
          log('Could not get media info duration: $e');
        }
      }

      final quality = _selectOptimalVideoQuality(
        originalMb: originalMb,
        maxSizeMb: maxSizeMb,
        durationSec: durationSec,
      );

      log('Compressing video in single pass with $quality...');

      // Reset progress to 0 before starting single pass
      VideoCompress.compressProgress$.next(0.0);

      final mediaInfo = await VideoCompress.compressVideo(
        path,
        quality: quality,
        deleteOrigin: false,
        frameRate: 30,
        includeAudio: true,
      );

      // Signal 100% completion and hold briefly for smooth animation
      VideoCompress.compressProgress$.next(100.0);
      await Future.delayed(const Duration(milliseconds: 350));

      if (mediaInfo == null || mediaInfo.path == null) {
        log('Video compression returned null MediaInfo');
        return path;
      }

      final compressedPath = mediaInfo.path!;
      final compressedFile = File(compressedPath);
      if (!await compressedFile.exists()) {
        log('Compressed file does not exist at $compressedPath');
        return path;
      }

      final compressedBytes = await compressedFile.length();
      final compressedMb = compressedBytes / (1024.0 * 1024.0);

      log(
        'Compressed video in single pass: ${originalMb.toStringAsFixed(2)} MB → ${compressedMb.toStringAsFixed(2)} MB with $quality',
      );

      // Clean up temporary camera/original file if in temp cache and different path
      if (compressedPath != path) {
        try {
          final tempDir = await getTemporaryDirectory();
          if (originalFile.path.startsWith(tempDir.path) &&
              await originalFile.exists()) {
            await originalFile.delete();
          }
        } catch (e) {
          log('Could not delete original temp video: $e');
        }
      }

      return compressedPath;
    } catch (e, st) {
      log('Error in _compressVideo: $e\n$st');
      if (await File(path).exists()) {
        return path;
      }
      return null;
    }
  }

  static Future<String?> downloadImageToFile(String url) async {
    try {
      final tempDir = await getTemporaryDirectory();
      final fileName = url.split('/').last;
      final filePath = '${tempDir.path}/$fileName';

      final dio = Dio();
      await dio.download(url, filePath);
      return filePath;
    } catch (e) {
      debugPrint("Error downloading image: $e");
      return null;
    }
  }

  static Future<void> clearOldTempFiles() async {
    try {
      final tempDir = await getTemporaryDirectory();
      final entities = tempDir.listSync();
      for (final entity in entities) {
        if (entity is File &&
            (entity.path.contains('_compressed.') ||
                entity.path.contains('video_compress'))) {
          await entity.delete();
        }
      }
    } catch (e) {
      log('Error cleaning temp files: $e');
    }
  }
}

class _VideoProgressDialog extends StatefulWidget {
  const _VideoProgressDialog();

  @override
  State<_VideoProgressDialog> createState() => _VideoProgressDialogState();
}

class _VideoProgressDialogState extends State<_VideoProgressDialog> {
  double _progress = 0;
  dynamic _subscription;

  @override
  void initState() {
    super.initState();
    // ObservableBuilder in VideoCompress uses .subscribe()
    _subscription = VideoCompress.compressProgress$.subscribe((progress) {
      if (mounted) {
        final val = (progress as num).toDouble();
        // Monotonic guard: progress only moves forward, never restarts or jumps backwards
        if (val > _progress) {
          setState(() {
            _progress = val.clamp(0.0, 100.0);
          });
        }
      }
    });
  }

  @override
  void dispose() {
    // Unsubscribe to avoid memory leaks
    try {
      _subscription?.unsubscribe();
    } catch (_) {}
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return CustomLoadingIndicator(
      size: 60,
      isDeterminate: true,
      showPercentage: true,
      value: _progress / 100.0,
    );
  }
}
