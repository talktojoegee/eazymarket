part of 'onboarding_bloc.dart';

abstract class OnboardingState extends Equatable {
  const OnboardingState();

  @override
  List<Object> get props => [];
}

class OnboardingInitial extends OnboardingState {}

class OnboardingLoading extends OnboardingState {}

class OnboardingSuccess extends OnboardingState {
  final List<OnboardingData> onboardingData;

  const OnboardingSuccess({required this.onboardingData});

  @override
  List<Object> get props => [onboardingData];
}

class OnboardingFailure extends OnboardingState {
  final String error;

  const OnboardingFailure({required this.error});

  @override
  List<Object> get props => [error];
}
