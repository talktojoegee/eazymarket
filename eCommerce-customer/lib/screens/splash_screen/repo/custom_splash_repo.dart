import 'package:hyper_commerce/config/api_base_helper.dart';
import 'package:hyper_commerce/config/api_routes.dart';
import 'package:hyper_commerce/config/helper.dart';
import '../model/custom_splash_model.dart';

class CustomSplashRepo {
  Future<CustomerAppSplashConfig?> fetchCustomSplash() async {
    try {
      final response = await AppHelpers.apiBaseHelper.getAPICall(
        '${ApiRoutes.fetchCustomSplash}?surface=customer_app',
        {},
      );
      if (response.statusCode == 200 && response.data != null) {
        if (response.data is Map<String, dynamic>) {
          return CustomerAppSplashConfig.fromJson(response.data as Map<String, dynamic>);
        } else if (response.data is Map) {
          return CustomerAppSplashConfig.fromJson(Map<String, dynamic>.from(response.data as Map));
        }
      }
      return null;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
