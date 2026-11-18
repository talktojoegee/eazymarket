import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:equatable/equatable.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_details_model.dart';
import 'package:hyper_commerce_seller/screen/order_page/repo/order_repo.dart';

part 'order_details_event.dart';
part 'order_details_state.dart';

class OrderDetailsBloc extends Bloc<OrderDetailsEvent, OrderDetailsState> {
  final OrdersRepo _repo;

  /// Which order the current [OrderDetailsLoaded] belongs to. The bloc is
  /// app-wide, so this is what tells a re-fetch of the *same* order apart from
  /// opening a different one.
  int? _loadedOrderId;

  OrderDetailsBloc(this._repo) : super(OrderDetailsInitial()) {
    on<FetchOrderDetails>(_onFetchOrderDetails);
    on<SetOrderDetailsBusy>(_onSetBusy);
  }

  void _onSetBusy(SetOrderDetailsBusy event, Emitter<OrderDetailsState> emit) {
    final current = state;
    if (current is OrderDetailsLoaded) {
      emit(current.copyWith(isUpdating: event.isBusy));
    }
  }

  Future<void> _onFetchOrderDetails(
    FetchOrderDetails event,
    Emitter<OrderDetailsState> emit,
  ) async {
    final current = state;
    final isReloadOfSameOrder =
        current is OrderDetailsLoaded && _loadedOrderId == event.orderId;

    // Only blank the screen when there is nothing to show. Re-fetching after
    // an action keeps the details on screen and swaps them when they land,
    // instead of flashing a spinner over the whole page.
    if (!isReloadOfSameOrder) {
      _loadedOrderId = null;
      emit(OrderDetailsLoading());
    }

    try {
      final response = await _repo.getOrderDetails(event.orderId);
      final detailsResponse = OrderDetailsResponse.fromJson(response);

      if (detailsResponse.data != null) {
        _loadedOrderId = event.orderId;
        emit(OrderDetailsLoaded(orderData: detailsResponse.data!));
      } else if (!isReloadOfSameOrder) {
        emit(OrderDetailsError(
          detailsResponse.message ?? 'Failed to load details',
        ));
      }
    } catch (e) {
      // A failed refresh keeps the copy already on screen; only a failed
      // first load takes over the page.
      if (!isReloadOfSameOrder) emit(OrderDetailsError(e.toString()));
    }
  }
}
