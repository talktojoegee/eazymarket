import 'dart:io';
import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/global_keys.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/introduction_pages/view/dynamic_onboarding_page.dart';
import 'package:hyper_commerce_seller/screen/auth/view/forgot_password_page.dart';
import 'package:hyper_commerce_seller/screen/auth/view/login_page.dart';
import 'package:hyper_commerce_seller/screen/auth/view/otp_login_page.dart';
import 'package:hyper_commerce_seller/screen/auth/view/awaiting_approval_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/view/subscription_history_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/bloc/subscription_plans_bloc/subscription_plan_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/model/subscription_plans_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/repo/subscription_plans_repo.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/view/subscription_plan_details_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/view/subscription_payment_webview.dart';
import 'package:hyper_commerce_seller/screen/auth/view/otp_page.dart';
import 'package:hyper_commerce_seller/screen/auth/view/sign_up_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/view/choose_plan_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/more_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/more_group_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/view/attribute_values_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/view/attributes_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/brands/view/brands_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/categories/bloc/categories_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/categories/repo/categories_repo.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/categories/view/categories_page.dart';
import 'package:hyper_commerce_seller/screen/dashboard/dashboard.dart';
import 'package:hyper_commerce_seller/screen/home_page/view/home_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/profile/view/profile_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/view/permission_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/view/roles_and_permissions_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/settings_content_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/view/stores_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/add_store/view/add_store_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/add_store/view/map_screen.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/view/subscription_plans_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/system_users/view/system_users_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/wallet/view/transaction_history_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/wallet/view/wallet_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/wallet/view/withdraw_history_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/wallet/view/withdraw_request_page.dart';
import 'package:hyper_commerce_seller/screen/order_page/view/order_details_page.dart';
import 'package:hyper_commerce_seller/screen/order_page/view/order_page.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/view/add_product_page.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/view/product_details_page.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/view/products_page.dart';
import 'package:hyper_commerce_seller/screen/splash_screen/view/splash_screen.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/tax_group/view/tax_groups_page.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/earning/view/earning_page.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/view/product_faqs_page.dart';
import 'package:hyper_commerce_seller/screen/home_page/view/notification_list_page.dart';

import '../screen/products_page/add_ons/addon_groups/model/addon_group_model.dart';
import '../screen/products_page/add_ons/addon_groups/view/addon_groups_page.dart';
import '../screen/products_page/add_ons/create_add_on_group/view/create_addon_group_page.dart';
import '../screen/more_page/view/store_addon_inventory/store_addon_list/views/store_addon_inventory_page.dart';
import '../screen/products_page/product_addons/create_product_addons/cubit/create_product_addons_cubit.dart';
import '../screen/products_page/product_addons/product_addons_group/model/product_addons_model.dart';
import '../screen/products_page/product_addons/create_product_addons/view/create_product_addons_page.dart';
import '../screen/products_page/product_addons/product_addons_group/view/product_addon_page.dart';
import '../screen/more_page/view/store_addon_inventory/store_addon_list/cubit/add_store_addon_inventory_cubit.dart';
import '../screen/more_page/view/store_addon_inventory/store_addon_list/views/add_store_addon_inventory_page.dart';
import '../screen/more_page/view/campaigns/view/campaign_list_page.dart';
import '../screen/more_page/view/campaigns/view/create_campaign_page.dart';
import '../screen/more_page/view/campaigns/view/campaign_detail_page.dart';
import '../screen/more_page/view/campaigns/view/campaign_wallet_page.dart';
import '../screen/more_page/view/campaigns/view/campaign_product_select_page.dart';
import '../screen/more_page/view/campaigns/view/campaign_payment_webview.dart';
import '../screen/more_page/view/watch_and_buy/bloc/profile/watch_and_buy_profile_bloc.dart';
import '../screen/more_page/view/watch_and_buy/bloc/reels/watch_and_buy_reels_bloc.dart';
import '../screen/more_page/view/watch_and_buy/bloc/status/watch_and_buy_status_bloc.dart';
import '../screen/more_page/view/watch_and_buy/model/watch_and_buy_profile_model.dart';
import '../screen/more_page/view/watch_and_buy/model/watch_and_buy_reel_model.dart';
import '../screen/more_page/view/watch_and_buy/repo/watch_and_buy_repo.dart';
import '../screen/more_page/view/watch_and_buy/view/watch_and_buy_dashboard_page.dart';
import '../screen/more_page/view/watch_and_buy/view/profile/watch_and_buy_profile_page.dart';
import '../screen/more_page/view/watch_and_buy/view/reels/create_reel_page.dart';
import '../screen/more_page/view/watch_and_buy/view/reels/reel_preview_page.dart';
import '../screen/more_page/view/watch_and_buy/view/status/create_status_page.dart';
import '../screen/more_page/view/watch_and_buy/view/status/status_preview_page.dart';
import '../screen/more_page/view/campaigns/bloc/campaign_product_select_bloc/campaign_product_select_bloc.dart';
import '../screen/more_page/view/campaigns/repo/campaign_repo.dart';
import '../screen/more_page/view/store_locations/view/store_locations_page.dart';
import '../screen/more_page/view/store_locations/view/store_location_form_page.dart';
import '../screen/more_page/view/store_locations/model/store_location_model.dart';
import '../screen/order_page/view/order_returns_page.dart';
import '../screen/products_page/products/view/product_visibility_page.dart';

Page platformPage(Widget child) {
  if (Platform.isIOS) {
    return CupertinoPage(child: child);
  } else {
    return MaterialPage(child: child);
  }
}

class AppRoutes {
  static const String splashScreen = '/';
  static const String introSlider = '/intro-slider';
  static const String login = '/login';
  static const String awaitingApproval = '/awaiting-approval';
  static const String signUp = '/signup';
  static const String forgotPassword = '/forgot-password';
  static const String otpLogin = '/otp-login';
  static const String otp = '/otp';
  static const String register = '/register';
  static const String home = '/home';
  static const String orders = '/orders';
  static const String products = '/products';
  static const String productDetails = '/product-details';
  static const String addProduct = '/add-products';
  static const String more = '/more';
  static const String ordersFull = '/orders-full';
  static const String productsFull = '/products-full';
  static const String categories = '/categories';
  static const String brands = '/brands';
  static const String taxGroup = '/tax-group';
  static const String stores = '/stores';
  static const String moreGroup = '/more-group';
  static const String addStore = '/add-store';
  static const String mapScreen = '/map-screen';
  static const String attributes = '/attributes';
  static const String attributeValues = '/attribute-values';
  static const String orderDetails = '/order-details';
  static const String wallet = '/wallet';
  static const String withdrawRequest = '/withdraw-request';
  static const String withdrawHistory = '/withdraw-history';
  static const String transactionHistory = '/transaction-history';
  static const String earnings = '/earnings';
  static const String productFaqs = '/product-faqs';
  static const String storeConfiguration = '/store-configuration';
  static const String profilePage = '/profile';
  static const String privacyPolicy = '/privacy-policy';
  static const String termsCondition = '/terms-condition';
  static const String rolesAndPermission = '/roles-permission';
  static const String permissions = '/permissions';
  static const String systemUsers = '/system-users';
  static const String storeLocations = '/store-locations';
  static const String storeLocationForm = '/store-location-form';
  static const String orderReturns = '/order-returns';
  static const String productVisibility = '/product-visibility';
  static const String notifications = '/notifications';
  static const String subscriptionPlans = '/subscription-plans';
  static const String subscriptionPlanDetails = '/subscription-plan-details';
  static const String subscriptionPaymentWebView = '/subscription-payment-webview';
  static const String subscriptionPlanHistory = '/subscription-plan-history';
  static const String subscriptionChoosePlan = '/subscription-choose-plan';
  static const String addOnGroup = '/add-on-group';
  static const String createAddOnGroup = '/create_add-on-group';
  static const String productAddOn = '/product-add-on';
  static const String createProductAddOn = '/create-product-add-on';
  static const String storeAddonInventory = '/store-addon-inventory';
  static const String createStoreAddonInventory = '/create-store-addon-inventory';

  // campaigns
  static const String campaigns = '/campaigns';
  static const String createCampaign = '/create-campaign';
  static const String campaignDetail = '/campaign-detail';
  static const String campaignWallet = '/campaign-wallet';
  static const String campaignProductSelect = '/campaign-product-select';
  static const String campaignPaymentWebView = '/campaign-payment-webview';
  static const String campaignTopupGateway = '/campaign-topup-gateway';

  // watch & buy
  static const String watchAndBuy = '/watch-and-buy';
  static const String watchAndBuyProfile = '/watch-and-buy/profile';
  static const String createReel = '/watch-and-buy/create-reel';
  static const String createStatus = '/watch-and-buy/create-status';
}

class MyAppRoutes {
  static GoRouter router = GoRouter(
    debugLogDiagnostics: true,
    initialLocation: AppRoutes.splashScreen,
    navigatorKey: GlobalKeys.navigatorKey,

    onException: (context, state, router) {
      // Ignore Firebase Auth redirects (reCAPTCHA flow) so we stay on the current page
      if (state.uri.toString().contains('firebaseauth/link')) {
        return;
      }
      // For any other routing errors, go to home or show a simple error
      debugPrint('Routing error: ${state.error}');
      router.go(AppRoutes.splashScreen);
    },
    routes: [
      GoRoute(path: AppRoutes.splashScreen, name: '/', pageBuilder: (context, state) => platformPage(SplashScreen())),
      GoRoute(
        path: AppRoutes.introSlider,
        name: '/intro-slider',
        redirect: (context, state) {
          if ((!HiveStorage.isFirstTime && !kDebugMode) ||
              !HiveStorage.isOnboardingEnabled) {
            final token = HiveStorage.userToken;
            return (token != null && token.isNotEmpty)
                ? AppRoutes.home
                : AppRoutes.login;
          }
          return null;
        },
        pageBuilder: (context, state) =>
            platformPage(const DynamicOnBoardingPage()),
      ),
      GoRoute(path: AppRoutes.login, name: '/login', pageBuilder: (context, state) => platformPage(const LoginPage())),
      GoRoute(
        path: AppRoutes.awaitingApproval,
        name: '/awaiting-approval',
        pageBuilder: (context, state) => platformPage(const AwaitingApprovalPage()),
      ),
      GoRoute(
        path: AppRoutes.signUp,
        name: '/signup',
        pageBuilder: (context, state) => platformPage(const SignUpPage()),
      ),
      GoRoute(
        path: AppRoutes.forgotPassword,
        name: '/forgot-password',
        pageBuilder: (context, state) => platformPage(const ForgotPasswordPage()),
      ),
      GoRoute(
        path: AppRoutes.otpLogin,
        name: '/otp-login',
        pageBuilder: (context, state) => platformPage(const OtpLoginPage()),
      ),
      GoRoute(
        path: AppRoutes.otp,
        name: '/otp',
        pageBuilder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          return platformPage(
            OtpPage(verificationId: extra?['verificationId'] as String?, phoneNumber: extra?['phoneNumber'] as String?),
          );
        },
      ),

      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return Dashboard(index: navigationShell.currentIndex, navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(path: AppRoutes.home, name: '/home', pageBuilder: (context, state) => platformPage(HomePage())),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.orders,
                name: '/order',
                pageBuilder: (context, state) => platformPage(BackToHomeWrapper(child: OrderPage())),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.products,
                name: '/products',
                pageBuilder: (context, state) => platformPage(BackToHomeWrapper(child: ProductsPage())),
              ),
            ],
          ),

          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.more,
                name: '/more',
                pageBuilder: (context, state) => platformPage(BackToHomeWrapper(child: MorePage())),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.addProduct,
        name: '/add-products',
        pageBuilder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          final isEdit = extra?['is_edit'] as bool? ?? false;
          return platformPage(AddProductPage(isEdit: isEdit));
        },
      ),
      GoRoute(
        path: AppRoutes.categories,
        name: '/categories',
        pageBuilder: (context, state) {
          final slug = state.uri.queryParameters['slug'];
          return platformPage(
            BlocProvider(
              create: (context) => CategoriesBloc(context.read<CategoriesRepo>()),
              child: CategoriesPage(categorySlug: slug),
            ),
          );
        },
      ),
      GoRoute(
        path: AppRoutes.brands,
        name: '/brands',
        pageBuilder: (context, state) => platformPage(const BrandsPage()),
      ),
      GoRoute(
        path: AppRoutes.taxGroup,
        name: '/tax-group',
        pageBuilder: (context, state) => platformPage(const TaxGroupsPage()),
      ),
      GoRoute(
        path: AppRoutes.stores,
        name: '/stores',
        pageBuilder: (context, state) => platformPage(const StoresPage()),
      ),
      GoRoute(
        path: AppRoutes.moreGroup,
        name: '/more-group',
        pageBuilder: (context, state) {
          // `extra` is the group id from MoreGroups.
          final groupId = state.extra is String ? state.extra as String : '';
          return platformPage(MoreGroupPage(groupId: groupId));
        },
      ),
      GoRoute(
        path: AppRoutes.addStore,
        name: '/add-store',
        pageBuilder: (context, state) => platformPage(const AddStorePage()),
      ),
      GoRoute(
        path: AppRoutes.mapScreen,
        name: '/map-screen',
        pageBuilder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          return platformPage(MapScreen(initialLatitude: extra?['latitude'], initialLongitude: extra?['longitude']));
        },
      ),
      GoRoute(
        path: AppRoutes.attributes,
        name: '/attributes',
        pageBuilder: (context, state) => platformPage(const AttributesPage()),
      ),
      GoRoute(
        path: AppRoutes.attributeValues,
        name: '/attribute-values',
        pageBuilder: (context, state) {
          final attribute = state.extra as dynamic;
          return platformPage(AttributeValuesPage(attribute: attribute));
        },
      ),
      GoRoute(
        path: AppRoutes.productDetails,
        name: '/product-details',
        pageBuilder: (context, state) {
          // `extra` carries the product id — the page fetches the record itself.
          final extra = state.extra;
          final productId = extra is int
              ? extra
              : int.tryParse('$extra') ?? 0;
          return platformPage(ProductDetailsPage(productId: productId));
        },
      ),
      GoRoute(
        path: '${AppRoutes.orderDetails}/:id',
        name: '/order-details',
        pageBuilder: (context, state) {
          final idString = state.pathParameters['id'];
          final id = int.tryParse(idString ?? '') ?? 0;
          // Optional `?tab=` query — accepts an index (0/1/2) or the friendly
          // names 'returns' / 'earnings' so callers don't need to remember
          // the tab order.
          final tabParam = state.uri.queryParameters['tab'] ?? '';
          int initialTab = int.tryParse(tabParam) ?? -1;
          if (initialTab < 0) {
            switch (tabParam.toLowerCase()) {
              case 'returns':
              case 'refunds':
              case 'return':
                initialTab = 1;
                break;
              case 'earnings':
              case 'settlement':
                initialTab = 2;
                break;
              default:
                initialTab = 0;
            }
          }
          return platformPage(OrderDetailsPage(
            orderId: id,
            initialTabIndex: initialTab,
          ));
        },
      ),
      GoRoute(
        path: AppRoutes.wallet,
        name: '/wallet',
        pageBuilder: (context, state) => platformPage(const WalletPage()),
      ),
      GoRoute(
        path: AppRoutes.withdrawRequest,
        name: '/withdraw-request',
        pageBuilder: (context, state) => platformPage(const WithdrawRequestPage()),
      ),
      GoRoute(
        path: AppRoutes.withdrawHistory,
        name: '/withdraw-history',
        pageBuilder: (context, state) => platformPage(const WithdrawHistoryPage()),
      ),
      GoRoute(
        path: AppRoutes.transactionHistory,
        name: '/transaction-history',
        pageBuilder: (context, state) => platformPage(const TransactionHistoryPage()),
      ),
      GoRoute(
        path: AppRoutes.earnings,
        name: '/earnings',
        pageBuilder: (context, state) => platformPage(const EarningPage()),
      ),
      GoRoute(
        path: AppRoutes.productFaqs,
        name: '/product-faqs',
        pageBuilder: (context, state) {
          final productIdStr = state.uri.queryParameters['productId'];
          final productId = int.tryParse(productIdStr ?? '');
          return platformPage(ProductFaqsPage(productId: productId));
        },
      ),
      GoRoute(
        path: AppRoutes.profilePage,
        name: '/profile',
        pageBuilder: (context, state) => platformPage(const ProfilePage()),
      ),
      GoRoute(
        path: AppRoutes.ordersFull,
        name: '/orders-full',
        pageBuilder: (context, state) => platformPage(OrderPage()),
      ),
      GoRoute(
        path: AppRoutes.productsFull,
        name: '/products-full',
        pageBuilder: (context, state) => platformPage(ProductsPage()),
      ),

      GoRoute(
        path: AppRoutes.addOnGroup,
        name: '/add-on-group',
        pageBuilder: (context, state) => platformPage(AddonGroupsPage()),
      ),

      GoRoute(
        path: AppRoutes.productAddOn,
        name: '/product-add-on',
        pageBuilder: (context, state) => platformPage(ProductAddonPage()),
      ),
      GoRoute(
        path: AppRoutes.createProductAddOn,
        name: '/create-product-add-on',
        pageBuilder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          final model = extra?['model'] as ProductAddon?;
          return platformPage(
            BlocProvider(
              create: (context) => CreateProductAddonsCubit(),
              child: CreateProductAddonsPage(editModel: model),
            ),
          );
        },
      ),

      GoRoute(
        path: AppRoutes.storeAddonInventory,
        name: '/store-addon-inventory',
        pageBuilder: (context, state) => platformPage(const StoreAddonInventoryPage()),
      ),
      GoRoute(
        path: AppRoutes.createStoreAddonInventory,
        name: '/create-store-addon-inventory',
        pageBuilder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          final inventoryId = extra?['id'] as int?;
          return platformPage(
            BlocProvider(
              create: (context) => AddStoreAddonInventoryCubit()..init(editId: inventoryId),
              child: AddStoreAddonInventoryPage(inventoryId: inventoryId),
            ),
          );
        },
      ),
      GoRoute(
        path: AppRoutes.createAddOnGroup,
        name: '/create_add-on-group',
        pageBuilder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          final model = extra?['model'] as AddonGroup?;
          return platformPage(CreateAddonGroupPage(addonGroup: model));
        },
      ),

      GoRoute(
        path: AppRoutes.rolesAndPermission,
        name: '/roles-permission',
        pageBuilder: (context, state) => platformPage(RolesAndPermissionPage()),
      ),
      GoRoute(
        path: AppRoutes.permissions,
        name: '/permissions',
        pageBuilder: (context, state) {
          final role = state.uri.queryParameters['role'] ?? '';
          final roleIdStr = state.uri.queryParameters['roleId'] ?? '0';
          final roleId = int.tryParse(roleIdStr) ?? 0;
          return platformPage(PermissionPage(role: role, roleId: roleId));
        },
      ),
      GoRoute(
        path: AppRoutes.systemUsers,
        name: '/system-users',
        pageBuilder: (context, state) => platformPage(SystemUsersPage()),
      ),
      GoRoute(
        path: AppRoutes.storeLocations,
        name: '/store-locations',
        pageBuilder: (context, state) => platformPage(const StoreLocationsPage()),
      ),
      GoRoute(
        path: AppRoutes.storeLocationForm,
        name: '/store-location-form',
        pageBuilder: (context, state) =>
            platformPage(StoreLocationFormPage(location: state.extra as StoreLocation?)),
      ),
      GoRoute(
        path: AppRoutes.orderReturns,
        name: '/order-returns',
        pageBuilder: (context, state) => platformPage(const OrderReturnsPage()),
      ),
      GoRoute(
        path: AppRoutes.productVisibility,
        name: '/product-visibility',
        pageBuilder: (context, state) =>
            platformPage(ProductVisibilityPage(productId: state.extra as int)),
      ),
      GoRoute(
        path: AppRoutes.notifications,
        name: '/notifications',
        pageBuilder: (context, state) => platformPage(const NotificationListPage()),
      ),
      GoRoute(
        path: AppRoutes.privacyPolicy,
        name: '/privacy-policy',
        pageBuilder: (context, state) =>
            platformPage(SettingsContentPage(title: 'Privacy Policy', htmlContent: HiveStorage.privacyPolicy)),
      ),
      GoRoute(
        path: AppRoutes.termsCondition,
        name: '/terms-condition',
        pageBuilder: (context, state) =>
            platformPage(SettingsContentPage(title: 'Terms & Condition', htmlContent: HiveStorage.termsCondition)),
      ),
      GoRoute(
        path: AppRoutes.subscriptionPlans,
        name: '/subscription-plans',
        pageBuilder: (context, state) => platformPage(const SubscriptionPlansPage()),
      ),
      GoRoute(
        path: AppRoutes.subscriptionPlanDetails,
        name: '/subscription-plan-details',
        pageBuilder: (context, state) {
          final plan = state.extra as SubscriptionPlan;
          return platformPage(SubscriptionPlanDetailsPage(plan: plan));
        },
      ),
      GoRoute(
        path: AppRoutes.subscriptionPaymentWebView,
        name: '/subscription-payment-webview',
        pageBuilder: (context, state) {
          final url = state.extra as String;
          return platformPage(SubscriptionPaymentWebView(url: url));
        },
      ),
      GoRoute(
        path: AppRoutes.subscriptionPlanHistory,
        name: '/subscription-plan-history',
        pageBuilder: (context, state) => platformPage(const SubscriptionHistoryPage()),
      ),
      GoRoute(
        path: AppRoutes.subscriptionChoosePlan,
        name: '/subscription-choose-plan',
        pageBuilder: (context, state) => platformPage(const ChoosePlanPage()),
      ),

      // Campaign routes
      GoRoute(
        path: AppRoutes.campaigns,
        name: '/campaigns',
        pageBuilder: (context, state) => platformPage(const CampaignListPage()),
      ),
      GoRoute(
        path: AppRoutes.createCampaign,
        name: '/create-campaign',
        pageBuilder: (context, state) => platformPage(const CreateCampaignPage()),
      ),
      GoRoute(
        path: '${AppRoutes.campaignDetail}/:id',
        name: '/campaign-detail',
        pageBuilder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return platformPage(CampaignDetailPage(campaignId: id));
        },
      ),
      GoRoute(
        path: AppRoutes.campaignWallet,
        name: '/campaign-wallet',
        pageBuilder: (context, state) => platformPage(const CampaignWalletPage()),
      ),
      GoRoute(
        path: AppRoutes.campaignProductSelect,
        name: '/campaign-product-select',
        pageBuilder: (context, state) => platformPage(
          BlocProvider(
            create: (_) => CampaignProductSelectBloc(CampaignRepository()),
            child: const CampaignProductSelectPage(),
          ),
        ),
      ),
      GoRoute(
        path: AppRoutes.campaignPaymentWebView,
        name: '/campaign-payment-webview',
        pageBuilder: (context, state) {
          final url = state.extra as String; 
          return platformPage(CampaignPaymentWebView(url: url));
        },
      ),

      // Watch & Buy routes
      GoRoute(
        path: AppRoutes.watchAndBuy,
        name: '/watch-and-buy',
        pageBuilder: (context, state) => platformPage(
          MultiBlocProvider(
            providers: [
              BlocProvider(
                create: (_) => WatchAndBuyProfileBloc(repo: WatchAndBuyRepo())
                  ..add(const FetchProfileEvent()),
              ),
              BlocProvider(
                create: (_) => WatchAndBuyReelsBloc(repo: WatchAndBuyRepo())
                  ..add(const FetchReelsEvent()),
              ),
              BlocProvider(
                create: (_) => WatchAndBuyStatusBloc(repo: WatchAndBuyRepo())
                  ..add(const FetchStatusesEvent()),
              ),
            ],
            child: const WatchAndBuyDashboardPage(),
          ),
        ),
      ),
      GoRoute(
        path: AppRoutes.watchAndBuyProfile,
        name: '/watch-and-buy/profile',
        pageBuilder: (context, state) {
          final profile = state.extra as WatchAndBuyProfileModel?;
          return platformPage(
            BlocProvider(
              create: (_) => WatchAndBuyProfileBloc(repo: WatchAndBuyRepo()),
              child: WatchAndBuyProfilePage(initialProfile: profile),
            ),
          );
        },
      ),
      GoRoute(
        path: AppRoutes.createReel,
        name: '/watch-and-buy/create-reel',
        pageBuilder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          return platformPage(
            CreateReelPage(
              reelId: extra?['reelId'] as int?,
              profileId: extra?['profileId'] as int?,
            ),
          );
        },
      ),
      GoRoute(
        path: AppRoutes.createStatus,
        name: '/watch-and-buy/create-status',
        pageBuilder: (context, state) {
          final profileId = state.extra as int?;
          return platformPage(
            CreateStatusPage(profileId: profileId),
          );
        },
      ),
    ],
  );
}

class BackToHomeWrapper extends StatelessWidget {
  const BackToHomeWrapper({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (didPop) return;
        context.go(AppRoutes.home);
      },
      child: child,
    );
  }
}
