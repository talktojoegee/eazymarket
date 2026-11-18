part of 'orders_bloc.dart';

abstract class OrdersEvent {}

class LoadOrdersInitial extends OrdersEvent {
  final String? search;
  final int? storeId;
  LoadOrdersInitial({this.search, this.storeId});
}

class LoadMoreOrders extends OrdersEvent {}

class RefreshOrders extends OrdersEvent {
  final int? storeId;
  RefreshOrders({this.storeId});
}

/// Updates one already-loaded row in place, with no network call and without
/// resetting pagination. Dispatch this after an action on a single order
/// instead of [RefreshOrders], which reloads from page 1 and throws away the
/// seller's scroll position.
class PatchOrderStatus extends OrdersEvent {
  final int sellerOrderId;
  final String? status;

  PatchOrderStatus({required this.sellerOrderId, this.status});
}

class SearchOrders extends OrdersEvent {
  final String query;
  SearchOrders(this.query);
}

class ApplyFilter extends OrdersEvent {
  final String? paymentType;
  final String? range;
  final String? sortBy;
  final String? sortDir;
  final String? status;

  ApplyFilter({
    this.paymentType,
    this.range,
    this.sortBy,
    this.sortDir,
    this.status,
  });
}

class SetFulfillmentMethodTab extends OrdersEvent {
  final String? method;
  SetFulfillmentMethodTab(this.method);
}

class ClearFilters extends OrdersEvent {}

class OrdersReset extends OrdersEvent {}

