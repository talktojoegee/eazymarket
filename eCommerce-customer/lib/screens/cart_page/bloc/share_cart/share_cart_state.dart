import 'package:hyper_commerce/screens/cart_page/model/share_cart_model.dart';

abstract class CartState {}

class ShareCartInitial extends CartState {}

class ShareCartLoading extends CartState {}

class ShareCartSuccess extends CartState {
  final ShareCartModel model;
  ShareCartSuccess(this.model);
}

class ShareCartFailure extends CartState {
  final String message;
  ShareCartFailure(this.message);
}
