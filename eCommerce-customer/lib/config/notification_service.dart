import 'dart:convert';
import 'dart:developer';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../bloc/device_sync_bloc/device_sync_bloc.dart';
import '../firebase_options.dart';
import '../router/app_routes.dart';
import '../screens/my_orders/repo/order_repo.dart';
import '../screens/notification_page/bloc/notification_bloc.dart';
import '../screens/payment_confirmation/service/payment_event_bus.dart';
import '../screens/payment_options/model/payment_status.dart';
import '../screens/product_listing_page/model/product_listing_type.dart';
import '../screens/support_page/service/support_route_presence.dart';
import 'dependency_injection_container.dart';
import 'global.dart';
import 'global_keys.dart';
import 'helper.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  log('Background message received: ${message.messageId}');

  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  }

  // Ensure local notifications plugin and notification channels are initialized in background isolate
  await NotificationService.ensureInitializedForBackground();

  final isDelivered = NotificationService.isDeliveredMessage(message);

  // If this is a data-only push OR an order delivered push requiring the "Rate now" action button:
  if (message.notification == null || isDelivered) {
    log('Displaying rich notification with action buttons in background (isDelivered=$isDelivered)');
    await NotificationService.showNotificationFromMessage(message);
  } else {
    log('Standard notification will be shown by FCM using custom channel');
  }
}

class NotificationService {
  NotificationService();

  static const MethodChannel _actionChannel =
      MethodChannel('com.hypercommerce.customer/notification_actions');

  static Map<String, dynamic>? _pendingNotificationPayload;
  static String? _pendingActionId;

  static bool get hasPendingNotification => _pendingNotificationPayload != null;

  /// Consumes and executes navigation for any cold-start notification that was
  /// captured while the app was booting up.
  static void checkAndHandlePendingNotification() {
    if (_pendingNotificationPayload != null) {
      final data = _pendingNotificationPayload!;
      final actionId = _pendingActionId;
      _pendingNotificationPayload = null;
      _pendingActionId = null;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        handleNotificationNavigation(data, actionId: actionId);
      });
    }
  }

  final FirebaseMessaging _firebaseMessaging = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  static bool _isBackgroundInitialized = false;

  static Future<void> createCustomNotificationChannel() async {
    const String channelId = 'custom_sound_channel';
    const String channelName = 'Custom Sound Notifications';
    const String channelDescription = 'Notifications with custom sound for orders and updates';

    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      channelId,
      channelName,
      description: channelDescription,
      importance: Importance.high,
      sound: RawResourceAndroidNotificationSound('notification_sound'),
      playSound: true,
      enableVibration: true,
      enableLights: true,
    );

    await _flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    log('✅ Custom notification channel created: $channelId with sound: notification_sound');
  }

  static Future<void> ensureInitializedForBackground() async {
    if (_isBackgroundInitialized) return;
    _isBackgroundInitialized = true;

    // ← Important: Create channel BEFORE initializing local notifications
    await createCustomNotificationChannel();

    // Initialize Local Notifications
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('notification');

    final DarwinInitializationSettings initializationSettingsIOS =
        DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
      notificationCategories: [
        DarwinNotificationCategory(
          'order_delivered_category',
          actions: <DarwinNotificationAction>[
            DarwinNotificationAction.plain(
              'rate_now',
              'Rate now',
              options: <DarwinNotificationActionOption>{
                DarwinNotificationActionOption.foreground,
              },
            ),
          ],
        ),
      ],
    );

    final InitializationSettings initializationSettings = InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );

    await _flutterLocalNotificationsPlugin.initialize(
      settings: initializationSettings,
      onDidReceiveNotificationResponse: (NotificationResponse response) {
        log('Notification tapped: payload=${response.payload}, actionId=${response.actionId}');
        if (response.payload != null) {
          try {
            final Map<String, dynamic> data = jsonDecode(response.payload!);
            handleNotificationNavigation(data, actionId: response.actionId);
          } catch (e) {
            log('Error parsing notification payload: $e');
          }
        }
      },
    );
  }

  Future<void> initFirebaseMessaging(BuildContext context) async {
    await _requestNotificationPermissions();
    await ensureInitializedForBackground();

    // Background message handler
    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    // Foreground message handler
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      log('📱 Foreground notification received');
      _emitPaymentEvent(message);
      showNotificationFromMessage(message);

      // Refresh in-app notifications and unread badge count
      final navContext = GlobalKeys.navigatorKey.currentContext;
      if (navContext != null) {
        try {
          navContext.read<NotificationBloc>().add(FetchNotifications());
        } catch (_) {}
      }
    });

    // When app is opened from notification (background/suspended)
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      log('App opened from notification');
      handleNotificationNavigation(message.data);
    });

    final initialMessage = await _firebaseMessaging.getInitialMessage();
    if (initialMessage != null) {
      log('Initial notification received on cold start: ${initialMessage.data}');
      _pendingNotificationPayload = initialMessage.data;
      _pendingActionId = null;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        checkAndHandlePendingNotification();
      });
    }

    final launchDetails = await _flutterLocalNotificationsPlugin.getNotificationAppLaunchDetails();
    if (launchDetails?.didNotificationLaunchApp ?? false) {
      final response = launchDetails!.notificationResponse;
      if (response?.payload != null) {
        log('Initial local notification response on cold start: ${response?.payload}, actionId: ${response?.actionId}');
        try {
          final Map<String, dynamic> data = jsonDecode(response!.payload!);
          _pendingNotificationPayload = data;
          _pendingActionId = response.actionId;
          WidgetsBinding.instance.addPostFrameCallback((_) {
            checkAndHandlePendingNotification();
          });
        } catch (e) {
          log('Error parsing launch notification payload: $e');
        }
      }
    }

    // Native action bridge (iOS UNNotificationAction & Android native actions)
    _actionChannel.setMethodCallHandler((call) async {
      if (call.method == 'onAction') {
        try {
          final arguments = call.arguments;
          if (arguments is Map) {
            final actionId = arguments['actionId']?.toString();
            final rawData = arguments['data'];
            Map<String, dynamic> data = {};
            if (rawData is Map) {
              data = Map<String, dynamic>.from(rawData);
            }
            log('Native notification action received: actionId=$actionId, data=$data');
            handleNotificationNavigation(data, actionId: actionId);
          }
        } catch (e) {
          log('Error handling native notification action: $e');
        }
      }
    });

    try {
      final pending = await _actionChannel.invokeMethod('getPendingAction');
      if (pending is Map) {
        final actionId = pending['actionId']?.toString();
        final rawData = pending['data'];
        if (rawData is Map) {
          final data = Map<String, dynamic>.from(rawData);
          log('Cold-start native notification action found: actionId=$actionId, data=$data');
          _pendingNotificationPayload = data;
          _pendingActionId = actionId;
          WidgetsBinding.instance.addPostFrameCallback((_) {
            checkAndHandlePendingNotification();
          });
        }
      }
    } catch (e) {
      log('Error checking pending native notification action: $e');
    }

    _firebaseMessaging.onTokenRefresh.listen((String newToken) {
      log('New FCM Token: $newToken');
      if (Global.userData != null && Global.userData!.token.isNotEmpty) {
        getIt<DeviceSyncBloc>().add(SyncDevice(
          deviceType: Platform.isAndroid ? 'android' : 'ios',
          previousToken: Global.userData?.fcm ?? '',
          roleType: 'customer',
        ));
      }
    });
  }

  Future<void> _requestNotificationPermissions() async {
    await _firebaseMessaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: true,
    );

    if (Platform.isAndroid) {
      await _flutterLocalNotificationsPlugin
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();
    }
  }

  Future<String?> getFcmToken() async => getFCMToken();

  /// Surfaces payment-status pushes to the confirming-payment screen.
  void _emitPaymentEvent(RemoteMessage message) {
    if (message.data['type']?.toString() != 'payment') return;
    final slug = message.data['order_slug']?.toString() ?? '';
    if (slug.isEmpty) return;
    PaymentEventBus.instance.emit(PaymentPushEvent(
      orderSlug: slug,
      paymentStatus:
          OrderPaymentStatus.fromString(message.data['payment_status']?.toString()),
    ));
  }

  static int _generateNotificationId(RemoteMessage message) {
    if (message.messageId != null && message.messageId!.isNotEmpty) {
      return message.messageId.hashCode.abs() % 100000;
    }
    return DateTime.now().millisecondsSinceEpoch.remainder(100000);
  }

  static bool isDeliveredMessage(RemoteMessage message) {
    Map<String, dynamic> combined = Map<String, dynamic>.from(message.data);
    final rawMeta = message.data['metadata'];
    if (rawMeta is Map) {
      combined.addAll(Map<String, dynamic>.from(rawMeta));
    } else if (rawMeta is String && rawMeta.isNotEmpty) {
      try {
        final decoded = jsonDecode(rawMeta);
        if (decoded is Map) combined.addAll(Map<String, dynamic>.from(decoded));
      } catch (_) {}
    }

    final rawTitle = message.notification?.title ?? combined['title']?.toString();
    final rawBody = message.notification?.body ??
        combined['body']?.toString() ??
        combined['message']?.toString();
    final titleLower = (rawTitle ?? '').toLowerCase();
    final bodyLower = (rawBody ?? '').toLowerCase();

    // Check if actions list explicitly defines rate_now
    bool hasRateNowInActions = false;
    final actionsList = combined['actions'];
    if (actionsList is Iterable) {
      for (final a in actionsList) {
        if (a is Map &&
            (a['action'] == 'rate_now' ||
                a['title']?.toString().toLowerCase() == 'rate now')) {
          hasRateNowInActions = true;
          break;
        }
      }
    }

    final type = combined['type']?.toString();
    final status = (combined['status'] ??
            combined['customer_status'] ??
            combined['fulfillment_status'] ??
            '')
        .toString()
        .toLowerCase();

    return hasRateNowInActions ||
        type == 'Delivered' ||
        type == 'delivery' ||
        type == 'order_delivered' ||
        status == 'delivered' ||
        status == 'fulfilled' ||
        titleLower.contains('delivered') ||
        bodyLower.contains('delivered');
  }

  /// Shows rich local notification with action buttons and big picture image
  static Future<void> showNotificationFromMessage(RemoteMessage message) async {
    await ensureInitializedForBackground();

    if (message.data['type']?.toString() == 'support_message' &&
        SupportRoutePresence.isViewing(
          message.data['thread_uuid']?.toString(),
        )) {
      return;
    }

    Map<String, dynamic> combined = Map<String, dynamic>.from(message.data);
    final rawMeta = message.data['metadata'];
    if (rawMeta is Map) {
      combined.addAll(Map<String, dynamic>.from(rawMeta));
    } else if (rawMeta is String && rawMeta.isNotEmpty) {
      try {
        final decoded = jsonDecode(rawMeta);
        if (decoded is Map) combined.addAll(Map<String, dynamic>.from(decoded));
      } catch (_) {}
    }

    final rawTitle = message.notification?.title ?? combined['title']?.toString();
    final rawBody = message.notification?.body ??
        combined['body']?.toString() ??
        combined['message']?.toString();

    final isDeliveredType = isDeliveredMessage(message);

    final rawReviewGiven = combined['is_user_review_given'];
    final bool isUserReviewGiven = rawReviewGiven == true ||
        rawReviewGiven?.toString() == 'true' ||
        rawReviewGiven?.toString() == '1';

    // If both title and body are empty, this is a silent data-only push (e.g. sync/payment)
    if (!isDeliveredType &&
        (rawTitle == null || rawTitle.isEmpty) &&
        (rawBody == null || rawBody.isEmpty)) {
      return;
    }

    final String title = rawTitle != null && rawTitle.isNotEmpty
        ? rawTitle
        : (isDeliveredType ? 'Order Delivered' : 'Notification');

    final String body = rawBody ?? '';

    final imageUrl =
        message.notification?.android?.imageUrl ??
            message.notification?.apple?.imageUrl ??
            message.data['image']?.toString();

    BigPictureStyleInformation? bigPictureStyle;
    final List<DarwinNotificationAttachment> iosAttachments = [];
    if (imageUrl != null && imageUrl.isNotEmpty) {
      try {
        final fileName = 'notif_image${_imageExtensionFromUrl(imageUrl)}';
        final filePath = await downloadAndSaveImage(imageUrl, fileName);
        if (File(filePath).existsSync()) {
          bigPictureStyle = BigPictureStyleInformation(
            FilePathAndroidBitmap(filePath),
            largeIcon: const DrawableResourceAndroidBitmap('@mipmap/launcher_icon'),
            contentTitle: title,
            summaryText: body,
          );
          iosAttachments.add(DarwinNotificationAttachment(filePath));
        }
      } catch (e) {
        log('Error downloading notification image: $e');
      }
    }

    List<AndroidNotificationAction>? androidActions;
    String? darwinCategory;
    if (isDeliveredType && !isUserReviewGiven) {
      androidActions = <AndroidNotificationAction>[
        const AndroidNotificationAction(
          'rate_now',
          'Rate now',
          showsUserInterface: true,
          cancelNotification: true,
        ),
      ];
      darwinCategory = 'order_delivered_category';
    }

    final AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
      'custom_sound_channel', // Must match the channel created above
      'Custom Sound Notifications',
      importance: Importance.max,
      priority: Priority.high,
      icon: '@drawable/notification',
      styleInformation: bigPictureStyle,
      largeIcon: const DrawableResourceAndroidBitmap('@mipmap/launcher_icon'),
      sound: const RawResourceAndroidNotificationSound('notification_sound'),
      fullScreenIntent: true,
      enableVibration: true,
      enableLights: true,
      actions: androidActions,
    );

    final DarwinNotificationDetails iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
      sound: 'notification_sound.caf',
      attachments: iosAttachments,
      categoryIdentifier: darwinCategory,
    );

    final NotificationDetails notificationDetails = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _flutterLocalNotificationsPlugin.show(
      id: _generateNotificationId(message),
      title: title,
      body: body,
      notificationDetails: notificationDetails,
      payload: jsonEncode(combined),
    );
  }

  /// Returns a leading-dot file extension (e.g.
  static String _imageExtensionFromUrl(String url) {
    const allowed = {'.jpg', '.jpeg', '.png', '.gif'};
    final uri = Uri.tryParse(url);
    if (uri == null || uri.pathSegments.isEmpty) return '.jpg';
    final lastSegment = uri.pathSegments.last;
    final dotIndex = lastSegment.lastIndexOf('.');
    if (dotIndex == -1 || dotIndex == lastSegment.length - 1) return '.jpg';
    final candidate = '.${lastSegment.substring(dotIndex + 1).toLowerCase()}';
    return allowed.contains(candidate) ? candidate : '.jpg';
  }

  int convertIntFromType(RemoteMessage message) {
    return _generateNotificationId(message);
  }

  static String notificationSlugTypeUpdate(String typeValue) {
    final lower = typeValue.toLowerCase();
    if (lower == 'order' ||
        lower == 'delivery' ||
        lower == 'new_order' ||
        lower == 'order_update' ||
        lower == 'delivered' ||
        lower == 'order_delivered' ||
        lower == 'return_order' ||
        lower == 'return_order_update' ||
        lower == 'payment') {
      return 'order_slug';
    } else if (lower == 'product' ||
        lower == 'featured_section' ||
        lower == 'brand' ||
        lower == 'category' ||
        lower == 'store' ||
        lower == 'offer' ||
        lower == 'promotion') {
      return 'slug';
    }
    return 'slug';
  }

  static void handleNotificationNavigation(Map<String, dynamic> data, {String? actionId}) {
    Map<String, dynamic> combined = Map<String, dynamic>.from(data);
    final rawMeta = data['metadata'];
    if (rawMeta is Map) {
      combined.addAll(Map<String, dynamic>.from(rawMeta));
    } else if (rawMeta is String && rawMeta.isNotEmpty) {
      try {
        final decoded = jsonDecode(rawMeta);
        if (decoded is Map) combined.addAll(Map<String, dynamic>.from(decoded));
      } catch (_) {}
    }

    final type = combined['type']?.toString() ??
        (combined.containsKey('order_slug') || combined.containsKey('order-slug')
            ? 'order'
            : null);
    if (type == null) return;

    final navigatorContext = GlobalKeys.navigatorKey.currentContext;

    if (navigatorContext == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final retryContext = GlobalKeys.navigatorKey.currentContext;
        if (retryContext != null) handleNotificationNavigation(data, actionId: actionId);
      });
      return;
    }

    final notificationId = combined['notification_id']?.toString() ?? combined['id']?.toString();
    if (notificationId != null &&
        notificationId.isNotEmpty &&
        notificationId != 'null') {
      try {
        navigatorContext.read<NotificationBloc>().add(
              MarkAsReadSpecificNotification(notificationId: notificationId),
            );
      } catch (_) {}
    }

    if (type == 'url' || type == 'external_url' || type == 'link') {
      final urlString = combined['url']?.toString() ?? combined['slug']?.toString() ?? '';
      if (urlString.isNotEmpty) {
        final uri = Uri.tryParse(urlString);
        if (uri != null) {
          launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      }
      return;
    }

    if (type == 'support_message') {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        GoRouter.of(navigatorContext).push(
          AppRoutes.supportPage,
          extra: {'ticket-slug': combined['ticket_slug']?.toString()},
        );
      });
      return;
    }

    final slugType = notificationSlugTypeUpdate(type);
    final slugValue = combined['order_slug']?.toString() ??
        combined['order-slug']?.toString() ??
        combined['orderSlug']?.toString() ??
        combined[slugType]?.toString() ??
        combined['slug']?.toString() ??
        '';

    dynamic rawItemId = combined['order_item_id'] ??
        combined['order-item-id'] ??
        combined['orderItemId'] ??
        combined['item_id'];

    if (rawItemId == null && combined['actions'] is Iterable) {
      for (final a in combined['actions']) {
        if (a is Map) {
          rawItemId = a['order_item_id'] ?? a['orderItemId'] ?? a['item_id'];
          if (rawItemId != null) break;
        }
      }
    }

    if (rawItemId == null && combined['order_item_ids'] != null) {
      final rawList = combined['order_item_ids'];
      if (rawList is List && rawList.isNotEmpty) {
        rawItemId = rawList.first;
      } else if (rawList is String && rawList.isNotEmpty) {
        try {
          final decodedList = jsonDecode(rawList);
          if (decodedList is List && decodedList.isNotEmpty) {
            rawItemId = decodedList.first;
          }
        } catch (_) {
          final match = RegExp(r'\d+').firstMatch(rawList);
          if (match != null) rawItemId = match.group(0);
        }
      }
    }

    final orderItemId = rawItemId?.toString() ?? '';
    final title = combined['title']?.toString() ?? '';

    // If user tapped the "Rate now" action button on the delivered notification
    if (actionId == 'rate_now') {
      final parsedItemId = int.tryParse(orderItemId);
      if (parsedItemId != null && parsedItemId > 0) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          GoRouter.of(navigatorContext).push(
            AppRoutes.productRating,
            extra: {
              'orderItemId': parsedItemId,
              'productName': combined['product_name'] ?? combined['productName'] ?? combined['title'] ?? title,
              'variantTitle': (combined['variant_title'] ?? combined['variantTitle'])?.toString(),
              'image': (combined['image'] ?? combined['product_image'] ?? combined['imageUrl'])?.toString(),
              'initialRating': 0,
            },
          );
        });
        return;
      } else if (slugValue.isNotEmpty) {
        // If specific order_item_id is not directly in the notification payload,
        // fetch order items via OrderRepository and pick the unreviewed item
        WidgetsBinding.instance.addPostFrameCallback((_) async {
          try {
            final orderData = await OrderRepository().getOrderDetail(orderSlug: slugValue);
            if (orderData.isNotEmpty && orderData.first.data?.items?.isNotEmpty == true) {
              final items = orderData.first.data!.items!;
              final targetItem = items.firstWhere(
                (item) => item.isUserReviewGiven != true,
                orElse: () => items.first,
              );
              if (targetItem.id != null && targetItem.id! > 0) {
                final itemImg = (targetItem.variant?.image?.isNotEmpty ?? false)
                    ? targetItem.variant!.image
                    : targetItem.product?.image;
                final curContext = GlobalKeys.navigatorKey.currentContext ?? navigatorContext;
                GoRouter.of(curContext).push(
                  AppRoutes.productRating,
                  extra: {
                    'orderItemId': targetItem.id!,
                    'productName': targetItem.title ?? targetItem.product?.name ?? combined['title'] ?? title,
                    'variantTitle': targetItem.variantTitle,
                    'image': itemImg ?? combined['image']?.toString(),
                    'initialRating': 0,
                  },
                );
                return;
              }
            }
          } catch (e) {
            log('Error fetching order for rate_now redirection: $e');
          }

          // Fallback to order detail page if item could not be retrieved
          final curContext = GlobalKeys.navigatorKey.currentContext ?? navigatorContext;
          GoRouter.of(curContext).push(
            AppRoutes.orderItemDetail,
            extra: {
              'order-slug': slugValue,
              'order-item-id': orderItemId,
            },
          );
        });
        return;
      }
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final typeLower = type.toLowerCase();
      if (slugType == 'order_slug' ||
          typeLower == 'order' ||
          typeLower == 'delivery' ||
          typeLower == 'new_order' ||
          typeLower == 'order_update' ||
          typeLower == 'delivered' ||
          typeLower == 'order_delivered' ||
          typeLower == 'return_order' ||
          typeLower == 'return_order_update' ||
          typeLower == 'payment' ||
          combined.containsKey('order_slug') ||
          combined.containsKey('order-slug') ||
          combined.containsKey('orderSlug')) {
        if (slugValue.isNotEmpty) {
          GoRouter.of(navigatorContext).push(
            AppRoutes.orderItemDetail,
            extra: {
              'order-slug': slugValue,
              'order-item-id': orderItemId,
            },
          );
          return;
        }
      }

      if (slugType == 'slug') {
        switch (typeLower) {
          case 'product':
            GoRouter.of(navigatorContext).push(
              AppRoutes.productDetailPage,
              extra: {'productSlug': slugValue},
            );
            break;
          case 'featured_section':
            GoRouter.of(navigatorContext).push(
              AppRoutes.productListing,
              extra: {
                'isTheirMoreCategory': false,
                'title': title,
                'logo': '',
                'totalProduct': 10,
                'type': ProductListingType.featuredSection,
                'identifier': slugValue,
              },
            );
            break;
          case 'category':
            GoRouter.of(navigatorContext).push(
              AppRoutes.productListing,
              extra: {
                'isTheirMoreCategory': false,
                'title': title,
                'logo': data['image']?.toString() ?? '',
                'totalProduct': 10,
                'type': ProductListingType.category,
                'identifier': slugValue,
              },
            );
            break;
          case 'brand':
            GoRouter.of(navigatorContext).push(
              AppRoutes.productListing,
              extra: {
                'isTheirMoreCategory': false,
                'title': title,
                'logo': data['image']?.toString() ?? '',
                'totalProduct': 10,
                'type': ProductListingType.brand,
                'identifier': slugValue,
              },
            );
            break;
          case 'store':
            GoRouter.of(navigatorContext).push(
              AppRoutes.nearbyStoreDetails,
              extra: {
                'store-slug': slugValue,
                'store-name': title,
              },
            );
            break;
          case 'wallet_transaction':
            GoRouter.of(navigatorContext).push(AppRoutes.transactions);
            break;
          case 'offer':
          case 'promotion':
            if (slugValue.isNotEmpty) {
              GoRouter.of(navigatorContext).push(
                AppRoutes.productListing,
                extra: {
                  'isTheirMoreCategory': false,
                  'title': title.isNotEmpty ? title : 'Offers',
                  'logo': data['image']?.toString() ?? '',
                  'totalProduct': 10,
                  'type': ProductListingType.featuredSection,
                  'identifier': slugValue,
                },
              );
            } else {
              GoRouter.of(navigatorContext).push(AppRoutes.home);
            }
            break;
          case 'cart':
            GoRouter.of(navigatorContext).push(AppRoutes.cart);
            break;
        }
      }
    });
  }
}

// Separate function for getting token (kept as is, but improved slightly)
Future<String?> getFCMToken() async {
  try {
    await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: true,
    );

    if (Platform.isIOS) {
      String? apnsToken = await FirebaseMessaging.instance.getAPNSToken();
      int retries = 0;
      while (apnsToken == null && retries < 6) {
        await Future.delayed(const Duration(milliseconds: 500));
        apnsToken = await FirebaseMessaging.instance.getAPNSToken();
        retries++;
      }
    }

    final fcmToken = await FirebaseMessaging.instance.getToken();
    log('FCM Token: $fcmToken');
    return fcmToken;
  } catch (e) {
    log('Error getting FCM token: $e');
    return null;
  }
}
