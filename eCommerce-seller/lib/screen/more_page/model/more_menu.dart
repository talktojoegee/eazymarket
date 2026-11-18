import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/bloc/language/language_cubit.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/config/permission_groups.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/more_page/widgets/custom_section.dart';
import 'package:hyper_commerce_seller/service/external_link.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_selection_sheet.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/language_bottom_sheet.dart';

/// One destination on the Account hub. Opening it pushes [MoreGroupPage],
/// which renders [tiles] with the same widgets the flat list used.
class MoreGroup {
  final String id;
  final String title;
  final String subtitle;
  final IconData icon;
  final List<SettingsTileData> tiles;

  const MoreGroup({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.tiles,
  });
}

/// Group ids — used as the route's `extra`.
class MoreGroups {
  static const String catalogue = 'catalogue';
  static const String stores = 'stores';
  static const String finance = 'finance';
  static const String plan = 'plan';
  static const String team = 'team';
  static const String settings = 'settings';
}

MoreGroup? findMoreGroup(BuildContext context, String id) {
  for (final group in buildMoreGroups(context)) {
    if (group.id == id) return group;
  }
  return null;
}


List<MoreGroup> buildMoreGroups(BuildContext context) {

  void denied(String message) => showCustomSnackbar(
        context: context,
        message: message,
        isWarning: true,
      );

  /// Runs [go] when the permission is held, otherwise explains why not.
  VoidCallback guarded(String permission, String message, VoidCallback go) {
    return () {
      if (!PermissionChecker.hasPermission(permission)) {
        denied(message);
        return;
      }
      go();
    };
  }

  /// Module-entry gate. Opens the module if the user holds *any* of the
  /// supplied permissions — so someone with only `create` can still land
  /// on the list to hit the "+ Create" button. Individual action buttons
  /// inside the module keep their own per-permission gates.
  VoidCallback guardedAny(
    List<String> permissions,
    String message,
    VoidCallback go,
  ) {
    return () {
      if (!PermissionChecker.hasAny(permissions)) {
        denied(message);
        return;
      }
      go();
    };
  }

  final groups = <MoreGroup>[
    MoreGroup(
      id: MoreGroups.catalogue,
      title: context.tr('manageListing', fallback: 'Manage Listing'),
      subtitle: context.tr('moreCatalogueSubtitle', fallback: 'Categories, brands, attributes and add-ons'),
      icon: Icons.category_outlined,
      tiles: [
        SettingsTileData(
          img: ImagesPath.categoriesSvg,
          title: context.tr('categories', fallback: 'Categories'),
          onTap: guarded(
            AppPermissions.categoryView,
            context.tr('noPermissionViewCategories', fallback: 'You do not have permission to view categories.'),
            () => context.pushNamed(AppRoutes.categories),
          ),
        ),
        SettingsTileData(
          img: ImagesPath.brandSvg,
          title: context.tr('brands', fallback: 'Brands'),
          onTap: guarded(
            AppPermissions.brandView,
            context.tr('noPermissionViewBrands', fallback: 'You do not have permission to view brands.'),
            () => context.pushNamed(AppRoutes.brands),
          ),
        ),
        SettingsTileData(
          img: ImagesPath.attributesSvg,
          title: context.tr('attributes', fallback: 'Attributes'),
          onTap: guardedAny(
            PermissionGroups.attribute,
            context.tr('noPermissionViewAttributes', fallback: 'You do not have permission to view attributes.'),
            () => context.pushNamed(AppRoutes.attributes),
          ),
        ),
        SettingsTileData(
          img: ImagesPath.faqSvg,
          title: context.tr('productFaqs', fallback: 'Product FAQs'),
          onTap: guardedAny(
            PermissionGroups.productFaq,
            context.tr('noPermissionViewProductFAQs', fallback: 'You do not have permission to view product FAQs.'),
            () => context.pushNamed(AppRoutes.productFaqs),
          ),
        ),
        // These two used to sit inside the "Manage Product" bottom sheet,
        // which had no other entry point.
        SettingsTileData(
          img: ImagesPath.productSvg,
          title: context.tr('addOnGroup', fallback: 'Add-on Groups'),
          onTap: guardedAny(
            PermissionGroups.addonGroup,
            context.tr('noPermissionViewProducts', fallback: 'You do not have permission to view add-on groups.'),
            () => context.pushNamed(AppRoutes.addOnGroup),
          ),
        ),
        SettingsTileData(
          img: ImagesPath.productSvg,
          title: context.tr('productAdsON', fallback: 'Product Add-ons'),
          onTap: guardedAny(
            PermissionGroups.productAddon,
            context.tr('noPermissionViewProducts', fallback: 'You do not have permission to view product add-ons.'),
            () => context.pushNamed(AppRoutes.productAddOn),
          ),
        ),
        SettingsTileData(
          img: ImagesPath.inventory,
          title: context.tr('storeAddOnInventory', fallback: 'Store Add-on Inventory'),
          onTap: guardedAny(
            PermissionGroups.storeAddonItem,
            context.tr('noPermissionViewProducts', fallback: 'You do not have permission to view store add-on inventory.'),
            () => context.pushNamed(AppRoutes.storeAddonInventory),
          ),
        ),
        if (HiveStorage.watchAndBuySettings.enabled)
          SettingsTileData(
            img: '',
            icon: Icons.video_collection_outlined,
            title: HiveStorage.watchAndBuySettings.title,
            onTap: () => context.pushNamed(AppRoutes.watchAndBuy),
          ),
      ],
    ),

    MoreGroup(
      id: MoreGroups.stores,
      title: context.tr('moreStores', fallback: 'Stores & locations'),
      subtitle:
          context.tr('moreStoresSubtitle', fallback: 'Storefronts, ship-from locations and POS'),
      icon: Icons.storefront_outlined,
      tiles: [
        SettingsTileData(
          img: ImagesPath.storeSvg,
          title: context.tr('stores', fallback: 'Stores'),
          onTap: guardedAny(
            PermissionGroups.store,
            context.tr('noPermissionViewStores', fallback: 'You do not have permission to view stores.'),
            () => context.pushNamed(AppRoutes.stores),
          ),
        ),
        SettingsTileData(
          img: ImagesPath.storeSvg,
          title: context.tr('storeLocationsTitle', fallback: 'Store Locations'),
          onTap: guardedAny(
            PermissionGroups.storeLocation,
            context.tr('noPermissionViewStores', fallback: 'You do not have permission to view store locations.'),
            () => context.pushNamed(AppRoutes.storeLocations),
          ),
        ),
        if (AppConstants.posEnabled)
          SettingsTileData(
            img: ImagesPath.storeSvg,
            title: context.tr('usePOS', fallback: 'Use POS'),
            onTap: guardedAny(
              PermissionGroups.pos,
              context.tr('noPermissionViewPOS', fallback: 'You do not have permission to access POS.'),
              () => ExternalLink.toPosDashboard(context),
            ),
          ),
        SettingsTileData(
          img: '',
          icon: Icons.local_shipping_outlined,
          title: 'Shipping & Delivery',
          onTap: guardedAny(
            PermissionGroups.shipping,
            'You do not have permission to view shipping & delivery.',
            () => ExternalLink.toShippingDashboard(context),
          ),
        ),
      ],
    ),

    MoreGroup(
      id: MoreGroups.finance,
      title: context.tr('finance', fallback: 'Finance'),
      subtitle: context.tr('moreFinanceSubtitle', fallback: 'Wallet, earnings and tax groups'),
      icon: Icons.account_balance_wallet_outlined,
      tiles: [
        SettingsTileData(
          img: ImagesPath.walletSvg,
          title: context.tr('wallet', fallback: 'Wallet'),
          onTap: guarded(
            AppPermissions.walletView,
            context.tr('noPermissionViewWallet', fallback: 'You do not have permission to view your wallet.'),
            () => context.pushNamed(AppRoutes.wallet),
          ),
        ),
        SettingsTileData(
          img: ImagesPath.earningsSvg,
          title: context.tr('earnings', fallback: 'Earnings'),
          onTap: guarded(
            AppPermissions.earningView,
            context.tr('noPermissionViewEarnings', fallback: 'You do not have permission to view earnings.'),
            () => context.pushNamed(AppRoutes.earnings),
          ),
        ),
        if (AppConstants.adsEnabled)
          SettingsTileData(
            img: ImagesPath.earningsSvg,
            title: context.tr('myCampaigns', fallback: 'My Campaigns'),
            onTap: () => context.pushNamed(AppRoutes.campaigns),
          ),
        SettingsTileData(
          img: ImagesPath.taxGroupSvg,
          title: context.tr('taxGroup', fallback: 'Tax Group'),
          onTap: guardedAny(
            PermissionGroups.taxRate,
            context.tr('noPermissionViewTaxRates', fallback: 'You do not have permission to view tax rates.'),
            () => context.pushNamed(AppRoutes.taxGroup),
          ),
        ),
      ],
    ),

    MoreGroup(
      id: MoreGroups.plan,
      title: context.tr('morePlan', fallback: 'Plan & billing'),
      subtitle:
          context.tr('morePlanSubtitle', fallback: 'Subscription and payment history'),
      icon: Icons.workspace_premium_outlined,
      tiles: [
        if (HiveStorage.isSubscriptionAvailable) ...[
          SettingsTileData(
            img: ImagesPath.subscriptionSvg,
            title: context.tr('subscriptionPlansTitle', fallback: 'Subscription Plans'),
            onTap: guardedAny(
              PermissionGroups.subscription,
              'You do not have permission to view subscription plans.',
              () => context.pushNamed(AppRoutes.subscriptionPlans),
            ),
          ),
          SettingsTileData(
            img: ImagesPath.mySubscriptionSvg,
            title: context.tr('mySubscription', fallback: 'My Subscription'),
            onTap: guardedAny(
              PermissionGroups.subscription,
              'You do not have permission to view subscription plans.',
              () => context.pushNamed(AppRoutes.subscriptionPlanHistory),
            ),
          ),
        ],
      ],
    ),

    MoreGroup(
      id: MoreGroups.team,
      title: context.tr('moreTeam', fallback: 'Team & access'),
      subtitle: context.tr('moreTeamSubtitle', fallback: 'Roles and system users'),
      icon: Icons.group_outlined,
      tiles: [
        SettingsTileData(
          img: ImagesPath.rolesAndPermissionSvg,
          title: context.tr('rolesAndPermissions', fallback: 'Roles & Permissions'),
          onTap: guardedAny(
            PermissionGroups.role,
            context.tr('noPermissionViewRolesAndPermissions', fallback: 'You do not have permission to view roles and permissions.'),
            () => context.pushNamed(AppRoutes.rolesAndPermission),
          ),
        ),
        SettingsTileData(
          img: ImagesPath.systemUsersSvg,
          title: context.tr('systemUsers', fallback: 'System Users'),
          onTap: guardedAny(
            PermissionGroups.systemUser,
            'You do not have permission to view system users.',
            () => context.pushNamed(AppRoutes.systemUsers),
          ),
        ),
      ],
    ),

    MoreGroup(
      id: MoreGroups.settings,
      title: context.tr('moreSettings', fallback: 'App settings'),
      subtitle: context.tr('moreSettingsSubtitle', fallback: 'Language and legal'),
      icon: Icons.settings_outlined,
      tiles: [
        SettingsTileData(
          img: ImagesPath.languageSvg,
          title: context.tr('language', fallback: 'Language'),
          onTap: () => LanguageBottomSheet.show(context),
        ),
        SettingsTileData(
          img: ImagesPath.privacyPolicySvg,
          title: context.tr('privacyPolicy', fallback: 'Privacy Policy'),
          onTap: () => context.pushNamed(AppRoutes.privacyPolicy),
        ),
        SettingsTileData(
          img: ImagesPath.termsAndConditionsSvg,
          title: context.tr('termsAndConditions', fallback: 'Terms & Condition'),
          onTap: () => context.pushNamed(AppRoutes.termsCondition),
        ),
      ],
    ),
  ];

  return groups.where((g) => g.tiles.isNotEmpty).toList();
}
