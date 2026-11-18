import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../model/onboarding_model.dart';
import '../repo/onboarding_repo.dart';

part 'onboarding_event.dart';
part 'onboarding_state.dart';

class OnboardingBloc extends Bloc<OnboardingEvent, OnboardingState> {
  final OnboardingRepo _onboardingRepo;

  OnboardingBloc({OnboardingRepo? onboardingRepo})
      : _onboardingRepo = onboardingRepo ?? OnboardingRepo(),
        super(OnboardingInitial()) {
    on<FetchOnboardingRequest>(_onFetchOnboardingRequest);
  }

  Future<void> _onFetchOnboardingRequest(
    FetchOnboardingRequest event,
    Emitter<OnboardingState> emit,
  ) async {
    try {
      emit(OnboardingLoading());
      List<OnboardingData> onboardingData = [];

      final response = await _onboardingRepo.fetchOnboarding();
      if (response['data'] != null && response['data'] is List) {
        onboardingData = List<OnboardingData>.from(
          (response['data'] as List).map(
            (data) => OnboardingData.fromJson(Map<String, dynamic>.from(data)),
          ),
        );
      }

      if (onboardingData.isNotEmpty && response['success'] == true) {
        emit(OnboardingSuccess(
          onboardingData: onboardingData,
        ));
      } else if (response['success'] == false) {
        emit(OnboardingFailure(
          error: response['message']?.toString() ?? 'Failed to load onboarding screens',
        ));
      } else {
        emit(OnboardingFailure(
          error: response['message']?.toString() ?? 'No onboarding screens found',
        ));
      }
    } catch (e) {
      emit(OnboardingFailure(error: e.toString()));
    }
  }
}
