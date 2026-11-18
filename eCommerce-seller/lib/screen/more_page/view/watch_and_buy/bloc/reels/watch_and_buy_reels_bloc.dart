import 'dart:io';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../model/watch_and_buy_reel_model.dart';
import '../../repo/watch_and_buy_repo.dart';

// ─── Events ─────────────────────────────────────────────────────

abstract class WatchAndBuyReelsEvent extends Equatable {
  const WatchAndBuyReelsEvent();
  @override
  List<Object?> get props => [];
}

class FetchReelsEvent extends WatchAndBuyReelsEvent {
  final bool isRefresh;
  final String? status;
  const FetchReelsEvent({this.isRefresh = false, this.status});
  @override
  List<Object?> get props => [isRefresh, status];
}

class LoadMoreReelsEvent extends WatchAndBuyReelsEvent {
  const LoadMoreReelsEvent();
}

class DeleteReelEvent extends WatchAndBuyReelsEvent {
  final int reelId;
  const DeleteReelEvent(this.reelId);
  @override
  List<Object?> get props => [reelId];
}

class TogglePublishReelEvent extends WatchAndBuyReelsEvent {
  final int reelId;
  final bool publish;
  const TogglePublishReelEvent({required this.reelId, required this.publish});
  @override
  List<Object?> get props => [reelId, publish];
}

// ─── States ─────────────────────────────────────────────────────

abstract class WatchAndBuyReelsState extends Equatable {
  const WatchAndBuyReelsState();
  @override
  List<Object?> get props => [];
}

class ReelsInitial extends WatchAndBuyReelsState {}

class ReelsLoading extends WatchAndBuyReelsState {}

class ReelsLoaded extends WatchAndBuyReelsState {
  final List<WatchAndBuyReelModel> reels;
  final ReelsPagePagination pagination;
  final bool hasMore;
  final String? statusFilter;

  const ReelsLoaded({
    required this.reels,
    required this.pagination,
    required this.hasMore,
    this.statusFilter,
  });

  @override
  List<Object?> get props => [reels, pagination.currentPage, hasMore, statusFilter];

  ReelsLoaded copyWith({
    List<WatchAndBuyReelModel>? reels,
    ReelsPagePagination? pagination,
    bool? hasMore,
    String? statusFilter,
  }) {
    return ReelsLoaded(
      reels: reels ?? this.reels,
      pagination: pagination ?? this.pagination,
      hasMore: hasMore ?? this.hasMore,
      statusFilter: statusFilter ?? this.statusFilter,
    );
  }
}

class ReelsLoadingMore extends WatchAndBuyReelsState {
  final List<WatchAndBuyReelModel> currentReels;
  final ReelsPagePagination pagination;
  final String? statusFilter;

  const ReelsLoadingMore({
    required this.currentReels,
    required this.pagination,
    this.statusFilter,
  });

  @override
  List<Object?> get props => [currentReels, pagination.currentPage, statusFilter];
}

class ReelsActionInProgress extends WatchAndBuyReelsState {
  final String message;
  final List<WatchAndBuyReelModel> currentReels;
  final int? deletingReelId;

  const ReelsActionInProgress(
    this.message, {
    this.currentReels = const [],
    this.deletingReelId,
  });

  @override
  List<Object?> get props => [message, currentReels, deletingReelId];
}

class ReelsActionSuccess extends WatchAndBuyReelsState {
  final String message;
  final List<WatchAndBuyReelModel> currentReels;

  const ReelsActionSuccess(this.message, {this.currentReels = const []});

  @override
  List<Object?> get props => [message, currentReels];
}

class ReelsError extends WatchAndBuyReelsState {
  final String error;
  const ReelsError(this.error);
  @override
  List<Object?> get props => [error];
}

// ─── Bloc ───────────────────────────────────────────────────────

class WatchAndBuyReelsBloc extends Bloc<WatchAndBuyReelsEvent, WatchAndBuyReelsState> {
  final WatchAndBuyRepo repo;
  String? _currentStatus;

  WatchAndBuyReelsBloc({required this.repo}) : super(ReelsInitial()) {
    on<FetchReelsEvent>(_onFetchReels);
    on<LoadMoreReelsEvent>(_onLoadMoreReels);
    on<DeleteReelEvent>(_onDeleteReel);
    on<TogglePublishReelEvent>(_onTogglePublishReel);
  }

  Future<void> _onFetchReels(
    FetchReelsEvent event,
    Emitter<WatchAndBuyReelsState> emit,
  ) async {
    if (event.status != null || !event.isRefresh) {
      _currentStatus = (event.status == null || event.status?.toLowerCase() == 'all')
          ? null
          : event.status?.toLowerCase();
    }
    if (!event.isRefresh) {
      emit(ReelsLoading());
    }
    try {
      final response = await repo.getReels(page: 1, perPage: 15, status: _currentStatus);
      emit(ReelsLoaded(
        reels: response.items,
        pagination: response.pagination,
        hasMore: response.pagination.hasNextPage,
        statusFilter: _currentStatus,
      ));
    } catch (e) {
      emit(ReelsError(e.toString().replaceAll('Exception:', '').trim()));
    }
  }

  Future<void> _onLoadMoreReels(
    LoadMoreReelsEvent event,
    Emitter<WatchAndBuyReelsState> emit,
  ) async {
    if (state is! ReelsLoaded) return;
    final currentState = state as ReelsLoaded;
    if (!currentState.hasMore) return;

    emit(ReelsLoadingMore(
      currentReels: currentState.reels,
      pagination: currentState.pagination,
      statusFilter: _currentStatus,
    ));

    try {
      final nextPage = currentState.pagination.currentPage + 1;
      final response = await repo.getReels(page: nextPage, perPage: 15, status: _currentStatus);

      final updatedReels = List<WatchAndBuyReelModel>.from(currentState.reels)..addAll(response.items);

      emit(ReelsLoaded(
        reels: updatedReels,
        pagination: response.pagination,
        hasMore: response.pagination.hasNextPage,
        statusFilter: _currentStatus,
      ));
    } catch (e) {
      emit(currentState); // revert back to loaded
    }
  }

  Future<void> _onDeleteReel(
    DeleteReelEvent event,
    Emitter<WatchAndBuyReelsState> emit,
  ) async {
    List<WatchAndBuyReelModel> previousReels = [];
    ReelsPagePagination? previousPagination;
    bool previousHasMore = false;

    if (state is ReelsLoaded) {
      final s = state as ReelsLoaded;
      previousReels = s.reels;
      previousPagination = s.pagination;
      previousHasMore = s.hasMore;
    }

    emit(ReelsActionInProgress(
      'Deleting reel...',
      currentReels: previousReels,
      deletingReelId: event.reelId,
    ));
    try {
      await repo.deleteReel(event.reelId);
      final updatedReels = previousReels.where((r) => r.id != event.reelId).toList();
      emit(ReelsActionSuccess('Reel deleted successfully', currentReels: updatedReels));
      if (previousPagination != null) {
        emit(ReelsLoaded(
          reels: updatedReels,
          pagination: previousPagination,
          hasMore: previousHasMore,
          statusFilter: _currentStatus,
        ));
      } else {
        add(FetchReelsEvent(status: _currentStatus));
      }
    } catch (e) {
      emit(ReelsError(e.toString().replaceAll('Exception:', '').trim()));
      if (previousPagination != null) {
        emit(ReelsLoaded(
          reels: previousReels,
          pagination: previousPagination,
          hasMore: previousHasMore,
          statusFilter: _currentStatus,
        ));
      }
    }
  }

  Future<void> _onTogglePublishReel(
    TogglePublishReelEvent event,
    Emitter<WatchAndBuyReelsState> emit,
  ) async {
    if (state is! ReelsLoaded) return;
    final currentState = state as ReelsLoaded;

    try {
      await repo.togglePublishReel(event.reelId, event.publish);
      final newStatus = event.publish ? 'published' : 'unpublished';

      List<WatchAndBuyReelModel> updatedReels;
      if (_currentStatus != null && _currentStatus != newStatus) {
        updatedReels = currentState.reels.where((r) => r.id != event.reelId).toList();
      } else {
        updatedReels = currentState.reels.map((r) {
          if (r.id == event.reelId) {
            return r.copyWith(status: newStatus);
          }
          return r;
        }).toList();
      }

      emit(ReelsActionSuccess(event.publish ? 'Reel published' : 'Reel unpublished'));
      emit(currentState.copyWith(reels: updatedReels));
    } catch (e) {
      emit(ReelsError(e.toString().replaceAll('Exception:', '').trim()));
      emit(currentState);
    }
  }
}
