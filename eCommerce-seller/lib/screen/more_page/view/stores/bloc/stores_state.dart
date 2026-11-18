part of 'stores_bloc.dart';


class StoresState extends PaginatedState<Store> {
  final StoreFilter? filterOptions;
  final String? selectedVisibilityStatus;
  final String? selectedVerificationStatus;
  final int? lastDeletedStoreId;

  const StoresState({
    super.items,
    super.isInitialLoading,
    super.isRefreshing,
    super.isPaginating,
    super.hasMore,
    super.error,
    super.currentPage,
    super.total,
    super.operationSuccess,
    super.operationMessage,
    super.lastOperationType,
    this.filterOptions,
    this.selectedVisibilityStatus,
    this.selectedVerificationStatus,
    this.lastDeletedStoreId,
  });

  @override
  StoresState copyWith({
    List<Store>? items,
    bool? isInitialLoading,
    bool? isRefreshing,
    bool? isPaginating,
    bool? hasMore,
    String? error,
    int? currentPage,
    int? total,
    bool? operationSuccess,
    String? operationMessage,
    String? lastOperationType,
    int? lastDeletedStoreId,
    bool clearOperation = false,
    StoreFilter? filterOptions,
    String? selectedVisibilityStatus,
    String? selectedVerificationStatus,
    bool overrideFilters = false,
  }) {
    return StoresState(
      items: items ?? this.items,
      isInitialLoading: isInitialLoading ?? this.isInitialLoading,
      isRefreshing: isRefreshing ?? this.isRefreshing,
      isPaginating: isPaginating ?? this.isPaginating,
      hasMore: hasMore ?? this.hasMore,
      error: clearOperation ? null : (error ?? this.error),
      currentPage: currentPage ?? this.currentPage,
      total: total ?? this.total,
      operationSuccess: clearOperation ? null : (operationSuccess ?? this.operationSuccess),
      operationMessage: clearOperation ? null : (operationMessage ?? this.operationMessage),
      lastOperationType: clearOperation ? null : (lastOperationType ?? this.lastOperationType),
      lastDeletedStoreId: clearOperation ? null : (lastDeletedStoreId ?? this.lastDeletedStoreId),
      filterOptions: filterOptions ?? this.filterOptions,
      selectedVisibilityStatus: overrideFilters ? selectedVisibilityStatus : (selectedVisibilityStatus ?? this.selectedVisibilityStatus),
      selectedVerificationStatus: overrideFilters ? selectedVerificationStatus : (selectedVerificationStatus ?? this.selectedVerificationStatus),
    );
  }

  @override
  List<Object?> get props => [
        ...super.props,
        filterOptions,
        selectedVisibilityStatus,
        selectedVerificationStatus,
        lastDeletedStoreId,
      ];
}
