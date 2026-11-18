import 'package:hyper_commerce_seller/config/constant.dart';

class ApiRoutes {
  // DON"T CHANGE ANY OF THIS URLs
  static String mainUrl = '${AppConstants.domainUrl}/api';
  static String baseUrl = '$mainUrl/seller';

  // ROUTES

  // AUTH
  static String registerApi = '$baseUrl/register';
  static String registrationFieldsApi = '$baseUrl/registration-fields';
  static String loginApi = '$baseUrl/login';
  static String logoutApi = '$baseUrl/logout';
  static String forgetPasswordApi = '$baseUrl/forget-password';
  static String verifyUserApi = '$mainUrl/verify-user';
  static String callbackApi = '$mainUrl/auth/phone/callback';
  static String customSendOtpApi = '$mainUrl/auth/send-otp';
  static String customVerifyOtpApi = '$mainUrl/auth/verify-otp';

  static String loginSendOtpApi = '$baseUrl/login/send-otp';
  static String loginVerifyOtpApi = '$baseUrl/login/verify-otp';
  static String forgetPasswordSendOtpApi = '$baseUrl/forget-password/send-otp';
  static String forgetPasswordVerifyOtpApi =
      '$baseUrl/forget-password/verify-otp';
  static String resetPasswordApi = '$baseUrl/reset-password';

  static String settingsApi = '$mainUrl/settings';
  static String fetchOnboardingPages = '$mainUrl/onboarding-pages';
  static String fetchCustomSplash = '$mainUrl/splash-screen';

  static String categoriesApi = '$mainUrl/categories';
  static String brandsApi = '$baseUrl/brands';
  static String attributesApi = '$baseUrl/attributes';
  static String attributeValueApi = '$baseUrl/attribute-values';
  static String productsApi = '$baseUrl/products';
  static String productFaqsApi = '$baseUrl/product-faqs';
  static String productsEnumsApi = '$productsApi/enums';
  static String productVisibilityApi(int id) => '$productsApi/$id/visibility';
  static String taxGroupsApi = '$baseUrl/tax-classes';
  static String storesApi = '$baseUrl/stores';
  static String storesEnumsApi = '$storesApi/enums';
  static String contextApi = '$baseUrl/context';
  static String switchStoreApi = '$storesApi/switch';
  static String storesWithLocationsApi = '$storesApi/with-locations';
  static String marketsSearchApi = '$baseUrl/markets/search';
  static String currencyConversionApi = '$baseUrl/currency-conversion';

  // store locations (ship-from)
  static String locationsApi = '$baseUrl/locations';
  static String locationDetailApi(int id) => '$locationsApi/$id';
  static String promoteLocationApi(int id) =>
      '$locationsApi/$id/promote-default';
  static String locationShiprocketSyncApi(int id) =>
      '$locationsApi/$id/shiprocket-sync';
  static String geoStatesApi = '$baseUrl/geo/states';
  static String geoCitiesApi = '$baseUrl/geo/cities';
  static String rolesApi = '$baseUrl/roles';
  static String rolesListApi = '$rolesApi/list';
  static String permissionsApi = '$baseUrl/permissions';
  static String systemUsersApi = '$baseUrl/system-users';
  static String notificationsApi = '$baseUrl/notifications';

  static String dashboardDataApi = '$baseUrl/dashboard';
  static String dashboardSummaryApi = '$dashboardDataApi/summary';
  static String dashboardChartApi = '$dashboardDataApi/chart';
  static String ordersApi = '$baseUrl/orders';
  static String ordersEnumsApi = '$ordersApi/enums';

  // order fulfillment / shipments (markets model)
  static String orderItemsApi(int sellerOrderId) =>
      '$ordersApi/$sellerOrderId/items';
  static String orderFulfillmentApi(int sellerOrderId) =>
      '$ordersApi/$sellerOrderId/fulfillment';
  static String orderEarningsApi(int sellerOrderId) =>
      '$ordersApi/$sellerOrderId/earnings';
  static String orderReturnsForOrderApi(int sellerOrderId) =>
      '$ordersApi/$sellerOrderId/returns';
  // cancel-item is keyed by order_item_id (not seller_order_id).
  static String cancelOrderItemApi(int orderItemId) =>
      '$ordersApi/$orderItemId/cancel-item';
  static String fulfillOrderApi(int sellerOrderId) =>
      '$ordersApi/$sellerOrderId/fulfill';
  static String orderFulfillmentStatusApi(int sellerOrderId) =>
      '$ordersApi/$sellerOrderId/fulfillment-status';
  static String restockOrderApi(int sellerOrderId) =>
      '$ordersApi/$sellerOrderId/restock';
  static String shipmentTransitionApi(int shipmentId) =>
      '$ordersApi/shipments/$shipmentId/transition';
  static String shipmentRetryBookingApi(int shipmentId) =>
      '$ordersApi/shipments/$shipmentId/retry-booking';
  static String shipmentUpdateLinesApi(int shipmentId) =>
      '$ordersApi/shipments/$shipmentId/update-lines';
  static String shipmentNdrActionApi(int shipmentId) =>
      '$ordersApi/shipments/$shipmentId/ndr-action';
  static String shipmentSyncApi(int shipmentId) =>
      '$ordersApi/shipments/$shipmentId/sync';
  static String shipmentEditApi(int shipmentId) =>
      '$ordersApi/shipments/$shipmentId/edit';
  static String shipmentPackingSlipApi(int shipmentId) =>
      '$ordersApi/shipments/$shipmentId/packing-slip';

  // returns
  static String orderReturnsApi = '$ordersApi/returns';
  static String orderReturnDetailApi(int returnId) =>
      '$ordersApi/returns/$returnId';
  static String orderReturnApproveApi(int returnId) =>
      '$ordersApi/returns/$returnId/approve';
  static String orderReturnDeclineApi(int returnId) =>
      '$ordersApi/returns/$returnId/decline';

  // wallet / transactions
  static String walletApi = '$baseUrl/wallet';
  static String transactionsApi = '$walletApi/transactions';
  static String withdrawalsApi = '$baseUrl/withdrawals';
  static String withdrawalHistoryApi = '$withdrawalsApi/history';

  // commission
  static String unsettledCreditApi = '$baseUrl/commissions';
  static String unsettledDebitApi = '$unsettledCreditApi/debits';
  static String settledEarningsApi = '$unsettledCreditApi/history';

  // profile
  static String updateUserApi = '$mainUrl/user/profile';
  static String changePasswordApi = '$mainUrl/user/change-password';

  // subscription plans
  static String subscriptionPlansApi = '$mainUrl/subscription/plans';
  static String checkEligibilityApi = '$baseUrl/subscription/check-eligibility';
  static String buySubscriptionPlanApi = '$baseUrl/subscription/buy';
  static String currentSubscriptionPlanApi = '$baseUrl/subscription/current';
  static String subscriptionPlanHistoryApi = '$baseUrl/subscription/history';
  static String deleteProfileApi = '$baseUrl/delete-account';
  static String addOnGroupListApi = "$baseUrl/addon-groups";
  static String productAddOneListApi = "$baseUrl/product-addons";
  static String deleteProductAddOnApi = "$baseUrl/product-addons";
  static String lookupProductsApi = '$baseUrl/product-addons/lookup/products';
  static String lookupVariantsBulkApi =
      '$baseUrl/product-addons/lookup/variants-bulk';
  static String lookupAddonGroupsApi =
      '$baseUrl/product-addons/lookup/addon-groups';
  static String showProductAddonApi = '$baseUrl/product-addons/show';
  static String updateProductAddonApi(int variantId, int groupId) =>
      '$baseUrl/product-addons/$variantId/$groupId';
  static String versionCheckApi = '$mainUrl/settings/check-version';
  static String stockInventoryListApi = "$baseUrl/store-addon-items";
  static String lookupStoresForInventoryApi =
      "$stockInventoryListApi/lookup/stores";
  static String lookupAddonGroupsForInventoryApi =
      "$stockInventoryListApi/lookup/addon-groups";
  static String lookupGroupItemsApi(int groupId) =>
      "$stockInventoryListApi/lookup/groups/$groupId/items";
  static String lookupInventoryStateApi(int storeId, int itemId) =>
      "$stockInventoryListApi/lookup/state?store_id=$storeId&addon_item_id=$itemId";
  static String bulkCreateInventoryApi = "$stockInventoryListApi/bulk";
  static String updateInventoryApi(int id) => "$stockInventoryListApi/$id";

  // ad wallet
  static String adWalletApi = '$baseUrl/ad-wallet';
  static String adWalletTransactionsApi = '$adWalletApi/transactions';
  static String adWalletTopupGatewayApi = '$adWalletApi/topup/gateway';
  static String adWalletTopupEarningsApi = '$adWalletApi/topup/earnings';

  // ad campaigns
  static String adCampaignsApi = '$baseUrl/ad-campaigns';
  static String adCampaignConfigApi = '$adCampaignsApi/config';
  static String adCampaignDetailApi(int id) => '$adCampaignsApi/$id';
  static String adCampaignPauseApi(int id) => '$adCampaignsApi/$id/pause';
  static String adCampaignResumeApi(int id) => '$adCampaignsApi/$id/resume';

  // watch and buy
  static String watchAndBuyProfileApi = '$baseUrl/watch-and-buy/profile';
  static String watchAndBuyReelsApi = '$baseUrl/watch-and-buy/reels';
  static String watchAndBuyReelDetailApi(int id) => '$watchAndBuyReelsApi/$id';
  static String watchAndBuyReelPublishApi(int id) =>
      '$watchAndBuyReelsApi/$id/publish';
  static String watchAndBuyEligibleProductsApi =
      '$baseUrl/watch-and-buy/eligible-products';
  static String watchAndBuyStatusesApi = '$baseUrl/watch-and-buy/statuses';
  static String watchAndBuyStatusDetailApi(int id) =>
      '$watchAndBuyStatusesApi/$id';

  // languages
  static String fetchLanguages = '$mainUrl/languages';
  static String languageLabels = '$mainUrl/language-labels';
}
