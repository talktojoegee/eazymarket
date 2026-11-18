import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../model/eligible_product_model.dart';
import '../../repo/watch_and_buy_repo.dart';

// ─── Events ─────────────────────────────────────────────────────

abstract class EligibleProductsEvent extends Equatable {
  const EligibleProductsEvent();
  @override
  List<Object?> get props => [];
}

class FetchEligibleProductsEvent extends EligibleProductsEvent {
  final String? search;
  const FetchEligibleProductsEvent({this.search});
  @override
  List<Object?> get props => [search];
}

class LoadMoreEligibleProductsEvent extends EligibleProductsEvent {
  const LoadMoreEligibleProductsEvent();
}

class SearchEligibleProductsEvent extends EligibleProductsEvent {
  final String query;
  const SearchEligibleProductsEvent(this.query);
  @override
  List<Object?> get props => [query];
}

// ─── States ─────────────────────────────────────────────────────

abstract class EligibleProductsState extends Equatable {
  const EligibleProductsState();
  @override
  List<Object?> get props => [];
}

class EligibleProductsInitial extends EligibleProductsState {}

class EligibleProductsLoading extends EligibleProductsState {}

class EligibleProductsLoaded extends EligibleProductsState {
  final List<EligibleProductModel> products;
  final CursorPaginationModel pagination;
  final String? search;
  final bool hasMore;

  const EligibleProductsLoaded({
    required this.products,
    required this.pagination,
    this.search,
    required this.hasMore,
  });

  @override
  List<Object?> get props => [products, pagination.nextCursor, search, hasMore];
}

class EligibleProductsLoadingMore extends EligibleProductsState {
  final List<EligibleProductModel> currentProducts;
  final CursorPaginationModel pagination;
  final String? search;

  const EligibleProductsLoadingMore({
    required this.currentProducts,
    required this.pagination,
    this.search,
  });

  @override
  List<Object?> get props => [currentProducts, pagination.nextCursor, search];
}

class EligibleProductsError extends EligibleProductsState {
  final String error;
  const EligibleProductsError(this.error);
  @override
  List<Object?> get props => [error];
}

// ─── Bloc ───────────────────────────────────────────────────────

class EligibleProductsBloc extends Bloc<EligibleProductsEvent, EligibleProductsState> {
  final WatchAndBuyRepo repo;

  EligibleProductsBloc({required this.repo}) : super(EligibleProductsInitial()) {
    on<FetchEligibleProductsEvent>(_onFetchEligibleProducts);
    on<SearchEligibleProductsEvent>(_onSearchEligibleProducts);
    on<LoadMoreEligibleProductsEvent>(_onLoadMoreEligibleProducts);
  }

  Future<void> _onFetchEligibleProducts(
    FetchEligibleProductsEvent event,
    Emitter<EligibleProductsState> emit,
  ) async {
    emit(EligibleProductsLoading());
    try {
      final response = await repo.getEligibleProducts(
        perPage: 15,
        search: event.search,
      );
      emit(EligibleProductsLoaded(
        products: response.items,
        pagination: response.pagination,
        search: event.search,
        hasMore: response.pagination.hasMorePages,
      ));
    } catch (e) {
      emit(EligibleProductsError(e.toString().replaceAll('Exception:', '').trim()));
    }
  }

  Future<void> _onSearchEligibleProducts(
    SearchEligibleProductsEvent event,
    Emitter<EligibleProductsState> emit,
  ) async {
    emit(EligibleProductsLoading());
    try {
      final response = await repo.getEligibleProducts(
        perPage: 15,
        search: event.query,
      );
      emit(EligibleProductsLoaded(
        products: response.items,
        pagination: response.pagination,
        search: event.query,
        hasMore: response.pagination.hasMorePages,
      ));
    } catch (e) {
      emit(EligibleProductsError(e.toString().replaceAll('Exception:', '').trim()));
    }
  }

  Future<void> _onLoadMoreEligibleProducts(
    LoadMoreEligibleProductsEvent event,
    Emitter<EligibleProductsState> emit,
  ) async {
    if (state is! EligibleProductsLoaded) return;
    final currentState = state as EligibleProductsLoaded;
    if (!currentState.hasMore || currentState.pagination.nextCursor == null) return;

    emit(EligibleProductsLoadingMore(
      currentProducts: currentState.products,
      pagination: currentState.pagination,
      search: currentState.search,
    ));

    try {
      final response = await repo.getEligibleProducts(
        cursor: currentState.pagination.nextCursor,
        perPage: 15,
        search: currentState.search,
      );

      final updated = List<EligibleProductModel>.from(currentState.products)..addAll(response.items);

      emit(EligibleProductsLoaded(
        products: updated,
        pagination: response.pagination,
        search: currentState.search,
        hasMore: response.pagination.hasMorePages,
      ));
    } catch (e) {
      emit(currentState);
    }
  }
}
