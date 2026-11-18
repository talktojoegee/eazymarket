import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/screens/notification_page/model/notification_list_model.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/screens/product_listing_page/model/product_listing_type.dart';
import '../../../../router/app_routes.dart';
import '../../bloc/notification_bloc.dart';

class NotificationCard extends StatelessWidget {
  final NotificationItem notification;

  const NotificationCard({super.key, required this.notification});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final isRead = notification.isRead ?? true;

    // Merge outer data and nested metadata seamlessly
    final outerData = notification.data ?? {};
    Map<String, dynamic>? innerMeta = notification.metadata;
    if (innerMeta == null && outerData['metadata'] != null) {
      final raw = outerData['metadata'];
      if (raw is Map) {
        innerMeta = Map<String, dynamic>.from(raw);
      } else if (raw is String && raw.isNotEmpty) {
        try {
          final decoded = jsonDecode(raw);
          if (decoded is Map) innerMeta = Map<String, dynamic>.from(decoded);
        } catch (_) {}
      }
    }

    final Map<String, dynamic> combined = {
      ...outerData,
      if (innerMeta != null) ...innerMeta,
      if (notification.metadata != null) ...notification.metadata!,
    };

    final type = notification.type ?? combined['type']?.toString() ?? '';
    final subtitle = _getSubtitle(combined, type);

    // Check if actions explicitly specifies rate_now
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

    final status = (combined['status'] ??
            combined['customer_status'] ??
            combined['fulfillment_status'] ??
            '')
        .toString()
        .toLowerCase();
    final typeLower = type.toLowerCase();
    final titleLower = (notification.title ?? combined['title'] ?? '').toString().toLowerCase();
    final messageLower = (notification.message ?? combined['message'] ?? combined['body'] ?? '').toString().toLowerCase();

    final isDelivered = hasRateNowInActions ||
        typeLower == 'delivered' ||
        typeLower == 'delivery' ||
        typeLower == 'order_delivered' ||
        status == 'delivered' ||
        status == 'fulfilled' ||
        titleLower.contains('delivered') ||
        messageLower.contains('delivered');

    final rawReviewGiven = combined['is_user_review_given'];
    final bool isUserReviewGiven = rawReviewGiven == true ||
        rawReviewGiven?.toString() == 'true' ||
        rawReviewGiven?.toString() == '1';
    final showRateButton = isDelivered && !isUserReviewGiven;

    return InkWell(
      onTap: () =>
          _handleTap(context, type.isNotEmpty ? type : 'general', combined),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14.r),
        ),
        margin: EdgeInsets.only(left: 10.w, right: 10.w, bottom: 12.w),
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: 2.w, vertical: 8.h),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  if (!isRead)
                    Container(
                      width: 4.w,
                      height: 4.w,
                      decoration: const BoxDecoration(
                        color: AppTheme.primaryColor,
                        shape: BoxShape.circle,
                      ),
                    ),
                  const SizedBox(width: 12),
                  Container(
                    width: 44.w,
                    height: 44.w,
                    decoration: BoxDecoration(
                      color: _iconBgColor(notification.type ?? 'general'),
                      shape: BoxShape.circle,
                    ),
                    child: Center(
                      child: Icon(
                        _iconForType(notification.type ?? 'general'),
                        color: _iconColor(
                            notification.type ?? 'general', colorScheme),
                        size: 20.sp,
                      ),
                    ),
                  ),
                ],
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            notification.title ?? 'Notification',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontWeight: FontWeight.w600,
                              fontSize: 13.5.sp,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 4.h),
                    Text(
                      notification.message ?? '',
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 12.5.sp,
                        color: theme.textTheme.bodySmall?.color
                            ?.withValues(alpha: 0.75),
                        height: 1.4,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (subtitle != null) ...[
                      SizedBox(height: 4.h),
                      Text(
                        subtitle,
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 11.5.sp,
                          color: theme.colorScheme.primary,
                          fontWeight: FontWeight.w500,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    SizedBox(height: 8.h),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Text(
                          _formatRelativeTime(notification.createdAt),
                          style: theme.textTheme.labelSmall?.copyWith(
                            fontSize: 11.sp,
                            color: theme.textTheme.bodySmall?.color
                                ?.withValues(alpha: 0.5),
                          ),
                        ),
                        if (showRateButton)
                          GestureDetector(
                            onTap: () {
                              final notificationId = notification.id;
                              if (notificationId != null && notificationId.isNotEmpty) {
                                context.read<NotificationBloc>().add(
                                      MarkAsReadSpecificNotification(notificationId: notificationId),
                                    );
                              }
                              dynamic rawId = combined['order_item_id'] ?? combined['order-item-id'];
                              if (rawId == null &&
                                  combined['order_item_ids'] is List &&
                                  (combined['order_item_ids'] as List).isNotEmpty) {
                                rawId = (combined['order_item_ids'] as List).first;
                              }
                              final itemId = rawId is int ? rawId : int.tryParse(rawId?.toString() ?? '') ?? 0;
                              if (itemId > 0) {
                                GoRouter.of(context).push(
                                  AppRoutes.productRating,
                                  extra: {
                                    'orderItemId': itemId,
                                    'productName': combined['product_name'] ?? combined['title'] ?? notification.title,
                                    'variantTitle': combined['variant_title']?.toString(),
                                    'image': combined['image']?.toString(),
                                    'initialRating': 0,
                                  },
                                );
                              } else {
                                final slug = combined['order_slug'] ?? combined['slug'] ?? '';
                                GoRouter.of(context).push(
                                  AppRoutes.orderItemDetail,
                                  extra: {
                                    'order-slug': slug,
                                    'order-item-id': rawId?.toString() ?? '',
                                  },
                                );
                              }
                            },
                            child: Container(
                              padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 4.h),
                              decoration: BoxDecoration(
                                color: theme.colorScheme.primary.withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(6.r),
                                border: Border.all(
                                  color: theme.colorScheme.primary.withValues(alpha: 0.3),
                                  width: 1,
                                ),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    Icons.star_outline_rounded,
                                    size: 14.sp,
                                    color: theme.colorScheme.primary,
                                  ),
                                  SizedBox(width: 4.w),
                                  Text(
                                    'Rate now',
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      fontWeight: FontWeight.w600,
                                      color: theme.colorScheme.primary,
                                      fontSize: 11.5.sp,
                                    ),
                                  ),
                                ],
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
      ),
    );
  }

  IconData _iconForType(String type) {
    switch (type) {
      case 'new_order':
        return Icons.shopping_bag_outlined;
      case 'order_update':
        return Icons.shopping_bag_outlined;
      case 'return_order_update':
        return Icons.assignment_return_outlined;
      case 'offer':
        return Icons.local_offer_outlined;
      case 'wallet_transaction':
        return Icons.account_balance_wallet_outlined;
      case 'support_message':
        return Icons.support_agent_outlined;
      case 'payment':
        return Icons.payments;
      default:
        return Icons.notifications_outlined;
    }
  }

  Color _iconBgColor(String type) {
    switch (type) {
      case 'new_order':
      case 'order_update':
        return const Color(0xFFE8F0FE);
      case 'return_order':
      case 'return_order_update':
        return const Color(0xFFFFF8E1);
      case 'offer':
      case 'promotion':
        return const Color(0xFFF3E8FD);
      case 'wallet_transaction':
      case 'wallet':
      case 'settlement_process':
      case 'settlement_create':
      case 'withdrawal_request':
        return const Color(0xFFE0F7FA);
      default:
        return AppTheme.primaryColor.withValues(alpha: 0.08);
    }
  }

  Color _iconColor(String type, ColorScheme colorScheme) {
    switch (type) {
      case 'new_order':
        return const Color(0xFF0D47A1);
      case 'order_update':
        return const Color(0xFF1B5E20);
      case 'return_order':
      case 'return_order_update':
        return const Color(0xFFE65100);
      case 'offer':
      case 'promotion':
        return const Color(0xFF6A1B9A);
      case 'wallet_transaction':
      case 'wallet':
        return const Color(0xFF004D40);
      case 'support_message':
        return AppTheme.primaryColor;
      case 'payment':
        return Colors.orangeAccent;
      default:
        return colorScheme.tertiary.withValues(alpha: 0.7);
    }
  }

  String? _getSubtitle(Map<String, dynamic>? meta, String type) {
    if (meta == null) return null;
    if (type.contains('OrderItemReturnUpdated')) {
      return 'Item #${meta['order_item_id']} • Status: ${meta['return_status']}';
    } else if (type.contains('SellerSettlementSettled')) {
      return 'Reference: ${meta['settlement_reference']} • Settled at: ${meta['settled_at']}';
    } else if (type.contains('WalletTransactionOccurred')) {
      return 'Transaction #${meta['wallet_transaction_id']} • Type: ${meta['transaction_type']} • Status: ${meta['status']}';
    } else if (type.contains('SellerSettlementCreated')) {
      return 'Statement #${meta['seller_statement_id']} • Type: ${meta['entry_type']}';
    } else if (type.contains('SellerWithdrawalStatusUpdated')) {
      return 'Request #${meta['withdrawal_request_id']} • Status: ${meta['new_status']}';
    }
    return null;
  }

  void _handleTap(BuildContext context, String simplifiedType,
      Map<String, dynamic>? metadata) {
    if (metadata == null) return;
    String? route;
    Map<String, dynamic> extra = {};
    void markAsSeenApi () {
      final notificationId = notification.id;
      if (notificationId != null && notificationId.isNotEmpty) {
        context.read<NotificationBloc>().add(
          MarkAsReadSpecificNotification(notificationId: notificationId),
        );
      }
    }
    switch (simplifiedType) {
      case 'order':
      case 'delivery':
      case 'new_order':
      case 'Delivered':
      case 'order_delivered':
      case 'order_update':
      case 'return_order':
      case 'return_order_update':
        route = AppRoutes.orderItemDetail;
        extra['order-slug'] = metadata['order_slug'] ?? metadata['slug'];
        extra['order-item-id'] = metadata['order-item-id'] ?? metadata['order_item_id'];
        break;
      case 'wallet_transaction':
        route = AppRoutes.transactions;
        break;
      case 'support_message':
        route = AppRoutes.supportPage;
        extra['ticket-slug'] = metadata['ticket_slug'];
        break;
      case 'product':
        route = AppRoutes.productDetailPage;
        extra['productSlug'] = metadata['slug'];
        break;
      case 'featured_section':
        route = AppRoutes.productListing;
        extra['isTheirMoreCategory'] = false;
        extra['title'] = metadata['title'];
        extra['logo'] = metadata['image'];
        extra['totalProduct'] = 10;
        extra['type'] = ProductListingType.featuredSection;
        extra['identifier'] = metadata['slug'];

        break;
      case 'category':
        route = AppRoutes.productListing;
        extra['isTheirMoreCategory'] = false;
        extra['title'] = metadata['title'];
        extra['logo'] = metadata['image'];
        extra['totalProduct'] = 10;
        extra['type'] = ProductListingType.category;
        extra['identifier'] = metadata['slug'];
        break;
      case 'brand':
        route = AppRoutes.productListing;
        extra['isTheirMoreCategory'] = false;
        extra['title'] = metadata['title'];
        extra['logo'] = metadata['image'];
        extra['totalProduct'] = 10;
        extra['type'] = ProductListingType.brand;
        extra['identifier'] = metadata['slug'];
        break;
      case 'store':
        route = AppRoutes.nearbyStoreDetails;
        extra['store-slug'] = metadata['slug'];
        extra['store-name'] = metadata['title'];
        break;
      case 'payment':
        markAsSeenApi();
        return;
      default:
        return;
    }

    if (route.isNotEmpty) {
      GoRouter.of(context).push(route, extra: extra);
    }
    markAsSeenApi();
    /*final notificationId = notification.id;
    if (notificationId != null && notificationId.isNotEmpty) {
      context.read<NotificationBloc>().add(
            MarkAsReadSpecificNotification(notificationId: notificationId),
          );
    }*/
    // Optionally mark as read: context.read<NotificationBloc>().add(MarkAsRead(notification.id));
  }

  String _formatRelativeTime(String? isoDate) {
    if (isoDate == null || isoDate.isEmpty) return '';
    try {
      final dt = DateTime.parse(isoDate).toLocal();
      final now = DateTime.now();
      final diff = now.difference(dt);
      if (diff.inDays > 1) return '${diff.inDays} days ago';
      if (diff.inDays == 1) return 'Yesterday';
      if (diff.inHours > 0) return '${diff.inHours} hours ago';
      if (diff.inMinutes > 0) return '${diff.inMinutes} min ago';
      return 'Just now';
    } catch (_) {
      return isoDate;
    }
  }
}
