import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_fulfillment_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/repo/order_repo.dart';

class OrderFulfillmentState {
  final bool isLoading;
  final bool isBusy;
  final OrderFulfillment? data;
  final String? error;
  final String? message;
  final bool messageIsError;

  const OrderFulfillmentState({
    this.isLoading = false,
    this.isBusy = false,
    this.data,
    this.error,
    this.message,
    this.messageIsError = false,
  });

  OrderFulfillmentState copyWith({
    bool? isLoading,
    bool? isBusy,
    OrderFulfillment? data,
    String? error,
    String? message,
    bool? messageIsError,
    bool clearMessage = false,
  }) {
    return OrderFulfillmentState(
      isLoading: isLoading ?? this.isLoading,
      isBusy: isBusy ?? this.isBusy,
      data: data ?? this.data,
      error: error,
      message: clearMessage ? null : (message ?? this.message),
      messageIsError: messageIsError ?? this.messageIsError,
    );
  }
}

class OrderFulfillmentCubit extends Cubit<OrderFulfillmentState> {
  final OrdersRepo _repo;
  final int sellerOrderId;

  OrderFulfillmentCubit(this._repo, this.sellerOrderId) : super(const OrderFulfillmentState());

  Future<void> load() async {
    emit(state.copyWith(isLoading: true, clearMessage: true));
    try {
      final res = await _repo.getOrderFulfillment(sellerOrderId);
      emit(state.copyWith(isLoading: false, data: OrderFulfillment.fromJson(res)));
    } catch (e) {
      emit(state.copyWith(isLoading: false, error: _clean(e)));
    }
  }

  Future<bool> fulfill(Map<String, dynamic> body) =>
      _run(() => _repo.fulfillOrder(sellerOrderId, body));

  Future<bool> setFulfillmentStatus(String status) =>
      _run(() => _repo.setFulfillmentStatus(sellerOrderId, status));

  Future<bool> transition(int shipmentId, String status) =>
      _run(() => _repo.transitionShipment(shipmentId, status));

  Future<bool> sync(int shipmentId) => _run(() => _repo.syncShipment(shipmentId));

  Future<bool> retryBooking(int shipmentId) => _run(() => _repo.retryShipmentBooking(shipmentId));

  Future<bool> editShipment(int shipmentId, Map<String, dynamic> body) =>
      _run(() => _repo.editShipment(shipmentId, body));

  Future<bool> updateShipmentLines(int shipmentId, Map<String, dynamic> body) =>
      _run(() => _repo.updateShipmentLines(shipmentId, body));

  Future<bool> ndrAction(int shipmentId, String action, String comments) =>
      _run(() => _repo.ndrShipmentAction(shipmentId, {'action': action, 'comments': comments}));

  Future<bool> restock(List<int> itemIds) => _run(() => _repo.restockOrder(sellerOrderId, itemIds));

  /// Runs a parcel action, surfaces its message, then reloads the payload.
  /// The API answers 200 with `success:false` on business failures, so the
  /// envelope is inspected rather than relying on a thrown error.
  Future<bool> _run(Future<dynamic> Function() call) async {
    emit(state.copyWith(isBusy: true, clearMessage: true));
    try {
      final res = await call();
      final ok = res is Map ? (res['success'] != false) : true;
      final msg = res is Map ? (res['message']?.toString() ?? '') : '';
      final warning = res is Map && res['data'] is Map && res['data']['warning'] == true;
      emit(state.copyWith(isBusy: false, message: msg, messageIsError: !ok || warning));
      await load();
      return ok;
    } catch (e) {
      emit(state.copyWith(isBusy: false, message: _clean(e), messageIsError: true));
      return false;
    }
  }

  String _clean(Object e) => e.toString().replaceFirst('Exception: ', '');
}
