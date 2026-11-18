import 'dart:async';
import 'dart:developer';
import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:hyper_commerce/screens/cart_page/widgets/shared_cart_bottom_sheet.dart';
import 'package:hyper_commerce/services/referral_attribution_service.dart';
import 'package:hyper_commerce/services/shared_cart_coordinator.dart';
import 'package:hyper_commerce/config/global.dart';
import 'config/global_keys.dart';

class AppLinksDeepLink {
  AppLinksDeepLink._privateConstructor();

  static final AppLinksDeepLink _instance =
      AppLinksDeepLink._privateConstructor();
  static AppLinksDeepLink get instance => _instance;

  late final AppLinks _appLinks = AppLinks();
  StreamSubscription<Uri>? _linkSubscription;
  bool _isInitialized = false;
  Uri? _lastProcessedUri;
  bool _hasPendingLink = false;
  Uri? _pendingInitialUri;

  bool get hasPendingLink => _hasPendingLink;
  Uri? get pendingInitialUri => _pendingInitialUri;

  void setPendingUri(Uri uri) {
    _hasPendingLink = true;
    _pendingInitialUri = uri;
  }

  String? extractSharedListToken(Uri uri) => _extractSharedListToken(uri);

  void clearPendingLink() {
    _hasPendingLink = false;
    _pendingInitialUri = null;
  }

  Future<void> initDeepLinks([BuildContext? context]) async {
    if (_isInitialized) {
      log('Deep links already initialized, skipping');
      return;
    }
    _isInitialized = true;

    try {
      log('NAVIGATOR CONTEXT INITIALIZED');

      // Listen for subsequent links while the app is running
      _linkSubscription = _appLinks.uriLinkStream.listen(
        (Uri? uri) async {
          log('Received deep link: $uri');
          if (uri != null) {
            if (!_isFirebaseAuthCallback(uri)) {
              _hasPendingLink = true;
            }
            await _handleDeepLink(uri);
          }
        },
        onError: (err) {
          log('Deep link error: $err');
        },
      );

      // Handle initial link if the app was opened via a link
      final initialUri = await _appLinks.getInitialLink();
      if (initialUri != null) {
        log('Initial deep link: $initialUri');
        // Firebase auth callbacks belong to Firebase, not the router — never
        // let them gate splash/home navigation.
        if (!_isFirebaseAuthCallback(initialUri)) {
          _hasPendingLink = true;
          _pendingInitialUri = initialUri;
        }
      }
    } catch (e, stack) {
      log('Deep link initialization error: $e');
      log('Stack trace: $stack');
    }
  }

  Future<void> handlePendingInitialLink() async {
    final uri = _pendingInitialUri;
    _pendingInitialUri = null;
    if (uri != null) {
      log('Handling pending initial deep link: $uri');
      await _handleDeepLink(uri);
    }
  }

  /// Consumes and executes any pending cold-start deep link on top of a fully
  /// mounted and stabilized [HomePage].
  Future<void> checkAndHandlePendingDeepLink([BuildContext? context]) async {
    final uri = _pendingInitialUri;
    if (uri == null && !_hasPendingLink) return;

    _pendingInitialUri = null;
    _hasPendingLink = false;
    _lastProcessedUri = null; // Clear so pending link is processed cleanly

    if (uri != null) {
      log('checkAndHandlePendingDeepLink processing: $uri');
      final effectiveContext =
          context ?? GlobalKeys.navigatorKey.currentContext;
      if (effectiveContext != null) {
        await _handleDeepLink(uri, forceContext: effectiveContext);
      } else {
        WidgetsBinding.instance.addPostFrameCallback((_) async {
          await _handleDeepLink(uri);
        });
      }
    }
  }

  Future<void> _handleDeepLink(Uri uri, {BuildContext? forceContext}) async {
    if (_isFirebaseAuthCallback(uri)) {
      log('Ignoring Firebase Auth callback deep link: ${uri.scheme}://${uri.host}${uri.path}');
      _hasPendingLink = false;
      return;
    }

    final navigatorContext =
        forceContext ?? GlobalKeys.navigatorKey.currentContext;
    if (navigatorContext == null) {
      log('Navigator context is null, storing deep link for deferred processing: $uri');
      _pendingInitialUri = uri;
      _hasPendingLink = true;
      return;
    }

    String? currentLoc;
    try {
      final router = GoRouter.of(navigatorContext);
      currentLoc = router.routerDelegate.currentConfiguration.uri.toString();
    } catch (_) {}

    final bool isAppInitializing = currentLoc == null ||
        currentLoc == '/' ||
        currentLoc == AppRoutes.splashScreen ||
        currentLoc == AppRoutes.introSlider;

    // If app is currently on Splash or Onboarding, defer presentation until HomePage mounts
    if (isAppInitializing && forceContext == null) {
      log('App is on $currentLoc, deferring deep link until HomePage mounts: $uri');
      _pendingInitialUri = uri;
      _hasPendingLink = true;
      Global.setIsFirstTime(false);
      return;
    }

    if (_lastProcessedUri == uri) {
      log('URI $uri was just processed, skipping');
      return;
    }
    _lastProcessedUri = uri;

    // Clear the last processed URI after a short delay to allow re-processing the same link later
    Future.delayed(const Duration(seconds: 2), () {
      if (_lastProcessedUri == uri) {
        _lastProcessedUri = null;
      }
    });

    if (kDebugMode) debugPrint('Processing deep link: $uri');

    // Capture any referral code into local storage so it can be auto-applied
    // after the user completes social sign-in. This runs alongside (not
    // instead of) the product-slug routing below.
    final String? referralCode = _extractReferralCode(uri);
    if (referralCode != null && referralCode.isNotEmpty) {
      try {
        await GetIt.instance<ReferralAttributionService>()
            .setCode(referralCode);
        if (kDebugMode) log('Referral code captured');
      } catch (e) {
        if (kDebugMode) log('Failed to persist referral code');
      }
    }

    // Intercept shared cart / shared list deep links before product routing
    final String? sharedListToken = _extractSharedListToken(uri);
    if (sharedListToken != null && sharedListToken.isNotEmpty) {
      log('Recognized shared cart token: $sharedListToken');
      // Set in coordinator so it persists across potential auth redirects
      SharedCartCoordinator.setPending(sharedListToken);

      // Ensure onboarding is dismissed permanently if opened via shared cart
      Global.setIsFirstTime(false);
      _hasPendingLink = false;
      _pendingInitialUri = null;

      final activeContext = forceContext ??
          GlobalKeys.navigatorKey.currentState?.overlay?.context ??
          GlobalKeys.navigatorKey.currentContext ??
          navigatorContext;

      if (activeContext.mounted) {
        await SharedCartBottomSheet.show(activeContext, token: sharedListToken);
      }
      return;
    }

    try {
      final String? productSlug = _extractProductSlug(uri);

      if (productSlug != null && productSlug.isNotEmpty) {
        log('Redirecting to product detail page with slug: $productSlug');
        Global.setIsFirstTime(false);
        _hasPendingLink = false;
        _pendingInitialUri = null;

        final activeContext = forceContext ??
            GlobalKeys.navigatorKey.currentContext ??
            navigatorContext;

        if (!activeContext.mounted) return;
        final router = GoRouter.of(activeContext);

        // Ensure Home is the base route
        router.go(AppRoutes.home);

        // Navigate to product detail page on top of Home
        router.push(
          AppRoutes.productDetailPage,
          extra: {'productSlug': productSlug},
        );
      } else {
        log('No product slug found in deep link: $uri');
        _hasPendingLink = false;
      }
    } catch (e, stack) {
      _hasPendingLink = false;
      if (kDebugMode) {
        debugPrint('Navigation error: $e');
        debugPrint('Stack trace: $stack');
      }
    }
  }

  /// Returns true when the URI is a Firebase Auth callback that the Firebase.
  bool _isFirebaseAuthCallback(Uri uri) {
    // Google's reverse-client-ID scheme is used for OAuth + Firebase callbacks.
    if (uri.scheme.startsWith('com.googleusercontent.apps.')) return true;

    // Custom `<bundle>://firebaseauth/...` or `<scheme>://firebase-auth/...`
    final host = uri.host.toLowerCase();
    if (host == 'firebaseauth' || host == 'firebase-auth') return true;

    // Firebase Auth callback query params (any of these means Firebase owns it).
    if (uri.queryParameters.containsKey('deep_link_id')) return true;
    if (uri.queryParameters.containsKey('authType')) return true;

    // Email-link sign-in and reCAPTCHA callbacks on the Firebase hosting domain.
    if (uri.path.contains('/__/auth/')) return true;

    return false;
  }

  /// Extracts a referral code from an invite-style deep link.
  /// Supports path forms like `/r/CODE`, `/referral/CODE`, `/invite/CODE`
  /// and query params `?ref=CODE` / `?referral_code=CODE` / `?friends_code=CODE`.
  static const Set<String> _allowedReferralSchemes = {'hyperlocal'};
  static const Set<String> _allowedReferralHosts = {
    'hypercommerce.app',
    'www.hypercommerce.app',
  };

  bool _isAllowedReferralHost(Uri uri) {
    final scheme = uri.scheme.toLowerCase();
    if (_allowedReferralSchemes.contains(scheme)) return true;
    if (scheme == 'http' || scheme == 'https') {
      return _allowedReferralHosts.contains(uri.host.toLowerCase());
    }
    return false;
  }

  String? _extractReferralCode(Uri uri) {
    if (!_isAllowedReferralHost(uri)) return null;
    const pathKeys = {'r', 'referral', 'invite'};
    for (final key in pathKeys) {
      if (uri.pathSegments.contains(key)) {
        final idx = uri.pathSegments.indexOf(key);
        if (idx != -1 && idx + 1 < uri.pathSegments.length) {
          final code = uri.pathSegments[idx + 1].trim();
          if (code.isNotEmpty) return code;
        }
      }
    }

    const queryKeys = ['ref', 'referral_code', 'friends_code', 'invite_code'];
    for (final key in queryKeys) {
      final value = uri.queryParameters[key]?.trim();
      if (value != null && value.isNotEmpty) return value;
    }

    return null;
  }

  String? _extractProductSlug(Uri uri) {
    if (uri.pathSegments.contains('product') ||
        uri.pathSegments.contains('p')) {
      final index = uri.pathSegments.contains('product')
          ? uri.pathSegments.indexOf('product')
          : uri.pathSegments.indexOf('p');

      if (index != -1 && index + 1 < uri.pathSegments.length) {
        final slug = uri.pathSegments[index + 1];
        if (slug.isNotEmpty) return slug;
      }
    }

    // 2. Check query parameters (e.g., ?slug=my-slug or ?product_slug=my-slug)
    if (uri.queryParameters.containsKey('slug')) {
      return uri.queryParameters['slug'];
    }
    if (uri.queryParameters.containsKey('product_slug')) {
      return uri.queryParameters['product_slug'];
    }

    // 3.
    const keywords = {'product', 'p', 'home', 'shop', 'categories', 'cart'};
    const nonProductPathKeys = {
      'r',
      'referral',
      'invite',
      'shared',
      'shared-lists',
      'shared-list',
      'shared-cart',
      'shared-carts',
      'share-cart',
      'share-lists',
      'share-list',
      'share',
    };
    if (nonProductPathKeys.contains(uri.host.toLowerCase())) {
      return null;
    }
    if (uri.pathSegments.isNotEmpty) {
      // Skip if any segment indicates a non-product link (e.g. referral, shared-lists, shared).
      final segmentSet = uri.pathSegments.map((s) => s.toLowerCase()).toSet();
      if (segmentSet.any(nonProductPathKeys.contains)) {
        return null;
      }
      final lastSegment = uri.pathSegments.last;
      if (lastSegment.isNotEmpty &&
          !keywords.contains(lastSegment.toLowerCase())) {
        return lastSegment;
      }
    }

    return null;
  }

  /// Extracts a shared cart token from links such as:
  /// - `https://<domain>/cart/shared/{code}`
  /// - https://dev-hypercommerce.vercel.app/cart/shared-lists/{token}
  /// - https://dev-hypercommerce.spa-point.in/api/user/shared-lists/{token}
  /// - https://dev-hypercommerce.spa-point.in/user/shared-lists/{token}
  /// - https://.../shared-lists/{token} or /shared-list/{token}
  /// - https://.../cart/share/{token}
  /// - customer-hypercommerce://shared-lists/{token}
  /// - customer-hypercommerce://cart/shared/{token}
  /// - customer-hypercommerce://cart/share/{token}
  /// - customer-hypercommerce://cart/{token}
  /// - query parameters like ?token=... or ?cart_token=...
  String? _extractSharedListToken(Uri uri) {
    const shareKeys = {
      'shared',
      'shared-lists',
      'shared-list',
      'shared-cart',
      'shared-carts',
      'share-cart',
      'share-lists',
      'share-list',
      'share',
    };

    // 1. Check query parameters first (e.g. ?token=... or ?cart_token=...)
    const queryKeys = [
      'token',
      'cart_token',
      'share_token',
      'list_token',
      'shared_list',
      'cart_id',
      'code',
    ];
    for (final key in queryKeys) {
      final val = uri.queryParameters[key]?.trim();
      if (val != null && val.isNotEmpty) {
        return val;
      }
    }

    // 2. Check if host itself is a share key (e.g. customer-hypercommerce://shared-lists/{token})
    final host = uri.host.toLowerCase().trim();
    if (shareKeys.contains(host) && uri.pathSegments.isNotEmpty) {
      final candidate = uri.pathSegments.first.trim();
      if (candidate.isNotEmpty && candidate != 'import') {
        return candidate;
      }
    }

    // 3. Check if host is 'cart' (e.g. customer-hypercommerce://cart/shared/{token} or customer-hypercommerce://cart/{token})
    if (host == 'cart' && uri.pathSegments.isNotEmpty) {
      final firstSegment = uri.pathSegments.first.toLowerCase().trim();
      if (shareKeys.contains(firstSegment) && uri.pathSegments.length > 1) {
        final candidate = uri.pathSegments[1].trim();
        if (candidate.isNotEmpty && candidate != 'import') {
          return candidate;
        }
      } else if (uri.pathSegments.length == 1 && firstSegment != 'import') {
        final candidate = uri.pathSegments.first.trim();
        if (candidate.isNotEmpty) {
          return candidate;
        }
      }
    }

    // 4. Check path segments for any share key
    for (int i = 0; i < uri.pathSegments.length; i++) {
      final segment = uri.pathSegments[i].toLowerCase().trim();
      if (shareKeys.contains(segment)) {
        if (i + 1 < uri.pathSegments.length) {
          final candidate = uri.pathSegments[i + 1].trim();
          if (candidate.isNotEmpty && candidate != 'import') {
            return candidate;
          }
        }
      }
    }

    // 5. Check path like /cart/shared/<token> or /cart/share/<token>
    if (uri.pathSegments.contains('cart')) {
      final cartIdx = uri.pathSegments.indexOf('cart');
      if (cartIdx + 1 < uri.pathSegments.length) {
        final sub = uri.pathSegments[cartIdx + 1].toLowerCase().trim();
        if (sub == 'share' || sub == 'shared' || shareKeys.contains(sub)) {
          if (cartIdx + 2 < uri.pathSegments.length) {
            final candidate = uri.pathSegments[cartIdx + 2].trim();
            if (candidate.isNotEmpty && candidate != 'import') {
              return candidate;
            }
          }
        }
      }
    }

    // 6. Regex match across full URI string as universal fallback
    final uriStr = uri.toString();
    final regex = RegExp(
      r'(?:user/cart/shared-lists|user/shared-lists|cart/shared-lists|shared-lists|shared-list|shared-cart|share-cart|cart/shared|cart/share)/([a-zA-Z0-9_-]+)',
      caseSensitive: false,
    );
    final match = regex.firstMatch(uriStr);
    if (match != null && match.groupCount >= 1) {
      final candidate = match.group(1)?.trim();
      if (candidate != null && candidate.isNotEmpty && candidate != 'import') {
        return candidate;
      }
    }

    return null;
  }

  void dispose() {
    _linkSubscription?.cancel();
  }
}
