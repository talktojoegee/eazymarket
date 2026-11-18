import 'dart:io';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../model/eligible_product_model.dart';
import '../../model/seller_status_model.dart';
import '../../repo/watch_and_buy_repo.dart';

// ─── Events ─────────────────────────────────────────────────────

abstract class WatchAndBuyStatusEvent extends Equatable {
  const WatchAndBuyStatusEvent();
  @override
  List<Object?> get props => [];
}

class FetchStatusesEvent extends WatchAndBuyStatusEvent {
  final bool isRefresh;
  const FetchStatusesEvent({this.isRefresh = false});
  @override
  List<Object?> get props => [isRefresh];
}

class LoadMoreStatusesEvent extends WatchAndBuyStatusEvent {
  const LoadMoreStatusesEvent();
}

class DeleteStatusEvent extends WatchAndBuyStatusEvent {
  final int statusId;
  const DeleteStatusEvent(this.statusId);
  @override
  List<Object?> get props => [statusId];
}

class CreateStatusEvent extends WatchAndBuyStatusEvent {
  final String? text;
  final File? media;
  final int? profileId;
  final List<int>? attachmentIds;

  const CreateStatusEvent({
    this.text,
    this.media,
    this.profileId,
    this.attachmentIds,
  });

  @override
  List<Object?> get props => [text, media?.path, profileId, attachmentIds];
}

// ─── States ─────────────────────────────────────────────────────

abstract class WatchAndBuyStatusState extends Equatable {
  const WatchAndBuyStatusState();
  @override
  List<Object?> get props => [];
}

class StatusInitial extends WatchAndBuyStatusState {}

class StatusLoading extends WatchAndBuyStatusState {}

class StatusLoaded extends WatchAndBuyStatusState {
  final List<SellerStatusModel> statuses;
  final CursorPaginationModel pagination;
  final bool hasMore;

  const StatusLoaded({
    required this.statuses,
    required this.pagination,
    required this.hasMore,
  });

  @override
  List<Object?> get props => [statuses, pagination.nextCursor, hasMore];

  StatusLoaded copyWith({
    List<SellerStatusModel>? statuses,
    CursorPaginationModel? pagination,
    bool? hasMore,
  }) {
    return StatusLoaded(
      statuses: statuses ?? this.statuses,
      pagination: pagination ?? this.pagination,
      hasMore: hasMore ?? this.hasMore,
    );
  }
}

class StatusLoadingMore extends WatchAndBuyStatusState {
  final List<SellerStatusModel> currentStatuses;
  final CursorPaginationModel pagination;

  const StatusLoadingMore({required this.currentStatuses, required this.pagination});

  @override
  List<Object?> get props => [currentStatuses, pagination.nextCursor];
}

class StatusActionInProgress extends WatchAndBuyStatusState {
  final String message;
  final List<SellerStatusModel> currentStatuses;
  final int? deletingStatusId;

  const StatusActionInProgress(
    this.message, {
    this.currentStatuses = const [],
    this.deletingStatusId,
  });

  @override
  List<Object?> get props => [message, currentStatuses, deletingStatusId];
}

class StatusActionSuccess extends WatchAndBuyStatusState {
  final String message;
  final List<SellerStatusModel> currentStatuses;

  const StatusActionSuccess(this.message, {this.currentStatuses = const []});

  @override
  List<Object?> get props => [message, currentStatuses];
}

class StatusError extends WatchAndBuyStatusState {
  final String error;
  const StatusError(this.error);
  @override
  List<Object?> get props => [error];
}

// ─── Bloc ───────────────────────────────────────────────────────

class WatchAndBuyStatusBloc extends Bloc<WatchAndBuyStatusEvent, WatchAndBuyStatusState> {
  final WatchAndBuyRepo repo;

  WatchAndBuyStatusBloc({required this.repo}) : super(StatusInitial()) {
    on<FetchStatusesEvent>(_onFetchStatuses);
    on<LoadMoreStatusesEvent>(_onLoadMoreStatuses);
    on<DeleteStatusEvent>(_onDeleteStatus);
    on<CreateStatusEvent>(_onCreateStatus);
  }

  Future<void> _onFetchStatuses(
    FetchStatusesEvent event,
    Emitter<WatchAndBuyStatusState> emit,
  ) async {
    if (!event.isRefresh) {
      emit(StatusLoading());
    }
    try {
      final response = await repo.getStatuses(perPage: 15);
      emit(StatusLoaded(
        statuses: response.items,
        pagination: response.pagination,
        hasMore: response.pagination.hasMorePages,
      ));
    } catch (e) {
      emit(StatusError(e.toString().replaceAll('Exception:', '').trim()));
    }
  }

  Future<void> _onLoadMoreStatuses(
    LoadMoreStatusesEvent event,
    Emitter<WatchAndBuyStatusState> emit,
  ) async {
    if (state is! StatusLoaded) return;
    final currentState = state as StatusLoaded;
    if (!currentState.hasMore || currentState.pagination.nextCursor == null) return;

    emit(StatusLoadingMore(
      currentStatuses: currentState.statuses,
      pagination: currentState.pagination,
    ));

    try {
      final response = await repo.getStatuses(
        cursor: currentState.pagination.nextCursor,
        perPage: 15,
      );

      final updatedStatuses = List<SellerStatusModel>.from(currentState.statuses)..addAll(response.items);

      emit(StatusLoaded(
        statuses: updatedStatuses,
        pagination: response.pagination,
        hasMore: response.pagination.hasMorePages,
      ));
    } catch (e) {
      emit(currentState);
    }
  }

  Future<void> _onDeleteStatus(
    DeleteStatusEvent event,
    Emitter<WatchAndBuyStatusState> emit,
  ) async {
    List<SellerStatusModel> previousStatuses = [];
    CursorPaginationModel? previousPagination;
    bool previousHasMore = false;

    if (state is StatusLoaded) {
      final s = state as StatusLoaded;
      previousStatuses = s.statuses;
      previousPagination = s.pagination;
      previousHasMore = s.hasMore;
    }

    emit(StatusActionInProgress(
      'Deleting status...',
      currentStatuses: previousStatuses,
      deletingStatusId: event.statusId,
    ));
    try {
      await repo.deleteStatus(event.statusId);
      final updatedStatuses = previousStatuses.where((s) => s.id != event.statusId).toList();
      emit(StatusActionSuccess('Status deleted successfully', currentStatuses: updatedStatuses));
      if (previousPagination != null) {
        emit(StatusLoaded(
          statuses: updatedStatuses,
          pagination: previousPagination,
          hasMore: previousHasMore,
        ));
      } else {
        add(const FetchStatusesEvent());
      }
    } catch (e) {
      emit(StatusError(e.toString().replaceAll('Exception:', '').trim()));
      if (previousPagination != null) {
        emit(StatusLoaded(
          statuses: previousStatuses,
          pagination: previousPagination,
          hasMore: previousHasMore,
        ));
      }
    }
  }

  Future<void> _onCreateStatus(
    CreateStatusEvent event,
    Emitter<WatchAndBuyStatusState> emit,
  ) async {
    emit(const StatusActionInProgress('Creating status...'));
    try {
      await repo.createStatus(
        text: event.text,
        media: event.media,
        profileId: event.profileId,
        attachmentIds: event.attachmentIds,
      );
      emit(const StatusActionSuccess('Status created successfully!'));
      add(const FetchStatusesEvent(isRefresh: true));
    } catch (e) {
      emit(StatusError(e.toString().replaceAll('Exception:', '').trim()));
    }
  }
}
