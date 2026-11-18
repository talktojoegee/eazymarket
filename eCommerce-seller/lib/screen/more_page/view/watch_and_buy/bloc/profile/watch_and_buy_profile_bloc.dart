import 'dart:io';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../model/watch_and_buy_profile_model.dart';
import '../../repo/watch_and_buy_repo.dart';

// ─── Events ─────────────────────────────────────────────────────

abstract class WatchAndBuyProfileEvent extends Equatable {
  const WatchAndBuyProfileEvent();
  @override
  List<Object?> get props => [];
}

class FetchProfileEvent extends WatchAndBuyProfileEvent {
  const FetchProfileEvent();
}

class SaveProfileEvent extends WatchAndBuyProfileEvent {
  final String username;
  final String bio;
  final File? photo;

  const SaveProfileEvent({
    required this.username,
    required this.bio,
    this.photo,
  });

  @override
  List<Object?> get props => [username, bio, photo?.path];
}

// ─── States ─────────────────────────────────────────────────────

abstract class WatchAndBuyProfileState extends Equatable {
  const WatchAndBuyProfileState();
  @override
  List<Object?> get props => [];
}

class ProfileInitial extends WatchAndBuyProfileState {}

class ProfileLoading extends WatchAndBuyProfileState {}

class ProfileLoaded extends WatchAndBuyProfileState {
  final WatchAndBuyProfileModel? profile;
  final bool hasProfile;

  const ProfileLoaded({
    this.profile,
    required this.hasProfile,
  });

  @override
  List<Object?> get props => [profile?.id, profile?.username, hasProfile];
}

class ProfileSaving extends WatchAndBuyProfileState {}

class ProfileSaveSuccess extends WatchAndBuyProfileState {
  final WatchAndBuyProfileModel profile;
  final String message;

  const ProfileSaveSuccess({
    required this.profile,
    this.message = 'Profile saved successfully',
  });

  @override
  List<Object?> get props => [profile.id, profile.username, message];
}

class ProfileError extends WatchAndBuyProfileState {
  final String error;

  const ProfileError(this.error);

  @override
  List<Object?> get props => [error];
}

// ─── Bloc ───────────────────────────────────────────────────────

class WatchAndBuyProfileBloc extends Bloc<WatchAndBuyProfileEvent, WatchAndBuyProfileState> {
  final WatchAndBuyRepo repo;

  WatchAndBuyProfileBloc({required this.repo}) : super(ProfileInitial()) {
    on<FetchProfileEvent>(_onFetchProfile);
    on<SaveProfileEvent>(_onSaveProfile);
  }

  Future<void> _onFetchProfile(
    FetchProfileEvent event,
    Emitter<WatchAndBuyProfileState> emit,
  ) async {
    emit(ProfileLoading());
    try {
      final profile = await repo.getProfile();
      final hasProfile = profile != null && (profile.username != null && profile.username!.isNotEmpty);
      emit(ProfileLoaded(profile: profile, hasProfile: hasProfile));
    } catch (e) {
      emit(ProfileError(e.toString().replaceAll('Exception:', '').trim()));
    }
  }

  Future<void> _onSaveProfile(
    SaveProfileEvent event,
    Emitter<WatchAndBuyProfileState> emit,
  ) async {
    emit(ProfileSaving());
    try {
      final profile = await repo.saveProfile(
        username: event.username,
        bio: event.bio,
        photo: event.photo,
      );
      emit(ProfileSaveSuccess(profile: profile));
      emit(ProfileLoaded(profile: profile, hasProfile: true));
    } catch (e) {
      emit(ProfileError(e.toString().replaceAll('Exception:', '').trim()));
    }
  }
}
