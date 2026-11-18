import 'dart:developer';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/global_keys.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/bloc/pagination/paginated_state.dart';
import 'package:hyper_commerce_seller/bloc/pagination/pagination_controller.dart';
import 'package:hyper_commerce_seller/bloc/pagination/pagination_response.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_filter_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/repo/store_repo.dart';

part 'stores_event.dart';
part 'stores_state.dart';

class StoresBloc extends Bloc<StoresEvent, StoresState> {
  final StoresRepo _repo;
  late final PaginationController<Store> _paginationController;

  String? _searchQuery;

  StoresBloc(this._repo) : super(const StoresState()) {
    _paginationController = PaginationController<Store>(
      fetcher: _fetchStores,
      emit: (paginatedState) => emit(state.copyWith(
        items: paginatedState.items,
        isInitialLoading: paginatedState.isInitialLoading,
        isRefreshing: paginatedState.isRefreshing,
        isPaginating: paginatedState.isPaginating,
        hasMore: paginatedState.hasMore,
        error: paginatedState.error,
        currentPage: paginatedState.currentPage,
        total: paginatedState.total,
      )),
      perPage: GlobalKeys.perPage,
    );

    on<LoadStoresInitial>(_onLoadStoresInitial);
    on<LoadMoreStores>(_onLoadMoreStores);
    on<RefreshStores>(_onRefreshStores);
    on<SearchStores>(_onSearchStores);
    on<LoadStoreFilters>(_onLoadStoreFilters);
    on<ApplyStoreFilter>(_onApplyStoreFilter);
    on<DeleteStore>(_onDeleteStore);
    on<ClearStoresOperation>((event, emit) => emit(state.copyWith(clearOperation: true)));
    on<ClearStores>((event, emit) => emit(const StoresState()));
  }

  Future<PaginationResponse<Store>> _fetchStores(int page, int perPage) async {
    try{
      final response = await _repo.getStores(
        page: page,
        perPage: perPage,
        search: _searchQuery,
        visibilityStatus: state.selectedVisibilityStatus,
        verificationStatus: state.selectedVerificationStatus,
      );
      final storesResponse = StoresResponse.fromJson(response);

      return PaginationResponse(
        items: storesResponse.data?.stores ?? [],
        total: storesResponse.data?.total,
        currentPage: page,
      );
    } catch (e, stack) {
      log('Store trace  ${stack.toString()}');
      return PaginationResponse(items: [], total: 0, currentPage: 1);
    }
  }

  Future<void> _onLoadStoreFilters(
    LoadStoreFilters event,
    Emitter<StoresState> emit,
  ) async {
    try {
      final response = await _repo.getStoreFilters();
      final filterModel = StoreFilterModel.fromJson(response);
      emit(state.copyWith(filterOptions: filterModel.storeFilter));
    } catch (e, stack) {
      log('Stafckckk ${stack.toString()}');
      // ignore: avoid_print
      print("Load filters error: $e");
    }
  }

  Future<void> _onApplyStoreFilter(
    ApplyStoreFilter event,
    Emitter<StoresState> emit,
  ) async {
    emit(state.copyWith(
      selectedVisibilityStatus: event.visibilityStatus,
      selectedVerificationStatus: event.verificationStatus,
      overrideFilters: true,
    ));
    await _paginationController.loadInitial(currentState: state);
  }

  Future<void> _onLoadStoresInitial(
      LoadStoresInitial event,
      Emitter<StoresState> emit,
      ) async {
    _searchQuery = event.search;
    await _paginationController.loadInitial(currentState: state);
  }

  Future<void> _onSearchStores(
    SearchStores event,
    Emitter<StoresState> emit,
  ) async {
    _searchQuery = event.query;
    await _paginationController.loadInitial(currentState: state);
  }

  Future<void> _onLoadMoreStores(
      LoadMoreStores event,
      Emitter<StoresState> emit,
      ) async {
    await _paginationController.loadNextPage(state);
  }

  Future<void> _onRefreshStores(
      RefreshStores event,
      Emitter<StoresState> emit,
      ) async {
    await _paginationController.refresh(state);
  }

  Future<void> _onDeleteStore(
    DeleteStore event,
    Emitter<StoresState> emit,
  ) async {
    emit(state.copyWith(clearOperation: true));
    try {
      await _repo.deleteStore(event.storeId);
      if (HiveStorage.selectedStoreId == event.storeId) {
        await HiveStorage.setSelectedStoreId(null);
      }
      emit(state.copyWith(
        operationSuccess: true,
        operationMessage: "Store deleted successfully",
        lastOperationType: 'delete',
        lastDeletedStoreId: event.storeId,
      ));
      add(RefreshStores());
    } catch (e) {
      emit(state.copyWith(
        operationSuccess: false,
        operationMessage: e.toString(),
        lastOperationType: 'delete',
      ));
    }
  }

}
