import 'package:equatable/equatable.dart';

abstract class CustomSplashEvent extends Equatable {
  const CustomSplashEvent();

  @override
  List<Object?> get props => [];
}

class FetchCustomSplashRequest extends CustomSplashEvent {}
