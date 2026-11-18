import 'package:hyper_commerce_seller/config/app_permissions.dart';

/// Groups of permissions that all gate the same module.
///
/// Rule for module entry: a user should be able to enter the module (open
/// the list page, see the "+ Create" button, etc.) if they hold **any** of
/// the CRUD-family permissions for that module — not just `view`.
///
/// Example: a user with only `store_location.create` will still be able to
/// open the Store Locations menu tile, land on the list, and tap Create.
/// The list itself will still require `view` to render items (the backend
/// enforces that), but the entry point is no longer a dead end.
///
/// Individual buttons inside the module should keep gating on their own
/// specific permission (e.g. edit button on `edit`, delete on `delete`).
class PermissionGroups {
  const PermissionGroups._();

  static const List<String> role = [
    AppPermissions.roleView,
    AppPermissions.roleCreate,
    AppPermissions.roleEdit,
    AppPermissions.roleDelete,
    AppPermissions.rolePermissionView,
    AppPermissions.rolePermissionEdit,
  ];

  static const List<String> systemUser = [
    AppPermissions.systemUserView,
    AppPermissions.systemUserCreate,
    AppPermissions.systemUserEdit,
    AppPermissions.systemUserDelete,
  ];

  static const List<String> store = [
    AppPermissions.storeView,
    AppPermissions.storeCreate,
    AppPermissions.storeEdit,
    AppPermissions.storeDelete,
  ];

  static const List<String> storeLocation = [
    AppPermissions.storeLocationView,
    AppPermissions.storeLocationCreate,
    AppPermissions.storeLocationEdit,
    AppPermissions.storeLocationDelete,
  ];

  static const List<String> attribute = [
    AppPermissions.attributeView,
    AppPermissions.attributeCreate,
    AppPermissions.attributeEdit,
    AppPermissions.attributeDelete,
  ];

  static const List<String> product = [
    AppPermissions.productView,
    AppPermissions.productCreate,
    AppPermissions.productEdit,
    AppPermissions.productDelete,
  ];

  static const List<String> productFaq = [
    AppPermissions.productFaqView,
    AppPermissions.productFaqCreate,
    AppPermissions.productFaqEdit,
    AppPermissions.productFaqDelete,
  ];

  static const List<String> order = [
    AppPermissions.orderView,
    AppPermissions.orderEdit,
    AppPermissions.orderUpdateStatus,
    AppPermissions.orderCancel,
    AppPermissions.orderRefund,
    AppPermissions.orderFulfil,
  ];

  static const List<String> orderReturn = [
    AppPermissions.returnView,
    AppPermissions.returnDecide,
  ];

  static const List<String> taxRate = [
    AppPermissions.taxRateView,
    AppPermissions.taxRateCreate,
    AppPermissions.taxRateEdit,
    AppPermissions.taxRateDelete,
  ];

  static const List<String> addonGroup = [
    AppPermissions.addonGroupView,
    AppPermissions.addonGroupCreate,
    AppPermissions.addonGroupEdit,
    AppPermissions.addonGroupDelete,
  ];

  static const List<String> productAddon = [
    AppPermissions.productAddonView,
    AppPermissions.productAddonCreate,
    AppPermissions.productAddonEdit,
    AppPermissions.productAddonDelete,
  ];

  static const List<String> storeAddonItem = [
    AppPermissions.storeAddonItemView,
    AppPermissions.storeAddonItemCreate,
    AppPermissions.storeAddonItemEdit,
    AppPermissions.storeAddonItemDelete,
  ];

  static const List<String> notification = [
    AppPermissions.notificationView,
    AppPermissions.notificationCreate,
    AppPermissions.notificationEdit,
    AppPermissions.notificationDelete,
  ];

  static const List<String> productCondition = [
    AppPermissions.productConditionCreate,
    AppPermissions.productConditionEdit,
    AppPermissions.productConditionDelete,
  ];

  static const List<String> adWallet = [
    AppPermissions.adWalletView,
    AppPermissions.adWalletTopup,
  ];

  static const List<String> adCampaign = [
    AppPermissions.adCampaignView,
    AppPermissions.adCampaignCreate,
    AppPermissions.adCampaignEdit,
    AppPermissions.adCampaignDelete,
    AppPermissions.adCampaignPause,
    AppPermissions.adCampaignDashboardView,
  ];

  static const List<String> withdrawal = [
    AppPermissions.withdrawalView,
    AppPermissions.withdrawalRequest,
  ];

  static const List<String> pos = [
    AppPermissions.posView,
    AppPermissions.posCreateOrder,
    AppPermissions.posRefund,
    AppPermissions.posParkSale,
    AppPermissions.posPaymentSession,
  ];

  static const List<String> subscription = [
    AppPermissions.subscriptionView,
    AppPermissions.subscriptionBuy,
  ];

  static const List<String> shipping = [
    AppPermissions.shippingView,
    AppPermissions.shippingEdit,
  ];
}
