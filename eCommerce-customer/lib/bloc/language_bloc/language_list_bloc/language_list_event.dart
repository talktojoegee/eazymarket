part of 'language_list_bloc.dart';

abstract class LanguageListEvent extends Equatable {
  const LanguageListEvent();

  @override
  // TODO: implement props
  List<Object?> get props => [];
}

class FetchLanguageList extends LanguageListEvent {
  final String locale;
  const FetchLanguageList({required this.locale});
  @override
  // TODO: implement props
  List<Object?> get props => [locale];
}
