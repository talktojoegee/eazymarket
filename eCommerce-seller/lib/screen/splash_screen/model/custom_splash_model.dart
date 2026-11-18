import 'package:flutter/foundation.dart';

/// Top-level model holding the seller app splash configuration and updated timestamp.
class SellerAppSplashConfig {
  final SplashScreenModel? splashScreen;
  final String? updatedAt;

  SellerAppSplashConfig({
    this.splashScreen,
    this.updatedAt,
  });

  factory SellerAppSplashConfig.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic> source;
    if (json.containsKey('seller_app') && json['seller_app'] is Map) {
      source = Map<String, dynamic>.from(json['seller_app'] as Map);
    } else if (json.containsKey('data') && json['data'] is Map) {
      final dataMap = Map<String, dynamic>.from(json['data'] as Map);
      if (dataMap.containsKey('seller_app') && dataMap['seller_app'] is Map) {
        source = Map<String, dynamic>.from(dataMap['seller_app'] as Map);
      } else {
        source = dataMap;
      }
    } else {
      source = json;
    }

    SplashScreenModel? splash;
    if (source.containsKey('splash_screen') && source['splash_screen'] is Map) {
      final splashMap = Map<String, dynamic>.from(source['splash_screen'] as Map);
      final sourceSeconds = source['min_display_seconds'] ??
          source['minDisplaySeconds'] ??
          source['display_seconds'] ??
          source['displaySeconds'] ??
          source['display_duration'] ??
          source['duration'] ??
          source['duration_seconds'] ??
          source['seconds'] ??
          json['min_display_seconds'] ??
          json['minDisplaySeconds'] ??
          json['display_seconds'] ??
          json['displaySeconds'] ??
          json['duration'] ??
          json['seconds'];
      if ((!splashMap.containsKey('min_display_seconds') || splashMap['min_display_seconds'] == null) &&
          sourceSeconds != null) {
        splashMap['min_display_seconds'] = sourceSeconds;
      }
      splash = SplashScreenModel.fromJson(splashMap);
    } else if (source.containsKey('background_type') ||
        source.containsKey('surface') ||
        source.containsKey('min_display_seconds') ||
        source.containsKey('display_seconds') ||
        source.containsKey('duration')) {
      splash = SplashScreenModel.fromJson(source);
    }

    return SellerAppSplashConfig(
      splashScreen: splash,
      updatedAt: _parseNullableString(source['updated_at'] ?? json['updated_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (splashScreen != null) 'splash_screen': splashScreen!.toJson(),
      if (updatedAt != null) 'updated_at': updatedAt,
    };
  }

  @override
  String toString() =>
      'SellerAppSplashConfig(splashScreen: $splashScreen, updatedAt: $updatedAt)';
}

/// Model representing the splash screen builder configuration (15 attributes).
class SplashScreenModel {
  final int? id;
  final String? surface;
  final String? slot;
  final String? scheduledDate;
  final String? backgroundType; // 'color', 'image', 'video'
  final String? backgroundColor; // e.g. '#d99bc2'
  final String? backgroundImage;
  final String? backgroundVideo;
  final bool? overlayEnabled;
  final String? overlayType; // 'image', 'lottie'
  final String? overlayImage;
  final String? overlayLottie;
  final OverlayPositionModel? overlayPosition;
  final double? overlaySize;
  final int? minDisplaySeconds;

  SplashScreenModel({
    this.id,
    this.surface,
    this.slot,
    this.scheduledDate,
    this.backgroundType,
    this.backgroundColor,
    this.backgroundImage,
    this.backgroundVideo,
    this.overlayEnabled,
    this.overlayType,
    this.overlayImage,
    this.overlayLottie,
    this.overlayPosition,
    this.overlaySize,
    this.minDisplaySeconds,
  });

  factory SplashScreenModel.fromJson(Map<String, dynamic> json) {
    return SplashScreenModel(
      id: parseInt(json['id']),
      surface: _parseNullableString(json['surface']),
      slot: _parseNullableString(json['slot']),
      scheduledDate: _parseNullableString(json['scheduled_date']),
      backgroundType: _parseNullableString(json['background_type']),
      backgroundColor: _parseNullableString(json['background_color']),
      backgroundImage: _parseNullableString(json['background_image']),
      backgroundVideo: _parseNullableString(json['background_video']),
      overlayEnabled: parseBool(json['overlay_enabled']),
      overlayType: _parseNullableString(json['overlay_type']),
      overlayImage: _parseNullableString(json['overlay_image']),
      overlayLottie: _parseNullableString(json['overlay_lottie']),
      overlayPosition: json['overlay_position'] != null &&
              json['overlay_position'] is Map<String, dynamic>
          ? OverlayPositionModel.fromJson(
              json['overlay_position'] as Map<String, dynamic>)
          : (json['overlay_position'] is Map
              ? OverlayPositionModel.fromJson(
                  Map<String, dynamic>.from(json['overlay_position'] as Map))
              : null),
      overlaySize: parseDouble(json['overlay_size']),
      minDisplaySeconds: _parseSeconds(
        json['min_display_seconds'] ??
            json['minDisplaySeconds'] ??
            json['display_seconds'] ??
            json['displaySeconds'] ??
            json['display_duration'] ??
            json['displayDuration'] ??
            json['duration'] ??
            json['duration_seconds'] ??
            json['display_time'] ??
            json['displayTime'] ??
            json['min_seconds'] ??
            json['minSeconds'] ??
            json['seconds'] ??
            json['time'],
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'surface': surface,
      'slot': slot,
      'scheduled_date': scheduledDate,
      'background_type': backgroundType,
      'background_color': backgroundColor,
      'background_image': backgroundImage,
      'background_video': backgroundVideo,
      'overlay_enabled': overlayEnabled,
      'overlay_type': overlayType,
      'overlay_image': overlayImage,
      'overlay_lottie': overlayLottie,
      if (overlayPosition != null) 'overlay_position': overlayPosition!.toJson(),
      'overlay_size': overlaySize,
      'min_display_seconds': minDisplaySeconds,
    };
  }

  bool get hasContent {
    final hasBgType = backgroundType != null && backgroundType!.trim().isNotEmpty;
    final hasBgColor = backgroundColor != null && backgroundColor!.trim().isNotEmpty;
    final hasBgImage = backgroundImage != null && backgroundImage!.trim().isNotEmpty;
    final hasBgVideo = backgroundVideo != null && backgroundVideo!.trim().isNotEmpty;
    final hasOverlay = overlayEnabled == true &&
        ((overlayImage != null && overlayImage!.trim().isNotEmpty) ||
            (overlayLottie != null && overlayLottie!.trim().isNotEmpty));
    return hasBgType || hasBgColor || hasBgImage || hasBgVideo || hasOverlay;
  }

  @override
  String toString() =>
      'SplashScreenModel(id: $id, surface: $surface, bgType: $backgroundType, bgColor: $backgroundColor, minSec: $minDisplaySeconds)';
}

/// Overlay position coordinate model (percentages 0..100).
class OverlayPositionModel {
  final double horizontal; // Percentage: 0 (left) .. 50 (center) .. 100 (right)
  final double vertical; // Percentage: 0 (top) .. 50 (center) .. 100 (bottom)

  OverlayPositionModel({
    this.horizontal = 50.0,
    this.vertical = 50.0,
  });

  factory OverlayPositionModel.fromJson(Map<String, dynamic> json) {
    return OverlayPositionModel(
      horizontal: parseDouble(json['horizontal'] ?? json['x'] ?? 50.0),
      vertical: parseDouble(json['vertical'] ?? json['y'] ?? 50.0),
    );
  }

  Map<String, dynamic> toJson() => {
    'horizontal': horizontal,
    'vertical': vertical,
  };

  @override
  String toString() =>
      'OverlayPositionModel(horizontal: $horizontal, vertical: $vertical)';
}

String? _parseNullableString(dynamic val) {
  if (val == null) return null;
  final str = val.toString().trim();
  return str.isEmpty ? null : str;
}

int? parseInt(dynamic val) {
  if (val == null) return null;
  if (val is int) return val;
  if (val is double) return val.toInt();
  return int.tryParse(val.toString());
}

int? _parseSeconds(dynamic val) {
  if (val == null) return null;
  num? n;
  if (val is num) {
    n = val;
  } else {
    n = num.tryParse(val.toString().trim());
  }
  if (n == null) return null;
  if (n >= 500) {
    return (n / 1000).round();
  }
  return n.toInt();
}

double parseDouble(dynamic val) {
  if (val == null) return 0.0;
  if (val is double) return val;
  if (val is int) return val.toDouble();
  return double.tryParse(val.toString()) ?? 0.0;
}

bool? parseBool(dynamic val) {
  if (val == null) return null;
  if (val is bool) return val;
  if (val is num) return val != 0;
  final str = val.toString().trim().toLowerCase();
  if (str == 'true' || str == '1') return true;
  if (str == 'false' || str == '0') return false;
  return null;
}
