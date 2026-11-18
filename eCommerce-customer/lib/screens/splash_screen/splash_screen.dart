import 'dart:developer';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/bloc/device_sync_bloc/device_sync_bloc.dart';
import 'package:hyper_commerce/bloc/settings_bloc/settings_bloc.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:hyper_commerce/screens/user_profile/bloc/user_profile_bloc/user_profile_bloc.dart';
import 'package:hyper_commerce/utils/widgets/custom_scaffold/custom_scaffold.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../bloc/user_details_bloc/user_details_bloc.dart';
import '../../bloc/user_details_bloc/user_details_state.dart';
import '../../config/global.dart';
import '../../config/helper.dart';
import '../../config/settings_data_instance.dart';
import '../../services/location/location_service.dart';
import '../../utils/app_update/bloc/app_update_bloc.dart';
import '../../utils/app_update/bloc/app_update_event.dart';
import '../../utils/app_update/bloc/app_update_state.dart';
import '../../utils/app_update/model/update_config.dart';
import '../../utils/app_update/widgets/app_update_dialog.dart';
import '../home_page/bloc/category/category_bloc.dart';
import '../home_page/bloc/category/category_event.dart';
import '../home_page/bloc/home_tab/home_tab_bloc.dart';
import '../home_page/bloc/home_tab/home_tab_event.dart';
import '../../bloc/language_bloc/language_list_bloc/language_list_bloc.dart';
import '../introduction_pages/bloc/onboarding_bloc.dart';

import 'widgets/custom_splash_view.dart';
import 'model/custom_splash_model.dart';
import '../../services/splash/splash_storage_service.dart';
import '../../deep_link.dart';
import '../../services/shared_cart_coordinator.dart';
import '../../config/notification_service.dart';



class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with WidgetsBindingObserver {

  bool _hasInitialized        = false;
  bool _hasNavigated          = false;
  bool _lastKnownConnectivity = false;
  bool _settingsRequested     = false;
  bool _updateGateResolved    = false;
  bool _updateDialogShowing   = false;
  late DateTime _splashDisplayStartTime;

  SplashScreenModel? get _activeSplashModel =>
      SettingsData.instance.activeSplashScreen ??
      SplashStorageService.splashModelNotifier.value;

  @override
  void initState() {
    super.initState();
    _splashDisplayStartTime = DateTime.now();
    SplashStorageService.splashModelNotifier.addListener(_onSplashModelChanged);
    WidgetsBinding.instance.addObserver(this);
    // Initialize deep links early to capture any cold-start link (e.g. shared cart)
    AppLinksDeepLink.instance.initDeepLinks();
    // Kick off version check, onboarding, and settings immediately
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AppUpdateBloc>().add(CheckAppUpdate());
      context.read<OnboardingBloc>().add(FetchOnboardingRequest());
      _requestSettingsFetch();
    });
  }

  void _onSplashModelChanged() {
    if (mounted) {
      setState(() {
        _splashDisplayStartTime = DateTime.now();
      });
    }
  }

  @override
  void dispose() {
    SplashStorageService.splashModelNotifier.removeListener(_onSplashModelChanged);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  // ── Update gate ───────────────────────────────────────────────────────────

  void _resolveGate() {
    if (_updateGateResolved) return;
    _updateGateResolved = true;
    _startSettingsIfReady();
    if (_lastKnownConnectivity) {
      navigate();
    }
  }


  /// Called when a soft-update dialog is dismissed via "Do it later".
  void _onSoftUpdateLater() {
    if (!mounted) return;
    Navigator.of(context, rootNavigator: true).pop();
    _updateDialogShowing = false;
    _hasNavigated = false;
    _resolveGate();
  }

  // ── AppUpdate dialog ──────────────────────────────────────────────────────
  void _showUpdateDialog(UpdateConfig config, {required bool isForced}) {
    if (_updateDialogShowing || !mounted) return;
    _updateDialogShowing = true;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AppUpdateDialog(
        config: config,
        isForced: isForced,
        onLater: _onSoftUpdateLater,
      ),
    );
  }

  // ── Settings & navigation gate ────────────────────────────────────────────
  void _requestSettingsFetch() {
    if (!mounted || _settingsRequested) return;
    _settingsRequested = true;
    context.read<SettingsBloc>().add(FetchSettingsData(context: context));
  }

  void _startSettingsIfReady() {
    if (!_updateGateResolved || !_lastKnownConnectivity) return;
    _requestSettingsFetch();
  }

  // ── Location helpers ──────────────────────────────────────────────────────
  Future<bool?> _showLocationAccessDialog() async {
    return await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        title: Text(dialogContext.tr('locationAccessNeeded')),
        content: Text(
          dialogContext.tr('locationAccessDescription'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(dialogContext.tr('later')),
          ),
          TextButton(
            onPressed: () async {
              await Geolocator.openLocationSettings();
              if (dialogContext.mounted) Navigator.pop(dialogContext, true);
            },
            child: Text(dialogContext.tr('openSettings')),
          ),
          TextButton(
            onPressed: () async {
              await openAppSettings();
              if (dialogContext.mounted) Navigator.pop(dialogContext, true);
            },
            child: Text(dialogContext.tr('appPermissions')),
          ),
        ],
      ),
    );
  }

  Future<void> _checkAndSetLocation() async {
    if (!AppHelpers.isDemo && LocationService.hasStoredLocation()) return;

    String? lat, lng;

    lat = AppHelpers.defaultLat;
    lng = AppHelpers.defaultLng;
    if (lat.isNotEmpty && lng.isNotEmpty) {
      await LocationService.storeLocationFromCoordinates(
        latitude: lat,
        longitude: lng,
      );
      return;
    }

    if (!LocationService.hasStoredLocation()) {
      final bool? granted = await _showLocationAccessDialog();
      if (granted == true) {
        await LocationService.requestAndStoreLocationWithRetry();
      }
    }
  }

  // ── Navigation ────────────────────────────────────────────────────────────
  Future<void> navigate() async {
    _dispatchInitialDataFetches();
    _syncDeviceIfLoggedIn();

    if (_hasNavigated) return;
    _hasNavigated = true;

    // 1. Ensure Settings are loaded so customer_app splash screen and updated_at are resolved
    final settingsBloc = context.read<SettingsBloc>();
    if (settingsBloc.state is! SettingsLoaded &&
        settingsBloc.state is! SettingsFailure &&
        settingsBloc.state is! MaintenanceModeEnabled) {
      try {
        await settingsBloc.stream
            .firstWhere((state) =>
                state is SettingsLoaded ||
                state is SettingsFailure ||
                state is MaintenanceModeEnabled)
            .timeout(const Duration(seconds: 4));
      } catch (_) {}
    }

    if (!mounted) return;

    // 2. Respect display seconds entered from backend (fallback to default 1400ms)
    final splashModel = _activeSplashModel;
    final int? backendSeconds = splashModel?.minDisplaySeconds;
    final int targetMs = (backendSeconds != null && backendSeconds > 0)
        ? backendSeconds * 1000
        : 1400;

    final int elapsedMs =
        DateTime.now().difference(_splashDisplayStartTime).inMilliseconds;
    final int remainingMs = targetMs - elapsedMs;

    if (remainingMs > 0) {
      debugPrint(
          'Splash: Respecting backend seconds ($targetMs ms, remaining: $remainingMs ms)');
      await Future.delayed(Duration(milliseconds: remainingMs));
    }
    if (!mounted) return;

    final bool isOnboardingEnabled = SettingsData.instance.isOnboardingEnabled;

    // Always ensure onboarding data is loaded before entering onboarding screen so no loader appears
    if (isOnboardingEnabled &&
        Global.isFirstTime &&
        !AppLinksDeepLink.instance.hasPendingLink &&
        !SharedCartCoordinator.hasPending) {
      final onboardingBloc = context.read<OnboardingBloc>();
      log('onBoarding State ${onboardingBloc.state}');
      if (onboardingBloc.state is OnboardingLoading ||
          onboardingBloc.state is OnboardingInitial) {
        try {
          await onboardingBloc.stream
              .firstWhere((state) =>
                  state is OnboardingSuccess || state is OnboardingFailure)
              .timeout(const Duration(seconds: 4));
        } catch (_) {}
      }
    }

    if (!mounted || !_lastKnownConnectivity) {
      _hasNavigated = false;
      return;
    }

    // 1. Deep link or pending shared cart check takes absolute priority over onboarding
    if (AppLinksDeepLink.instance.hasPendingLink ||
        SharedCartCoordinator.hasPending) {
      log('Deep link or shared cart pending, prioritizing over onboarding');
      Global.setIsFirstTime(false);
      GoRouter.of(context).go(AppRoutes.home);
      return;
    }

    // 2. Notification check
    if (NotificationService.hasPendingNotification) {
      log('Notification pending, navigating to home first for safe stack');
      GoRouter.of(context).go(AppRoutes.home);
      return;
    }

    // 3. First-time onboarding check (only when newly installed, not seen, and no deep link)
    if (isOnboardingEnabled && Global.isFirstTime) {
      GoRouter.of(context).go(AppRoutes.introSlider);
      return;
    }

    // 4. Default landing route for all subsequent launches / app restarts
    GoRouter.of(context).go(AppRoutes.home);
  }

  void _handleConnectivityChanged(bool isConnected) {
    _lastKnownConnectivity = isConnected;

    if (!isConnected) {
      _hasNavigated = false;
      return;
    }

    if (!_hasInitialized) {
      _hasInitialized = true;
      if (_updateGateResolved) navigate();
      return;
    }

    if (!_hasNavigated && _updateGateResolved) navigate();
  }

  void _dispatchInitialDataFetches() {
    context.read<HomeTabBloc>().add(FetchHomeTabs(context: context));
    context.read<CategoryBloc>().add(FetchCategory(context: context));
    context.read<LanguageListBloc>().add(FetchLanguageList(locale: Global.currentLanguage));
    if (SettingsData.instance.isOnboardingEnabled && Global.isFirstTime) {
      if (context.read<OnboardingBloc>().state is OnboardingInitial) {
        context.read<OnboardingBloc>().add(FetchOnboardingRequest());
      }
    }
    if (Global.userData != null) {
      context.read<UserProfileBloc>().add(FetchUserProfile());
    }
  }

  void _syncDeviceIfLoggedIn() {
    if (Global.userData != null && Global.userData!.token.isNotEmpty) {
      context.read<DeviceSyncBloc>().add(SyncDevice(
        deviceType: Platform.isAndroid ? 'android' : 'ios',
        previousToken: Global.userData?.fcm ?? '',
        roleType: 'customer',
      ));
    }
  }

  // ── Build ─────────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    return BlocListener<AppUpdateBloc, AppUpdateState>(
      listener: (context, state) {
        if (state is AppUpdateConfigLoaded) {
          if (!state.isUpdateAvailable) {
            _resolveGate();
          } else {
            final isForced = state.isForced;
            _showUpdateDialog(state.config, isForced: isForced);
          }
        }
        if (state is AppUpdateFailed) {
          _resolveGate();
        }
      },
      child: BlocListener<SettingsBloc, SettingsState>(
        listener: (context, state) async {
          if (state is MaintenanceModeEnabled) {
            GoRouter.of(context).go(
              AppRoutes.maintenancePage,
              extra: {'message': state.maintenanceModeMessage},
            );
            return;
          }
          if (state is SettingsLoaded) {
            if (SettingsData.instance.system?.webMaintenanceMode ?? false) {
              GoRouter.of(context).go(AppRoutes.maintenancePage);
              return;
            }
            if (SettingsData.instance.isOnboardingEnabled && Global.isFirstTime) {
              if (context.read<OnboardingBloc>().state is OnboardingInitial) {
                context.read<OnboardingBloc>().add(FetchOnboardingRequest());
              }
            }
            await _checkAndSetLocation();
            if (_lastKnownConnectivity) _handleConnectivityChanged(true);
          }
        },
        child: BlocListener<UserDataBloc, UserDataState>(
          listener: (context, state) {},
          child: _buildBody(context),
        ),
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    return CustomScaffold(
      showViewCart: false,
      notifyConnectivityStatusOnInit: true,
      onConnectivityChanged: (isConnected, _) {
        _lastKnownConnectivity = isConnected;
        if (!_updateGateResolved) {
          if (isConnected) _startSettingsIfReady();
          return;
        }
        if (context.read<SettingsBloc>().state is SettingsLoaded) {
          _handleConnectivityChanged(isConnected);
        } else if (isConnected) {
          _startSettingsIfReady();
        }
      },
      onConnectivityRestored: (value){
        context.read<SettingsBloc>().add(FetchSettingsData(context: context));
        navigate();
      },
      body: ValueListenableBuilder<SplashScreenModel?>(
        valueListenable: SplashStorageService.splashModelNotifier,
        builder: (context, splashModel, _) {
          final effectiveModel =
              splashModel ?? SettingsData.instance.activeSplashScreen;
          return CustomSplashView(
            splashModel: effectiveModel,
            defaultChild: Image.asset(
              'assets/images/app_logos/app-logo-dark.png',
              height: 180,
              width: 250,
              fit: BoxFit.contain,
            ),
          );
        },
      ),
    );
  }
}
