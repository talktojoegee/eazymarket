import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:equatable/equatable.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/model/store_location_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/repo/store_location_repo.dart';

enum StoreLocationStatus { initial, loading, loaded, error }

class StoreLocationState extends Equatable {
  final StoreLocationStatus status;
  final List<StoreLocation> locations;
  final String? error;
  final bool actionInProgress;
  final String? actionMessage;
  final bool? actionSuccess;

  const StoreLocationState({
    this.status = StoreLocationStatus.initial,
    this.locations = const [],
    this.error,
    this.actionInProgress = false,
    this.actionMessage,
    this.actionSuccess,
  });

  StoreLocationState copyWith({
    StoreLocationStatus? status,
    List<StoreLocation>? locations,
    String? error,
    bool? actionInProgress,
    String? actionMessage,
    bool? actionSuccess,
    bool clearAction = false,
  }) {
    return StoreLocationState(
      status: status ?? this.status,
      locations: locations ?? this.locations,
      error: error ?? this.error,
      actionInProgress: actionInProgress ?? this.actionInProgress,
      actionMessage: clearAction ? null : (actionMessage ?? this.actionMessage),
      actionSuccess: clearAction ? null : (actionSuccess ?? this.actionSuccess),
    );
  }

  @override
  List<Object?> get props => [status, locations, error, actionInProgress, actionMessage, actionSuccess];
}

class StoreLocationCubit extends Cubit<StoreLocationState> {
  final StoreLocationRepo _repo;

  /// Whether the current scope is "every store" (`all=1` on the wire).
  bool _all = false;

  /// Explicit X-Store-Id override for the current scope. When non-null the
  /// cubit is showing that store's locations only; when null and [_all] is
  /// false it uses the app-wide selected store from HiveStorage.
  int? _storeId;

  StoreLocationCubit(this._repo) : super(const StoreLocationState());

  /// Load locations. Scope args are remembered so [refresh] hits the same
  /// slice — otherwise a refresh after switching stores would silently
  /// snap back to the default (app-wide) scope.
  Future<void> load({bool all = false, int? storeId}) async {
    _all = all;
    _storeId = storeId;
    emit(state.copyWith(
        status: StoreLocationStatus.loading, clearAction: true));
    try {
      final response = await _repo.getLocations(all: all, storeId: storeId);
      final parsed = StoreLocationsResponse.fromJson(response);
      emit(state.copyWith(
          status: StoreLocationStatus.loaded, locations: parsed.locations));
    } catch (e) {
      emit(state.copyWith(
          status: StoreLocationStatus.error, error: e.toString()));
    }
  }

  Future<void> refresh() => load(all: _all, storeId: _storeId);

  Future<bool> promoteDefault(int id) => _action(() => _repo.promoteDefault(id));

  Future<bool> shiprocketSync(int id) => _action(() => _repo.shiprocketSync(id));

  Future<bool> delete(int id) => _action(() => _repo.deleteLocation(id));

  Future<bool> _action(Future<dynamic> Function() call) async {
    emit(state.copyWith(actionInProgress: true, clearAction: true));
    try {
      final res = await call();
      final ok = res is Map && (res['success'] == true);
      final msg = res is Map ? (res['message']?.toString()) : null;
      emit(state.copyWith(actionInProgress: false, actionSuccess: ok, actionMessage: msg));
      if (ok) await refresh();
      return ok;
    } catch (e) {
      emit(state.copyWith(actionInProgress: false, actionSuccess: false, actionMessage: e.toString()));
      return false;
    }
  }
}
