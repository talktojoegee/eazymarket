import 'package:hyper_commerce_seller/config/hive_storage.dart';

class Security {
  static Future<Map<String, String>> get headers async {
    String? token = HiveStorage.userToken;

    final Map<String, String> headers = {
      "Accept": "application/json",
    };

    if (token != null && token.trim().isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    // Store scope: the seller API resolves the active store from this header
    // (middleware seller.api.store). Falls back to the seller's first store
    // server-side when absent.
    final int? storeId = HiveStorage.selectedStoreId;
    if (storeId != null) {
      headers['X-Store-Id'] = storeId.toString();
    }

    return headers;
  }
}
