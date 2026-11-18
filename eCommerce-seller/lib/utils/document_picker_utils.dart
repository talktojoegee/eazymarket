import 'dart:developer';
import 'dart:io';

import 'package:file_picker/file_picker.dart';

/// Picks a document for upload. Defaults mirror the panel's return-label rule:
/// pdf / png / jpg, at most 5 MB.
class DocumentPickerUtils {
  static const List<String> defaultExtensions = ['pdf', 'png', 'jpg', 'jpeg'];

  /// Returns the picked file's path, or null when cancelled. When the file is
  /// larger than [maxSizeMb] the [onTooLarge] callback fires and null is
  /// returned so the caller can surface its own message.
  static Future<String?> pick({
    List<String> allowedExtensions = defaultExtensions,
    double maxSizeMb = 5.0,
    void Function(double actualMb)? onTooLarge,
  }) async {
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: allowedExtensions,
        allowMultiple: false,
        withData: false,
      );

      final path = result?.files.single.path;
      if (path == null) return null;

      final sizeMb = await File(path).length() / (1024 * 1024);
      if (sizeMb > maxSizeMb) {
        onTooLarge?.call(sizeMb);
        return null;
      }

      return path;
    } catch (e) {
      log('Document pick failed: $e');
      return null;
    }
  }
}
