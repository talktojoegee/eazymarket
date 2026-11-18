part of 'permissions_bloc.dart';

sealed class PermissionsState extends Equatable {
  const PermissionsState();

  @override
  List<Object?> get props => [];
}

final class PermissionsInitial extends PermissionsState {}

final class PermissionsLoading extends PermissionsState {}

final class PermissionsLoaded extends PermissionsState {
  final SellerPermissionsData data;
  final List<String> currentAssigned;

  /// True while a `SyncPermissions` request is in flight. The UI uses this
  /// to disable the save button (and show a spinner) without dropping to a
  /// bare loading state — which would blow away the user's tick selections.
  final bool isSyncing;

  const PermissionsLoaded(
    this.data,
    this.currentAssigned, {
    this.isSyncing = false,
  });

  @override
  List<Object?> get props => [data, currentAssigned, isSyncing];
}

final class PermissionsError extends PermissionsState {
  final String message;

  const PermissionsError(this.message);

  @override
  List<Object?> get props => [message];
}

final class PermissionsSyncSuccess extends PermissionsState {
  final String message;

  const PermissionsSyncSuccess(this.message);

  @override
  List<Object?> get props => [message];
}
