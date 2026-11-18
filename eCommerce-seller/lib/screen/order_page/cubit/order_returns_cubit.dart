import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:equatable/equatable.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_return_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/repo/order_repo.dart';

enum OrderReturnsStatus { initial, loading, loaded, error }

class OrderReturnsState extends Equatable {
  final OrderReturnsStatus status;
  final List<OrderReturn> returns;
  final CurrencyPayload? currency;
  final String? error;
  final String? filterStatus;
  final bool actionInProgress;
  final String? actionMessage;
  final bool? actionSuccess;

  const OrderReturnsState({
    this.status = OrderReturnsStatus.initial,
    this.returns = const [],
    this.currency,
    this.error,
    this.filterStatus,
    this.actionInProgress = false,
    this.actionMessage,
    this.actionSuccess,
  });

  OrderReturnsState copyWith({
    OrderReturnsStatus? status,
    List<OrderReturn>? returns,
    CurrencyPayload? currency,
    String? error,
    String? filterStatus,
    bool? actionInProgress,
    String? actionMessage,
    bool? actionSuccess,
    bool clearAction = false,
  }) {
    return OrderReturnsState(
      status: status ?? this.status,
      returns: returns ?? this.returns,
      currency: currency ?? this.currency,
      error: error ?? this.error,
      filterStatus: filterStatus ?? this.filterStatus,
      actionInProgress: actionInProgress ?? this.actionInProgress,
      actionMessage: clearAction ? null : (actionMessage ?? this.actionMessage),
      actionSuccess: clearAction ? null : (actionSuccess ?? this.actionSuccess),
    );
  }

  @override
  List<Object?> get props => [
        status,
        returns,
        currency,
        error,
        filterStatus,
        actionInProgress,
        actionMessage,
        actionSuccess,
      ];
}

class OrderReturnsCubit extends Cubit<OrderReturnsState> {
  final OrdersRepo _repo;

  OrderReturnsCubit(this._repo) : super(const OrderReturnsState());

  Future<void> load({String? filterStatus}) async {
    emit(state.copyWith(status: OrderReturnsStatus.loading, filterStatus: filterStatus, clearAction: true));
    try {
      final res = await _repo.getReturns(perPage: 50, returnStatus: filterStatus);
      final parsed = OrderReturnsResponse.fromJson(res);
      emit(state.copyWith(
        status: OrderReturnsStatus.loaded,
        returns: parsed.returns,
        currency: parsed.currency,
      ));
    } catch (e) {
      emit(state.copyWith(status: OrderReturnsStatus.error, error: e.toString()));
    }
  }

  Future<void> refresh() => load(filterStatus: state.filterStatus);

  Future<bool> approve(int returnId, {Map<String, dynamic>? data}) =>
      _action(() => _repo.approveReturn(returnId, data ?? {}));

  Future<bool> decline(int returnId, {String? reason}) =>
      _action(() => _repo.declineReturn(returnId, reason: reason));

  Future<bool> _action(Future<dynamic> Function() call) async {
    emit(state.copyWith(actionInProgress: true, clearAction: true));
    try {
      final res = await call();
      final ok = res is Map && res['success'] == true;
      emit(state.copyWith(
        actionInProgress: false,
        actionSuccess: ok,
        actionMessage: res is Map ? res['message']?.toString() : null,
      ));
      if (ok) await refresh();
      return ok;
    } catch (e) {
      emit(state.copyWith(actionInProgress: false, actionSuccess: false, actionMessage: e.toString()));
      return false;
    }
  }
}
