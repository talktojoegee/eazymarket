part of 'order_details_bloc.dart';

abstract class OrderDetailsEvent extends Equatable {
  const OrderDetailsEvent();

  @override
  List<Object> get props => [];
}

class FetchOrderDetails extends OrderDetailsEvent {
  final int orderId; // seller_order_id
  const FetchOrderDetails(this.orderId);

  @override
  List<Object> get props => [orderId];
}

/// Marks the loaded order as having an action in flight, so the bottom bar can
/// disable its buttons and show a spinner. Cleared by the [FetchOrderDetails]
/// that follows the action, or explicitly if the action fails.
class SetOrderDetailsBusy extends OrderDetailsEvent {
  final bool isBusy;
  const SetOrderDetailsBusy(this.isBusy);

  @override
  List<Object> get props => [isBusy];
}
