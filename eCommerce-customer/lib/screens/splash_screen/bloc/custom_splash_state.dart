import 'package:equatable/equatable.dart';
import '../model/custom_splash_model.dart';

abstract class CustomSplashState extends Equatable {
  const CustomSplashState();

  @override
  List<Object?> get props => [];
}

class CustomSplashInitial extends CustomSplashState {}

class CustomSplashLoading extends CustomSplashState {}

class CustomSplashLoaded extends CustomSplashState {
  final CustomerAppSplashConfig config;
  final bool wasStorageUpdated;

  const CustomSplashLoaded({
    required this.config,
    this.wasStorageUpdated = false,
  });

  @override
  List<Object?> get props => [config, wasStorageUpdated];
}

class CustomSplashFailure extends CustomSplashState {
  final String error;

  const CustomSplashFailure({required this.error});

  @override
  List<Object?> get props => [error];
}
