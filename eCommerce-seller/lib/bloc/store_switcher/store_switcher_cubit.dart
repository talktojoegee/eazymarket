// ignore_for_file: depend_on_referenced_packages

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:equatable/equatable.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/repo/store_repo.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:collection/collection.dart';

class StoreSwitcherState extends Equatable {
  final List<Store> stores;
  final Store? selectedStore;
  final bool isLoading;

  const StoreSwitcherState({
    required this.stores,
    this.selectedStore,
    this.isLoading = false,
  });

  factory StoreSwitcherState.initial() => const StoreSwitcherState(
        stores: [],
        selectedStore: null,
      );

  StoreSwitcherState copyWith({
    List<Store>? stores,
    Store? selectedStore,
    bool? isLoading,
  }) {
    return StoreSwitcherState(
      stores: stores ?? this.stores,
      selectedStore: selectedStore ?? this.selectedStore,
      isLoading: isLoading ?? this.isLoading,
    );
  }

  @override
  List<Object?> get props => [stores, selectedStore, isLoading];
}

class StoreSwitcherCubit extends Cubit<StoreSwitcherState> {
  final StoresRepo _repo;

  StoreSwitcherCubit(this._repo) : super(StoreSwitcherState.initial()) {
    loadStores();
  }

  Future<void> loadStores() async {
    emit(state.copyWith(isLoading: true));
    try {
      final response = await _repo.getStores(
        page: 1, 
        perPage: 50, // Keep 50 for switcher to show all usually
      );
      
      final storesResponse = StoresResponse.fromJson(response);
      final stores = storesResponse.data?.stores ?? [];

      // Valid = approved + visible. Stores no longer carry an online/offline
      // status in the markets model, so availability is not gated here.
      final validStores = stores.where((s) =>
        s.verificationStatus != 'not_approved' &&
        s.visibilityStatus != 'draft'
      ).toList();

      // Try to find the last selected store
      final lastSelectedStoreId = HiveStorage.selectedStoreId;
      var selectedStore = stores.firstWhereOrNull((s) => s.id == lastSelectedStoreId);

      // If last selected store is NOT valid (e.g. it's a draft), pick a valid one
      if (selectedStore != null) {
         bool isValid = selectedStore.verificationStatus != 'not_approved' &&
                        selectedStore.visibilityStatus != 'draft';
         if (!isValid) {
           selectedStore = validStores.isNotEmpty ? validStores.first : null;
           if (selectedStore != null) {
             HiveStorage.setSelectedStoreId(selectedStore.id); 
           } else {
             // If no valid store exists, clear the stored ID so UI can show "Add Store" or force selection
             HiveStorage.setSelectedStoreId(null);
           }
         }
      } else {
        selectedStore = validStores.isNotEmpty ? validStores.first : null;
        if (selectedStore != null) {
          HiveStorage.setSelectedStoreId(selectedStore.id);
        } else {
          HiveStorage.setSelectedStoreId(null);
        }
      }

      emit(state.copyWith(
        stores: stores,
        selectedStore: selectedStore,
        isLoading: false,
      ));
    } catch (e) {
      // If error occurs, we still want to set isLoading to false 
      // and ensure stores is at least the current list (even if empty)
      emit(state.copyWith(isLoading: false, stores: state.stores));
    }
  }

  /// Adopt a store as the active scope. Validates with the server
  /// (POST stores/switch) so a store the seller can't access is rejected, then
  /// persists the id — every later request carries it as X-Store-Id.
  Future<void> selectStore(Store store) async {
    final previous = state.selectedStore;
    await HiveStorage.setSelectedStoreId(store.id);
    emit(state.copyWith(selectedStore: store));

    try {
      await _repo.switchStore(store.id);
    } catch (_) {
      // Server rejected the store — revert to the previous selection.
      await HiveStorage.setSelectedStoreId(previous?.id);
      emit(state.copyWith(selectedStore: previous));
    }
  }

  /// Gracefully handle store deletion. If the deleted store was the active one,
  /// immediately falls back to another valid store (or clears if none), updates
  /// HiveStorage, informs the backend, and synchronizes the store list.
  Future<void> onStoreDeleted(int deletedStoreId) async {
    // 1. Remove deleted store from current list immediately
    final remainingStores =
        state.stores.where((s) => s.id != deletedStoreId).toList();

    final wasSelected = state.selectedStore?.id == deletedStoreId ||
        HiveStorage.selectedStoreId == deletedStoreId ||
        HiveStorage.selectedStoreId == null;

    if (wasSelected) {
      final validStores = remainingStores
          .where((s) =>
              s.verificationStatus != 'not_approved' &&
              s.visibilityStatus != 'draft')
          .toList();

      final nextStore = validStores.isNotEmpty ? validStores.first : null;

      await HiveStorage.setSelectedStoreId(nextStore?.id);

      emit(state.copyWith(
        stores: remainingStores,
        selectedStore: nextStore,
      ));

      if (nextStore != null) {
        try {
          await _repo.switchStore(nextStore.id);
        } catch (_) {}
      }
    } else {
      emit(state.copyWith(stores: remainingStores));
    }

    // 2. Synchronize store list with server
    await loadStores();
  }

  void clear() {
    emit(StoreSwitcherState.initial());
  }
}

