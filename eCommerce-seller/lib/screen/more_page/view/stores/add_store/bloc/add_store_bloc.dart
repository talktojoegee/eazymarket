import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/add_store/bloc/add_store_state.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/add_store/repo/add_store_repo.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';

part 'add_store_event.dart';

class AddStoreBloc extends Bloc<AddStoreEvent, AddStoreState> {
  final AddStoreRepository repository;

  AddStoreBloc(this.repository) : super(AddStoreState(formData: {})) {
    // 1. Update form fields
    on<UpdateStoreField>((event, emit) {
      final currentFormData = state.formData;
      bool isChanged = false;

      event.fields.forEach((key, value) {
        if (currentFormData[key] != value) {
          isChanged = true;
        }
      });

      if (isChanged) {
        final newFormData = Map<String, dynamic>.from(currentFormData)
          ..addAll(event.fields);
        emit(state.copyWith(formData: newFormData));
      }
    });

    // 2. Clear state
    on<ClearAddStoreState>((event, emit) {
      emit(AddStoreState(formData: {}));
    });

    on<ResetMessages>((event, emit) {
      emit(state.copyWith(successMessage: null, errorMessage: null));
    });

    // 3. Initialize edit mode
    on<InitializeEditStore>((event, emit) async {
      emit(state.copyWith(isLoading: true, isEditMode: true));

      // Markets model: the store carries no bank/address/documents — those live
      // on the seller profile + StoreLocation now. Edit only touches the
      // store's own fields + market reach.
      final Map<String, dynamic> initialData = {
        'name': event.store.name,
        'contact_email': event.store.contactEmail,
        'contact_number': event.store.contactNumber,
        'tax_name': event.store.taxName,
        'tax_number': event.store.taxNumber,
        'market_ids': event.store.markets.map((m) => m.id).toList(),
      };

      if (event.store.logo != null && event.store.logo!.isNotEmpty) {
        final logoPath = await ImagePickerUtils.downloadImageToFile(
          event.store.logo!,
        );
        if (logoPath != null) {
          initialData['store_logo'] = logoPath;
        }
      }

      if (event.store.banner != null && event.store.banner!.isNotEmpty) {
        final bannerPath = await ImagePickerUtils.downloadImageToFile(
          event.store.banner!,
        );
        if (bannerPath != null) {
          initialData['store_banner'] = bannerPath;
        }
      }

      emit(state.copyWith(formData: initialData, isLoading: false));
    });

    // 4. Submit store (Add/Edit)
    on<SubmitStore>((event, emit) async {
      emit(state.copyWith(isLoading: true));
      try {
        final Map<String, dynamic> raw = Map.from(state.formData);

        // Multipart body for the markets-model store contract:
        //   scalar fields + market_ids[] + nested location[...] + logo/banner.
        final formData = FormData();

        // market_ids[] — repeated entries so Laravel reads an array.
        final markets = raw.remove('market_ids');
        if (markets is List) {
          for (final id in markets) {
            formData.fields.add(MapEntry('market_ids[]', id.toString()));
          }
        }

        // File fields.
        for (final key in ['store_logo', 'store_banner']) {
          final v = raw.remove(key);
          if (v is String && v.isNotEmpty && !v.startsWith('http')) {
            formData.files.add(MapEntry(key, await MultipartFile.fromFile(v)));
          }
        }
        // Drop any legacy file keys that no longer belong on the store.
        raw.remove('address_proof');
        raw.remove('voided_check');

        if (event.isEdit) {
          formData.fields.add(const MapEntry('_method', 'POST'));
        }

        // Remaining scalar / nested keys (location[...] keys already namespaced
        // by the location step) go through as-is.
        raw.forEach((key, value) {
          if (value != null) formData.fields.add(MapEntry(key, value.toString()));
        });

        if (event.isEdit) {
          await repository.updateStore(event.storeId!, formData);
          emit(
            state.copyWith(
              isLoading: false,
              successMessage: "Store updated successfully",
            ),
          );
        } else {
          await repository.createStore(formData);
          emit(
            state.copyWith(
              isLoading: false,
              successMessage: "Store registered successfully",
            ),
          );
        }
      } catch (e) {
        emit(state.copyWith(isLoading: false, errorMessage: e.toString()));
      }
    });

    // 5. Delete store
    on<DeleteStore>((event, emit) async {
      emit(state.copyWith(isLoading: true));
      try {
        await repository.deleteStore(event.storeId);
        emit(
          state.copyWith(
            isLoading: false,
            successMessage: "Store deleted successfully",
          ),
        );
      } catch (e) {
        emit(state.copyWith(isLoading: false, errorMessage: e.toString()));
      }
    });

  }
}
