import 'dart:developer';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/bloc/settings/settings_cubit.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:flutter_svg/svg.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/profile/bloc/profile_bloc.dart';
import 'package:hyper_commerce_seller/service/notification_service.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/bloc/current_subscription/current_subscription_bloc.dart';
import 'package:hyper_commerce_seller/screen/introduction_pages/bloc/onboarding_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/service/subscription_limit_service.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';

import '../../../utils/app_update/bloc/app_update_bloc.dart';
import '../../../utils/app_update/bloc/app_update_event.dart';
import '../../../utils/app_update/bloc/app_update_state.dart';
import '../../../utils/app_update/model/update_config.dart';
import '../../../utils/app_update/widgets/app_update_dialog.dart';
import '../model/custom_splash_model.dart';
import '../widgets/custom_splash_view.dart';
import '../../../services/splash/splash_storage_service.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  bool _isNavigating = false;
  bool _updateDialogShowing = false;
  late DateTime _splashDisplayStartTime;

  @override
  void initState() {
    super.initState();
    _splashDisplayStartTime = DateTime.now();
    SplashStorageService.splashModelNotifier.addListener(_onSplashModelChanged);
    context.read<AppUpdateBloc>().add(CheckAppUpdate());
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
    SplashStorageService.splashModelNotifier
        .removeListener(_onSplashModelChanged);
    super.dispose();
  }

  Future<void> _initializeAndNavigate() async {
    if (_isNavigating) return;
    _isNavigating = true;

    // Pre-fetch onboarding if onboarding might be shown
    final bool mightShowOnboarding = (HiveStorage.isFirstTime || kDebugMode) &&
        HiveStorage.isOnboardingEnabled;

    Future<void>? onboardingFuture;
    if (mightShowOnboarding) {
      try {
        final onboardingBloc = context.read<OnboardingBloc>();
        if (onboardingBloc.state is OnboardingInitial) {
          onboardingBloc.add(FetchOnboardingRequest());
        }
        if (onboardingBloc.state is OnboardingLoading ||
            onboardingBloc.state is OnboardingInitial) {
          onboardingFuture = onboardingBloc.stream
              .firstWhere(
                (state) =>
                    state is OnboardingSuccess || state is OnboardingFailure,
              )
              .timeout(const Duration(seconds: 3))
              .then<void>((_) => null)
              .catchError((_) => null);
        }
      } catch (_) {}
    }

    // ── Parallel operations ───────────────────────────────────────
    final futures = <Future>[
      // 1. Load & fetch settings (cached + fresh from server)
      _initSettings(),

      // 2. Pre-fetch onboarding data in parallel if needed
      if (onboardingFuture != null) onboardingFuture,

      // 3. Just ensure Hive is ready (already opened in main.dart)
      Future.value(),
    ];

    // Wait for settings and parallel init tasks
    await Future.wait(futures);

    if (!mounted) return;

    // ── Respect display seconds entered from backend ──────────────
    final splashModel = SplashStorageService.splashModelNotifier.value;
    final int? backendSeconds = splashModel?.minDisplaySeconds;

    // If backend configured display seconds, use that (e.g. 3s, 5s).
    // If no backend splash screen exists or no seconds configured, fallback to default 1400ms.
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

    // ── Navigation decision ───────────────────────────────────────
    final token = HiveStorage.userToken;
    if (token != null && token.trim().isNotEmpty) {
      // Fetch user profile and store in UserHelper before navigating
      await ProfileBloc.fetchAndStoreProfile();

      // Sync subscription limits
      await SubscriptionLimitService().syncSubscription();
      if (!mounted) return;
      context.read<CurrentSubscriptionBloc>().add(FetchCurrentSubscription());

      // Check for pending notifications
      final notificationManager = NotificationManager();
      if (notificationManager.hasPendingNotification) {
        debugPrint('Splash: Handling pending notification');
        notificationManager.handlePendingNotification(context);

        // Fallback timer: if the app is still on splash screen after 3 seconds,
        // something likely went wrong with the notification navigation.
        Future.delayed(const Duration(seconds: 3), () {
          if (mounted &&
              GoRouterState.of(context).uri.toString() ==
                  AppRoutes.splashScreen) {
            debugPrint('Splash: Fallback navigation triggered');
            context.go(AppRoutes.introSlider);
          }
        });
      } else {
        if (!mounted) return;
        if ((HiveStorage.isFirstTimeSubscriptionVisit || kDebugMode) &&
            HiveStorage.isSubscriptionAvailable &&
            (HiveStorage.activePlanId == null ||
                HiveStorage.activePlanId == 0) &&
            HiveStorage.pendingSubscriptionId == null) {
          debugPrint("Splash: Navigating to subscription choose plan");
          context.go(AppRoutes.subscriptionChoosePlan);
        } else {
          context.go(AppRoutes.introSlider);
        }
      }
    } else {
      if (!mounted) return;
      final bool shouldShowOnboarding =
          (HiveStorage.isFirstTime || kDebugMode) &&
              HiveStorage.isOnboardingEnabled;

      if (shouldShowOnboarding) {
        debugPrint('Splash: Navigating to onboarding screen');
        context.go(AppRoutes.introSlider);
        return;
      }
      context.go(AppRoutes.introSlider);
    }
  }

  Future<void> _initSettings() async {
    final settingsCubit = context.read<SettingsCubit>();

    // First: immediately use cached values (if exist)
    await settingsCubit.loadCachedSettings();

    try {
      await settingsCubit
          .fetchAndSaveSettings()
          .timeout(const Duration(seconds: 4));
    } catch (e) {
      debugPrint('Settings fetch failed or timed out: $e');
      // On first run, a failure here might be problematic, but we continue
      // to avoid blocking the user indefinitely if the server is down.
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.tertiary,
      body: BlocListener<AppUpdateBloc, AppUpdateState>(
        listener: (context, state) {
          log('🔍 SPLASH SCREEN: AppUpdateState listener -> status: ${state.status}');
          if (state.status == AppUpdateStatus.success) {
            if (!state.isUpdateAvailable) {
              log('🔍 SPLASH SCREEN: No update available, navigating...');
              _initializeAndNavigate();
            } else {
              log('🔍 SPLASH SCREEN: Update available (forced: ${state.isForced})');
              final isForced = state.isForced;
              _showUpdateDialog(state.config!, isForced: isForced);
            }
          }

          if (state.status == AppUpdateStatus.failure) {
            log('🔍 SPLASH SCREEN: Update check failed, failing open...');
            _initializeAndNavigate();
          }
        },
        child: ValueListenableBuilder<SplashScreenModel?>(
          valueListenable: SplashStorageService.splashModelNotifier,
          builder: (context, splashModel, _) {
            return CustomSplashView(
              splashModel: splashModel,
              defaultChild: Image.asset(
                ImagesPath.darkLogo,
                width: 180,
                height: 180,
                fit: BoxFit.contain,
              ),
            );
          },
        ),
      ),
    );
  }

  void _onForceUpdatePressed() {
    // Dialog stays open; url_launcher opens the store.
    // Nothing to resolve here — navigation stays blocked.
  }
  void _onSoftUpdateLater() {
    if (!mounted) return;
    Navigator.of(context, rootNavigator: true).pop();
    _updateDialogShowing = false;
    _isNavigating = false;
    _initializeAndNavigate();
  }

  void _showUpdateDialog(UpdateConfig config, {required bool isForced}) {
    if (_updateDialogShowing || !mounted) return;
    _updateDialogShowing = true;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AppUpdateDialog(
        config: config,
        isForced: isForced,
        onLater: isForced ? _onForceUpdatePressed : _onSoftUpdateLater,
      ),
    );
  }
}
