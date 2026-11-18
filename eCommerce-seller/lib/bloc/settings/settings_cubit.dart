import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/splash_screen/model/custom_splash_model.dart';
import 'package:hyper_commerce_seller/screen/splash_screen/repo/custom_splash_repo.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';
import 'package:hyper_commerce_seller/services/splash/splash_storage_service.dart';

part 'settings_state.dart';

class SettingsCubit extends Cubit<SettingsState> {
  SettingsCubit() : super(SettingsInitial());

  bool _isFetching = false;

  Future<void> fetchAndSaveSettings() async {
    if (_isFetching) {
      debugPrint('Settings fetch already in progress, skipping...');
      return;
    }
    _isFetching = true;

    if (state is! SettingsLoaded) {
      emit(SettingsLoading());
    }

    try {
      final response = await ApiBaseHelper().get(ApiRoutes.settingsApi);
      final data = response['data'] as List<dynamic>;

      // 🧠 Convert list → map (ONLY ONE LOOP)
      final Map<String, dynamic> settingsMap = {
        for (var item in data) item['variable']: item['value'],
      };

      final systemValue = settingsMap['system'] as Map<String, dynamic>?;
      final paymentValue = settingsMap['payment'] as Map<String, dynamic>?;
      final authenticationValue =
          settingsMap['authentication'] as Map<String, dynamic>?;
      final sellerValue = settingsMap['seller'];
      final subscriptionValue =
          settingsMap['subscription'] as Map<String, dynamic>?;
      final advertisementValue = settingsMap['advertisement'] as Map<String, dynamic>?;

      if (systemValue != null) {
        final filteredSystem = {
          'sellerAppMaintenanceMode':
              systemValue['sellerAppMaintenanceMode'] ?? false,
          'sellerAppMaintenanceMessage':
              systemValue['sellerAppMaintenanceMessage'] ?? '',
          'demoMode': systemValue['demoMode'] ?? false,
          'sellerDemoModeMessage': systemValue['sellerDemoModeMessage'] ?? '',
          'currency': systemValue['currency'] ?? 'USD',
          'currencySymbol': systemValue['currencySymbol'] ?? '\$',
          'systemVendorType': systemValue['systemVendorType'] ?? 'multi',
          // Dial code from settings (e.g. "91"). Stored as-is; the use site
          // converts to an ISO-2 code for the phone field.
          'countryCode': systemValue['countryCode']?.toString() ?? '91',
          'advertisementEnable': advertisementValue?['featureEnabled'] ?? false,
          'advertisementWalletMinTopUp': advertisementValue?['walletMinTopup'] ?? 10,
          'productApprovalCriticalFields':
              (systemValue['productApprovalCriticalFields'] as List?)
                  ?.map((e) => e.toString())
                  .toList() ??
              <String>[],
        };

        final isCustomSms = authenticationValue?['customSms'] as bool? ?? false;

        // Identifier mode decides which recovery/OTP channels the UI may offer;
        // the gateway decides whether OTPs come from the server or Firebase.
        await HiveStorage.setIdentifierMode(
          authenticationValue?['identifierMode'] as String?,
        );
        await HiveStorage.setSmsGateway(
          authenticationValue?['smsGateway'] as String?,
        );

        final isSubscriptionAvailable =
            subscriptionValue?['enableSubscription'] as bool? ?? false;

        await HiveStorage.setSystemSettings(
          filteredSystem,
          payment: paymentValue,
          isCustomSms: isCustomSms,
          isSubscriptionAvailable: isSubscriptionAvailable,
        );

        emit(
          SettingsLoaded(
            maintenanceMode: HiveStorage.sellerAppMaintenanceMode,
            maintenanceMessage: HiveStorage.sellerAppMaintenanceMessage,
            isDemoMode: HiveStorage.demoMode,
            demoMessage: HiveStorage.sellerDemoModeMessage,
            currency: HiveStorage.currency,
            currencySymbol: HiveStorage.currencySymbol,
            systemVendorType: HiveStorage.systemVendorType,
            paymentSettings: paymentValue,
          ),
        );
      } else {
        if (state is! SettingsLoaded) {
          emit(SettingsError('System settings not found in response'));
        }
      }

      // Seller settings
      if (sellerValue != null) {
        await HiveStorage.setSellerSettings(sellerValue);
      }

      // Shipping settings — drives the Orders page method tabs
      // (labels + wire values + enabled state per mode).
      final shippingValue = settingsMap['shipping'];
      if (shippingValue is Map<String, dynamic>) {
        await HiveStorage.setShippingSettings(shippingValue);
      }

      // Watch & Buy settings
      final watchAndBuyValue = settingsMap['watch_and_buy'];
      if (watchAndBuyValue is Map<String, dynamic>) {
        await HiveStorage.setWatchAndBuySettings(watchAndBuyValue);
      }

      // Onboarding settings
      final onboardingValue = settingsMap['onboarding'] ?? settingsMap['on_boarding'];
      if (onboardingValue is Map) {
        await HiveStorage.setOnboardingSettings(
          Map<String, dynamic>.from(onboardingValue),
        );
      }

      // Seller App Splash Screen Builder settings
      dynamic splashRaw;
      if (settingsMap['app'] is Map &&
          (settingsMap['app'] as Map).containsKey('seller_app')) {
        splashRaw = (settingsMap['app'] as Map)['seller_app'];
      } else if (settingsMap['seller'] is Map &&
          (settingsMap['seller'] as Map).containsKey('seller_app')) {
        splashRaw = (settingsMap['seller'] as Map)['seller_app'];
      } else if (settingsMap.containsKey('seller_app')) {
        splashRaw = settingsMap['seller_app'];
      } else if (settingsMap.containsKey('splash_screen')) {
        splashRaw = settingsMap['splash_screen'];
      } else if (settingsMap.containsKey('splash_screen_builder')) {
        splashRaw = settingsMap['splash_screen_builder'];
      } else if (settingsMap.containsKey('splash_builder')) {
        splashRaw = settingsMap['splash_builder'];
      } else if (settingsMap.containsKey('splash')) {
        splashRaw = settingsMap['splash'];
      }

      // Fallback: search through data items if splashRaw is null
      if (splashRaw == null) {
        for (var item in data) {
          if (item is Map && item['value'] is Map) {
            final valMap = Map<String, dynamic>.from(item['value'] as Map);
            if (valMap.containsKey('seller_app')) {
              splashRaw = valMap['seller_app'];
              break;
            } else if (valMap.containsKey('splash_screen') &&
                valMap.containsKey('updated_at')) {
              splashRaw = valMap;
              break;
            }
          }
        }
      }

      if (splashRaw == null) {
        try {
          final splashConfig = await CustomSplashRepo().fetchCustomSplash();
          if (splashConfig != null) {
            await SplashStorageService.onSettingsSplashReceived(splashConfig);
            return;
          }
        } catch (_) {}
      }

      _processSplashData(splashRaw);
    } catch (e) {
      debugPrint('Settings fetch error: $e');
      if (state is! SettingsLoaded) {
        emit(SettingsError(e.toString()));
      }
    } finally {
      _isFetching = false;
    }
  }

  // Call this early (e.g. after auth check or app start)
  Future<void> loadCachedSettings() async {
    if (HiveStorage.systemSettings != null) {
      emit(
        SettingsLoaded(
          maintenanceMode: HiveStorage.sellerAppMaintenanceMode,
          maintenanceMessage: HiveStorage.sellerAppMaintenanceMessage,
          isDemoMode: HiveStorage.demoMode,
          demoMessage: HiveStorage.sellerDemoModeMessage,
          currency: HiveStorage.currency,
          currencySymbol: HiveStorage.currencySymbol,
          systemVendorType: HiveStorage.systemVendorType,
          paymentSettings: HiveStorage.paymentSettings,
        ),
      );
    }
  }

  void _processSplashData(dynamic raw) {
    if (raw == null || raw is! Map) {
      SplashStorageService.onSettingsSplashReceived(null);
      return;
    }
    final map = Map<String, dynamic>.from(raw);
    if (map.isEmpty) {
      SplashStorageService.onSettingsSplashReceived(null);
      return;
    }
    final config = SellerAppSplashConfig.fromJson(map);
    SplashStorageService.onSettingsSplashReceived(config);
  }
}
