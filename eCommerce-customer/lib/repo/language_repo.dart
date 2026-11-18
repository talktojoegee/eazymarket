import 'dart:async';
import 'dart:convert';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:hyper_commerce/config/api_base_helper.dart';
import 'package:hyper_commerce/config/api_routes.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/model/language_model/language_model.dart';
import 'package:hyper_commerce/model/language_model/language_list_model.dart';

class LanguageRepo {
  static const String _boxName = 'LanguageCacheBox';
  static const String _languagesCacheKey = 'languages_list';
  static const String _labelsCachePrefix = 'labels_';

  Future<Box> _getBox() async {
    if (Hive.isBoxOpen(_boxName)) {
      return Hive.box(_boxName);
    }
    return await Hive.openBox(_boxName);
  }

  Future<LanguageModel?> getCachedLanguages() async {
    try {
      final box = await _getBox();
      final raw = box.get(_languagesCacheKey);
      if (raw != null) {
        if (raw is Map) {
          return LanguageModel.fromJson(Map<String, dynamic>.from(raw));
        } else if (raw is String) {
          return LanguageModel.fromJson(jsonDecode(raw) as Map<String, dynamic>);
        }
      }
    } catch (_) {}
    return null;
  }

  Future<void> saveCachedLanguages(Map<String, dynamic> data) async {
    try {
      final box = await _getBox();
      await box.put(_languagesCacheKey, data);
    } catch (_) {}
  }

  Future<LanguageLabelsModel?> getCachedLanguageLabels(String locale) async {
    try {
      final box = await _getBox();
      final raw = box.get('$_labelsCachePrefix$locale');
      if (raw != null) {
        if (raw is Map) {
          return LanguageLabelsModel.fromJson(Map<String, dynamic>.from(raw));
        } else if (raw is String) {
          return LanguageLabelsModel.fromJson(jsonDecode(raw) as Map<String, dynamic>);
        }
      }
    } catch (_) {}
    return null;
  }

  Future<void> saveCachedLanguageLabels(String locale, Map<String, dynamic> data) async {
    try {
      final box = await _getBox();
      await box.put('$_labelsCachePrefix$locale', data);
    } catch (_) {}
  }

  Future<LanguageModel> fetchLanguages() async {
    try {
      final response = await AppHelpers.apiBaseHelper.getAPICall(
        ApiRoutes.fetchLanguages,
        {
          'surface': 'customer_app'
        }
      );

      if (response.statusCode == 200 && response.data is Map<String, dynamic>) {
        final map = response.data as Map<String, dynamic>;
        unawaited(saveCachedLanguages(map));
        return LanguageModel.fromJson(map);
      } else {
        return LanguageModel();
      }
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<LanguageLabelsModel> fetchLanguageLabels(String locale) async {
    try {
      final response = await AppHelpers.apiBaseHelper.getAPICall(
        ApiRoutes.languageLabels,
        {
          'surface': 'customer_app',
          'locale': locale,
        }
      );

      if (response.statusCode == 200 && response.data is Map<String, dynamic>) {
        final map = response.data as Map<String, dynamic>;
        unawaited(saveCachedLanguageLabels(locale, map));
        return LanguageLabelsModel.fromJson(map);
      } else {
        return LanguageLabelsModel();
      }
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}