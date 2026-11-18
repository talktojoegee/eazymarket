import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../model/language_model/language_list_model.dart';
import '../../../repo/language_repo.dart';

part 'language_list_event.dart';
part 'language_list_state.dart';

class LanguageListBloc extends Bloc<LanguageListEvent, LanguageListState> {
  final LanguageRepo languageRepo;

  LanguageListBloc({LanguageRepo? languageRepo})
      : languageRepo = languageRepo ?? LanguageRepo(),
        super(LanguageListInitial()) {
    on<FetchLanguageList>(_onFetchLanguageList);
  }

  Future<void> _onFetchLanguageList(
    FetchLanguageList event,
    Emitter<LanguageListState> emit,
  ) async {
    // 1. Emit cached labels immediately if available for 0ms startup
    final cached = await languageRepo.getCachedLanguageLabels(event.locale);
    if (cached != null && cached.data != null) {
      final cachedLabels = cached.data?.labels ?? {};
      emit(LanguageListLoaded(
        languageLabelsModel: cached,
        labels: cachedLabels,
        locale: cached.data?.locale ?? event.locale,
        direction: cached.data?.direction ?? 'ltr',
      ));
    } else {
      emit(LanguageListLoading());
    }

    // 2. Fetch fresh labels from API
    try {
      final response = await languageRepo.fetchLanguageLabels(event.locale);
      if (response.success == true && response.data != null) {
        final freshLabels = response.data?.labels ?? {};
        emit(LanguageListLoaded(
          languageLabelsModel: response,
          labels: freshLabels,
          locale: response.data?.locale ?? event.locale,
          direction: response.data?.direction ?? 'ltr',
        ));
      } else if (state is! LanguageListLoaded) {
        emit(LanguageListFailed(
          error: response.message ?? 'Failed to load language labels',
        ));
      }
    } catch (e) {
      if (state is! LanguageListLoaded) {
        emit(LanguageListFailed(error: e.toString()));
      }
    }
  }
}

