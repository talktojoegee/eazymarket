import 'dart:convert';
import 'dart:developer';
import 'package:flutter/foundation.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../../screens/splash_screen/model/custom_splash_model.dart';

/// Service managing local Hive storage and smart cache invalidation for the Splash Screen builder model.
class SplashStorageService {
  static const String boxName = 'splashBox';
  static const String _modelKey = 'splash_screen_model';
  static const String _updatedAtKey = 'splash_updated_at';

  /// ValueNotifier holding the active splash screen model so the UI can reactively update.
  static final ValueNotifier<SplashScreenModel?> splashModelNotifier =
      ValueNotifier<SplashScreenModel?>(null);

  /// Initializes the Hive box and loads any previously saved splash screen model.
  static Future<void> init() async {
    try {
      if (!Hive.isBoxOpen(boxName)) {
        await Hive.openBox(boxName);
      }
      final savedModel = getSavedSplashModel();
      splashModelNotifier.value = savedModel;
      if (kDebugMode) {
        log('✅ SplashStorageService initialized: savedModelId=${savedModel?.id}, updatedAt=${getSavedUpdatedAt()}');
      }
    } catch (e) {
      debugPrint('⚠️ SplashStorageService init error: $e');
    }
  }

  /// Retrieves the saved [SplashScreenModel] from Hive storage.
  static SplashScreenModel? getSavedSplashModel() {
    try {
      if (!Hive.isBoxOpen(boxName)) return null;
      final box = Hive.box(boxName);
      final raw = box.get(_modelKey);
      if (raw == null) return null;

      if (raw is Map) {
        return SplashScreenModel.fromJson(Map<String, dynamic>.from(raw));
      } else if (raw is String && raw.isNotEmpty) {
        final decoded = jsonDecode(raw);
        if (decoded is Map<String, dynamic>) {
          return SplashScreenModel.fromJson(decoded);
        } else if (decoded is Map) {
          return SplashScreenModel.fromJson(Map<String, dynamic>.from(decoded));
        }
      }
    } catch (e) {
      debugPrint('⚠️ Error retrieving saved splash model: $e');
    }
    return null;
  }

  /// Retrieves the saved `updated_at` date timestamp string from Hive.
  static String? getSavedUpdatedAt() {
    try {
      if (!Hive.isBoxOpen(boxName)) return null;
      final box = Hive.box(boxName);
      final val = box.get(_updatedAtKey);
      return val?.toString();
    } catch (e) {
      debugPrint('⚠️ Error retrieving splash updated_at: $e');
      return null;
    }
  }

  /// Compares the newly received [CustomerAppSplashConfig] with the last saved `updated_at` date.
  ///
  /// - If the last saved splash screen model has the same date as `updated_at`, it does NOT
  ///   update the stored splash screen model.
  /// - Otherwise, it saves the latest model of `splash_screen` and the new `updated_at` date.
  /// - Returns `true` if storage was updated, `false` if skipped or invalid.
  static Future<bool> onSettingsSplashReceived(CustomerAppSplashConfig? config) async {
    // If config is null, or splash_screen is null, or both splash_screen and updated_at are null:
    // There is no custom splash screen -> clear local storage and revert to default splash screen.
    if (config == null || config.splashScreen == null) {
      final bool hadSaved = getSavedSplashModel() != null || getSavedUpdatedAt() != null;
      if (hadSaved) {
        if (kDebugMode) {
          log('ℹ️ No custom splash screen received (splash_screen is null). Clearing saved model and reverting to default splash screen.');
        }
        await clear();
        return true;
      }
      splashModelNotifier.value = null;
      return false;
    }

    final String? incomingUpdatedAt = config.updatedAt?.trim();
    final String? savedUpdatedAt = getSavedUpdatedAt()?.trim();

    // Check if last saved splash screen model has the same date as incoming updated_at
    final bool hasValidDates = (savedUpdatedAt != null && savedUpdatedAt.isNotEmpty) &&
        (incomingUpdatedAt != null && incomingUpdatedAt.isNotEmpty);

    if (hasValidDates && savedUpdatedAt == incomingUpdatedAt) {
      if (kDebugMode) {
        log('ℹ️ Splash screen date matches ($savedUpdatedAt). Skipping storage update.');
      }
      // Ensure the in-memory notifier has the model if it wasn't already loaded
      if (splashModelNotifier.value == null) {
        splashModelNotifier.value = getSavedSplashModel() ?? config.splashScreen;
      }
      return false;
    }

    // Otherwise, change and save latest model of splash_screen
    try {
      if (!Hive.isBoxOpen(boxName)) {
        await Hive.openBox(boxName);
      }
      final box = Hive.box(boxName);
      final jsonString = jsonEncode(config.splashScreen!.toJson());
      await box.put(_modelKey, jsonString);

      if (incomingUpdatedAt != null && incomingUpdatedAt.isNotEmpty) {
        await box.put(_updatedAtKey, incomingUpdatedAt);
      }

      splashModelNotifier.value = config.splashScreen;

      if (kDebugMode) {
        log('✅ Saved latest splash_screen model (id: ${config.splashScreen?.id}) with updated_at: $incomingUpdatedAt');
      }
      return true;
    } catch (e) {
      debugPrint('❌ Failed to update splash screen model in local storage: $e');
      return false;
    }
  }

  /// Clears stored splash screen model and updated_at date.
  static Future<void> clear() async {
    try {
      if (Hive.isBoxOpen(boxName)) {
        final box = Hive.box(boxName);
        await box.delete(_modelKey);
        await box.delete(_updatedAtKey);
      }
      splashModelNotifier.value = null;
    } catch (e) {
      debugPrint('⚠️ Error clearing splash storage: $e');
    }
  }
}
