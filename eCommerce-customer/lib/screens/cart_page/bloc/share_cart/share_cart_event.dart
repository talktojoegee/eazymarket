abstract class CartEvent {}

// If the API needs nothing extra
class ShareCart extends CartEvent {}

// If the API needs the cart id
class ShareUserCart extends CartEvent {
  final String cartId;
  ShareUserCart(this.cartId);
}
