import '../model/settings_model/settings_model.dart';
import '../screens/splash_screen/model/custom_splash_model.dart';
import '../services/splash/splash_storage_service.dart';

/// Modified singleton for managing settings data.
class SettingsData {
  static final SettingsData _instance = SettingsData._internal();

  static SettingsData get instance => _instance;

  factory SettingsData() {
    return _instance;
  }

  SettingsData._internal();

  SystemSettings? system;
  StorageSettings? storage;
  EmailSettings? email;
  PaymentSettings? payment;
  AuthenticationSettings? authentication;
  NotificationSettings? notification;
  WebSettings? web;
  AppSettings? app;
  DeliveryBoySettings? deliveryBoy;
  AdvertisementSettings? advertisement;
  MarketsSettings? markets;
  WatchAndBuy? watchAndBuy;
  Onboarding? onboarding;
  CustomerAppSplashConfig? splashBuilder;

  bool get isOnboardingEnabled => onboarding?.customAppEnabled ?? true;

  SplashScreenModel? get activeSplashScreen =>
      splashBuilder?.splashScreen ?? SplashStorageService.splashModelNotifier.value;

  void setSettingsData(List<SettingsApiModel> data) {
    for (final item in data) {
      if (item.value is! Map<String, dynamic> && item.value is! Map) {
        if (item.variable == 'splash_screen' ||
            item.variable == 'splash_screen_builder' ||
            item.variable == 'splash_builder' ||
            item.variable == 'splashBuilder' ||
            item.variable == 'customer_app' ||
            item.variable == 'splash') {
          _processSplashData({});
        }
        continue;
      }
      final valueJson = Map<String, dynamic>.from(item.value as Map);
      switch (item.variable) {
        case 'system':
          system = SystemSettings.fromJson(valueJson);
          break;
        case 'storage':
          storage = StorageSettings.fromJson(valueJson);
          break;
        case 'email':
          email = EmailSettings.fromJson(valueJson);
          break;
        case 'payment':
          payment = PaymentSettings.fromJson(valueJson);
          break;
        case 'authentication':
          authentication = AuthenticationSettings.fromJson(valueJson);
          break;
        case 'notification':
          notification = NotificationSettings.fromJson(valueJson);
          break;
        case 'web':
          web = WebSettings.fromJson(valueJson);
          break;
        case 'app':
          app = AppSettings.fromJson(valueJson);
          if (valueJson.containsKey('customer_app')) {
            _processSplashData(valueJson['customer_app']);
          }
          break;
        case 'delivery_boy':
          deliveryBoy = DeliveryBoySettings.fromJson(valueJson);
          break;
        case 'advertisement':
          advertisement = AdvertisementSettings.fromJson(valueJson);
          break;
        case 'markets':
          markets = MarketsSettings.fromJson(valueJson);
          break;
        case 'watch_and_buy':
        case 'watchAndBuy':
        case 'watch_buy':
        case 'watchBuy':
          watchAndBuy = WatchAndBuy.fromJson(valueJson);
          break;
        case 'onboarding':
        case 'on_boarding':
        case 'onBoarding':
          onboarding = Onboarding.fromJson(valueJson);
          break;
        case 'splash_screen':
        case 'splash_screen_builder':
        case 'splash_builder':
        case 'splashBuilder':
        case 'customer_app':
        case 'splash':
          _processSplashData(valueJson);
          break;
      }
    }

    // Fallback: If not matched by switch variable name, inspect if any item's value contains 'customer_app'
    // or ('splash_screen' and 'updated_at')
    if (splashBuilder == null) {
      for (final item in data) {
        if (item.value is Map) {
          final map = Map<String, dynamic>.from(item.value as Map);
          if (map.containsKey('customer_app') ||
              (map.containsKey('splash_screen') && map.containsKey('updated_at'))) {
            _processSplashData(map);
            break;
          }
        }
      }
    }
  }

  void _processSplashData(dynamic raw) {
    if (raw == null || raw is! Map) {
      splashBuilder = null;
      SplashStorageService.onSettingsSplashReceived(null);
      return;
    }
    final map = Map<String, dynamic>.from(raw);
    if (map.isEmpty) {
      splashBuilder = null;
      SplashStorageService.onSettingsSplashReceived(null);
      return;
    }
    splashBuilder = CustomerAppSplashConfig.fromJson(map);
    if (splashBuilder?.splashScreen == null) {
      splashBuilder = null;
      SplashStorageService.onSettingsSplashReceived(null);
    } else {
      SplashStorageService.onSettingsSplashReceived(splashBuilder);
    }
  }

  void clear() {
    system = null;
    storage = null;
    email = null;
    payment = null;
    authentication = null;
    notification = null;
    web = null;
    app = null;
    deliveryBoy = null;
    advertisement = null;
    markets = null;
    watchAndBuy = null;
    onboarding = null;
    splashBuilder = null;
  }
}