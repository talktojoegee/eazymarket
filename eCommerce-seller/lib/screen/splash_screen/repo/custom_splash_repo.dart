import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';
import '../model/custom_splash_model.dart';

class CustomSplashRepo {
  final ApiBaseHelper _apiHelper;

  CustomSplashRepo({ApiBaseHelper? apiHelper})
      : _apiHelper = apiHelper ?? ApiBaseHelper();

  Future<SellerAppSplashConfig?> fetchCustomSplash() async {
    try {
      final response = await _apiHelper.get(
        '${ApiRoutes.fetchCustomSplash}?surface=seller_app',
      );
      if (response != null) {
        if (response is Map<String, dynamic>) {
          return SellerAppSplashConfig.fromJson(response);
        } else if (response is Map) {
          return SellerAppSplashConfig.fromJson(
            Map<String, dynamic>.from(response),
          );
        }
      }
      return null;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
