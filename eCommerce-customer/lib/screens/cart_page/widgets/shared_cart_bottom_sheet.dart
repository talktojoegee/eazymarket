import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_tabler_icons/flutter_tabler_icons.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/bloc/user_cart_bloc/user_cart_bloc.dart';
import 'package:hyper_commerce/bloc/user_cart_bloc/user_cart_event.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/global.dart';
import 'package:hyper_commerce/config/global_keys.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:hyper_commerce/screens/cart_page/bloc/get_user_cart/get_user_cart_bloc.dart';
import 'package:hyper_commerce/screens/cart_page/model/shared_cart_response_model.dart';
import 'package:hyper_commerce/screens/cart_page/repo/cart_repository.dart';
import 'package:hyper_commerce/services/shared_cart_coordinator.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:hyper_commerce/utils/widgets/custom_button.dart';
import 'package:hyper_commerce/utils/widgets/custom_circular_progress_indicator.dart';
import 'package:hyper_commerce/utils/widgets/custom_image_container.dart';
import 'package:hyper_commerce/utils/widgets/custom_toast.dart';

class SharedCartBottomSheet extends StatefulWidget {
  final String token;
  final SharedCartResponseModel? initialData;

  const SharedCartBottomSheet({
    super.key,
    required this.token,
    this.initialData,
  });

  static bool _isShowing = false;
  static bool get isShowing => _isShowing;

  static Future<void> show(
    BuildContext context, {
    required String token,
    SharedCartResponseModel? initialData,
  }) async {
    if (_isShowing) return;
    _isShowing = true;

    final effectiveContext =
        GlobalKeys.navigatorKey.currentState?.overlay?.context ??
            (context.mounted
                ? context
                : (GlobalKeys.navigatorKey.currentContext ?? context));

    try {
      await showModalBottomSheet(
        context: effectiveContext,
        backgroundColor: Colors.transparent,
        isScrollControlled: true,
        useRootNavigator: false,
        builder: (sheetContext) => Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: EdgeInsets.symmetric(vertical: 16.h),
              child: Center(
                child: GestureDetector(
                  onTap: () => Navigator.of(sheetContext).pop(),
                  child: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Theme.of(sheetContext).colorScheme.surface,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.12),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: const Icon(Icons.close, size: 20),
                  ),
                ),
              ),
            ),
            SharedCartBottomSheet(
              token: token,
              initialData: initialData,
            ),
          ],
        ),
      );
    } catch (e, stack) {
      debugPrint('[SharedCartBottomSheet] Failed to show sheet: $e\n$stack');
    } finally {
      _isShowing = false;
    }
  }

  @override
  State<SharedCartBottomSheet> createState() => _SharedCartBottomSheetState();
}

class _SharedCartBottomSheetState extends State<SharedCartBottomSheet> {
  final CartRepository _cartRepository = CartRepository();

  SharedCartResponseModel? _data;
  bool _isLoading = false;
  String? _errorMessage;
  bool _isImporting = false;

  bool get _isAuthenticated {
    final token = Global.token ?? Global.userData?.token;
    return token != null && token.isNotEmpty;
  }

  @override
  void initState() {
    super.initState();
    if (widget.initialData != null) {
      _data = widget.initialData;
    } else if (_isAuthenticated) {
      _fetchSharedList();
    }
  }

  Future<void> _fetchSharedList() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await _cartRepository.fetchSharedList(widget.token);
      if (mounted) {
        setState(() {
          _data = response;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  void _navigateToLogin() {
    SharedCartCoordinator.setPending(widget.token);
    Navigator.of(context).pop();
    context.push(AppRoutes.login);
  }

  Future<void> _handleImportAction() async {
    // 1. Guest Handling: Prompt login and retain pending cart code
    if (!_isAuthenticated) {
      _navigateToLogin();
      return;
    }

    // 2. Authenticated Execution
    setState(() {
      _isImporting = true;
    });

    try {
      final importResult = await _cartRepository.importSharedList(
        widget.token,
        action: 'merge',
      );

      if (mounted) {
        // Refresh server cart state and local Hive cache
        try {
          context.read<GetUserCartBloc>().add(FetchUserCart(isRefresh: false));
        } catch (_) {}
        try {
          context.read<CartBloc>().add(LoadCart());
        } catch (_) {}

        // Clear coordinator since import completed
        SharedCartCoordinator.clear();

        final failedItems = importResult.data?.failedItems ?? const [];
        if (failedItems.isNotEmpty) {
          ToastManager.show(
            context: context,
            message: context.tr(
              'someItemsOutOfStock',
              fallback:
                  '${failedItems.length} items were out of stock and skipped.',
            ),
            type: ToastType.warning,
          );
        } else {
          final String successMessage = importResult.message ??
              context.tr('sharedCartAddedSuccessfully',
                  fallback: 'Shared cart items added to your cart.');

          ToastManager.show(
            context: context,
            message: successMessage,
            type: ToastType.success,
          );
        }

        Navigator.of(context).pop();

        if (context.mounted) {
          try {
            final currentLoc = GoRouterState.of(context).matchedLocation;
            if (currentLoc != AppRoutes.cart) {
              context.push(AppRoutes.cart);
            }
          } catch (_) {
            context.push(AppRoutes.cart);
          }
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isImporting = false;
        });
        ToastManager.show(
          context: context,
          message: e.toString(),
          type: ToastType.error,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = _data?.data?.items ?? [];
    final store = _data?.data?.store ?? (items.isNotEmpty ? items.first.store : null);
    final String? storeName = store?.name;
    final String? storeImage = store?.image;
    final bool hasItems = items.isNotEmpty;

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(16.r),
          topRight: Radius.circular(16.r),
        ),
      ),
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.80,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Header
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
            child: Row(
              children: [
                if (storeImage != null && storeImage.isNotEmpty) ...[
                  CustomImageContainer(
                    imagePath: storeImage,
                    width: 28.w,
                    height: 28.w,
                    borderRadius: BorderRadius.circular(14.r),
                    fit: BoxFit.cover,
                  ),
                  SizedBox(width: 8.w),
                ] else if (storeName != null && storeName.isNotEmpty) ...[
                  CircleAvatar(
                    radius: 14.r,
                    backgroundColor: AppTheme.primaryColor.withValues(alpha: 0.12),
                    child: Icon(
                      TablerIcons.building_store,
                      size: 15.sp,
                      color: AppTheme.primaryColor,
                    ),
                  ),
                  SizedBox(width: 8.w),
                ],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        context.tr('sharedCart', fallback: 'Shared Cart'),
                        style: AppTextStyles.titleMedium.copyWith(
                          fontSize: 16.sp,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (storeName != null && storeName.isNotEmpty)
                        Text(
                          storeName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.caption.copyWith(
                            color: Colors.grey.shade600,
                            fontSize: 11.sp,
                          ),
                        ),
                    ],
                  ),
                ),
                if (hasItems)
                  Container(
                    padding:
                        EdgeInsets.symmetric(horizontal: 8.w, vertical: 3.h),
                    decoration: BoxDecoration(
                      color: AppTheme.primaryColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(12.r),
                    ),
                    child: Text(
                      '${items.length} ${context.tr('items', fallback: 'items')}',
                      style: AppTextStyles.caption.copyWith(
                        color: AppTheme.primaryColor,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
              ],
            ),
          ),

          Divider(
            height: 1.h,
            color: Theme.of(context).colorScheme.outlineVariant,
          ),

          // Content body
          Flexible(
            child: _isAuthenticated
                ? _buildBody(items)
                : _buildUnauthenticatedBody(),
          ),

          // Action Button
          if (hasItems || !_isAuthenticated)
            _buildActionButtons(),
        ],
      ),
    );
  }

  Widget _buildUnauthenticatedBody() {
    return Center(
      child: Padding(
        padding: EdgeInsets.symmetric(horizontal: 24.w, vertical: 32.h),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: EdgeInsets.all(16.w),
              decoration: BoxDecoration(
                color: AppTheme.primaryColor.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(
                TablerIcons.login,
                size: 36.sp,
                color: AppTheme.primaryColor,
              ),
            ),
            SizedBox(height: 16.h),
            Text(
              context.tr('signInToViewCart',
                  fallback: 'Sign in to access this shared cart'),
              textAlign: TextAlign.center,
              style: AppTextStyles.titleMedium.copyWith(
                fontWeight: FontWeight.w700,
                fontSize: 16.sp,
              ),
            ),
            SizedBox(height: 8.h),
            Text(
              context.tr('signInToViewCartSubtitle',
                  fallback:
                      'Please sign in or create an account to view and add these shared items to your cart.'),
              textAlign: TextAlign.center,
              style: AppTextStyles.bodySmall.copyWith(
                color: Colors.grey.shade600,
                height: 1.4,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBody(List<SharedCartItem> items) {
    if (_isLoading) {
      return Center(
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: 40.h),
          child: const CustomCircularProgressIndicator(),
        ),
      );
    }

    if (_errorMessage != null) {
      return Center(
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: 20.w, vertical: 30.h),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.error_outline,
                size: 40.sp,
                color: Colors.red.shade400,
              ),
              SizedBox(height: 12.h),
              Text(
                _errorMessage!,
                textAlign: TextAlign.center,
                style: AppTextStyles.bodySmall.copyWith(
                  color: Colors.grey.shade600,
                ),
              ),
              SizedBox(height: 16.h),
              TextButton(
                onPressed: _fetchSharedList,
                child: Text(
                  context.tr('retry', fallback: 'Retry'),
                  style: const TextStyle(color: AppTheme.primaryColor),
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (items.isEmpty) {
      return Center(
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: 40.h),
          child: Text(
            context.tr('noItemsFound',
                fallback: 'No items in this shared cart'),
            style: AppTextStyles.bodySmall.copyWith(
              color: Colors.grey.shade600,
            ),
          ),
        ),
      );
    }

    return ListView.separated(
      shrinkWrap: true,
      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
      itemCount: items.length,
      separatorBuilder: (context, index) => SizedBox(height: 10.h),
      itemBuilder: (context, index) {
        final item = items[index];
        return _buildItemCard(item);
      },
    );
  }

  Widget _buildItemCard(SharedCartItem item) {
    final String imagePath = item.image?.isNotEmpty == true
        ? item.image!
        : (item.variant?.image ?? '');
    final String currencySymbol = item.variant?.currencySymbol ?? '\$';
    final double? specialPrice = item.variant?.specialPrice;
    final double? price = item.variant?.price;
    final bool hasSpecialPrice = specialPrice != null &&
        specialPrice > 0 &&
        price != null &&
        specialPrice < price;

    final bool isOutOfStock = item.isOutOfStock;

    return Opacity(
      opacity: isOutOfStock ? 0.6 : 1.0,
      child: Container(
        padding: EdgeInsets.all(10.w),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: BorderRadius.circular(10.r),
          border: Border.all(
            color: Theme.of(context)
                .colorScheme
                .outlineVariant
                .withValues(alpha: 0.6),
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Product Thumbnail
            CustomImageContainer(
              imagePath: imagePath,
              width: 60.w,
              height: 60.w,
              borderRadius: BorderRadius.circular(8.r),
              fit: BoxFit.cover,
            ),
            SizedBox(width: 12.w),

            // Details
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          item.title ?? '',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.body.copyWith(
                            fontWeight: FontWeight.w600,
                            fontSize: 13.sp,
                          ),
                        ),
                      ),
                      if (isOutOfStock)
                        Container(
                          margin: EdgeInsets.only(left: 6.w),
                          padding: EdgeInsets.symmetric(
                              horizontal: 6.w, vertical: 2.h),
                          decoration: BoxDecoration(
                            color: Colors.red.shade50,
                            borderRadius: BorderRadius.circular(4.r),
                            border: Border.all(color: Colors.red.shade300),
                          ),
                          child: Text(
                            context.tr('outOfStock', fallback: 'Out of stock'),
                            style: AppTextStyles.caption.copyWith(
                              color: Colors.red.shade700,
                              fontSize: 9.sp,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                    ],
                  ),
                  if (item.variant?.title != null &&
                      item.variant!.title!.isNotEmpty) ...[
                    SizedBox(height: 3.h),
                    Text(
                      item.variant!.title!,
                      style: AppTextStyles.caption.copyWith(
                        color: Colors.grey.shade600,
                        fontSize: 11.sp,
                      ),
                    ),
                  ],
                  if (item.store?.name != null &&
                      item.store!.name!.isNotEmpty) ...[
                    SizedBox(height: 4.h),
                    Row(
                      children: [
                        Icon(
                          TablerIcons.building_store,
                          size: 12.sp,
                          color: Colors.grey.shade500,
                        ),
                        SizedBox(width: 4.w),
                        Expanded(
                          child: Text(
                            item.store!.name!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTextStyles.caption.copyWith(
                              color: Colors.grey.shade600,
                              fontSize: 11.sp,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                  SizedBox(height: 6.h),

                  // Price and Quantity Row wrapped to prevent overflow
                  Wrap(
                    crossAxisAlignment: WrapCrossAlignment.center,
                    alignment: WrapAlignment.spaceBetween,
                    spacing: 8.w,
                    runSpacing: 4.h,
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (hasSpecialPrice) ...[
                            Text(
                              '$currencySymbol${specialPrice.toStringAsFixed(2)}',
                              style: AppTextStyles.body.copyWith(
                                fontWeight: FontWeight.w700,
                                color: AppTheme.primaryColor,
                                fontSize: 13.sp,
                              ),
                            ),
                            SizedBox(width: 6.w),
                            Text(
                              '$currencySymbol${price.toStringAsFixed(2)}',
                              style: AppTextStyles.caption.copyWith(
                                decoration: TextDecoration.lineThrough,
                                color: Colors.grey.shade500,
                                fontSize: 11.sp,
                              ),
                            ),
                          ] else ...[
                            Text(
                              '$currencySymbol${(price ?? 0).toStringAsFixed(2)}',
                              style: AppTextStyles.body.copyWith(
                                fontWeight: FontWeight.w700,
                                color: AppTheme.primaryColor,
                                fontSize: 13.sp,
                              ),
                            ),
                          ],
                        ],
                      ),
                      Container(
                        padding: EdgeInsets.symmetric(
                            horizontal: 8.w, vertical: 2.h),
                        decoration: BoxDecoration(
                          color: Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(6.r),
                          border: Border.all(color: Colors.grey.shade300),
                        ),
                        child: Text(
                          '${context.tr('qty', fallback: 'Qty')}: ${item.quantity ?? 1}',
                          style: AppTextStyles.caption.copyWith(
                            fontWeight: FontWeight.w600,
                            color: Colors.black87,
                            fontSize: 11.sp,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Action button: "Add to Cart" if authenticated, "Sign In" if unauthenticated.
  Widget _buildActionButtons() {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(
          top: BorderSide(
            color: Theme.of(context).colorScheme.outlineVariant,
            width: 1.h,
          ),
        ),
      ),
      child: SafeArea(
        top: false,
        child: _isAuthenticated
            ? CustomButton(
                text: context.tr('addToCart', fallback: 'Add to Cart'),
                isLoading: _isImporting,
                onPressed: _isImporting ? null : _handleImportAction,
                width: double.infinity,
              )
            : CustomButton(
                text: context.tr('signIn', fallback: 'Sign In'),
                onPressed: _navigateToLogin,
                width: double.infinity,
              ),
      ),
    );
  }
}
