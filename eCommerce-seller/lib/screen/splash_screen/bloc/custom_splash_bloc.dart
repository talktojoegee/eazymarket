import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../services/splash/splash_storage_service.dart';
import '../repo/custom_splash_repo.dart';
import 'custom_splash_event.dart';
import 'custom_splash_state.dart';

class CustomSplashBloc extends Bloc<CustomSplashEvent, CustomSplashState> {
  final CustomSplashRepo _repo;

  CustomSplashBloc({CustomSplashRepo? repo})
      : _repo = repo ?? CustomSplashRepo(),
        super(CustomSplashInitial()) {
    on<FetchCustomSplashRequest>(_onFetchCustomSplash);
  }

  Future<void> _onFetchCustomSplash(
    FetchCustomSplashRequest event,
    Emitter<CustomSplashState> emit,
  ) async {
    try {
      emit(CustomSplashLoading());
      final config = await _repo.fetchCustomSplash();
      if (config != null) {
        final wasUpdated = await SplashStorageService.onSettingsSplashReceived(config);
        emit(CustomSplashLoaded(config: config, wasStorageUpdated: wasUpdated));
      } else {
        emit(const CustomSplashFailure(error: 'No splash screen configuration received'));
      }
    } catch (e) {
      emit(CustomSplashFailure(error: e.toString()));
    }
  }
}
