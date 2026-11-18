import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';

class OnboardingRepo {
  final ApiBaseHelper _apiBaseHelper;

  OnboardingRepo({ApiBaseHelper? apiBaseHelper})
      : _apiBaseHelper = apiBaseHelper ?? ApiBaseHelper();

  Future<Map<String, dynamic>> fetchOnboarding() async {
    try {
      final response = await _apiBaseHelper.get(
        '${ApiRoutes.fetchOnboardingPages}?surface=seller_app',
      );
      if (response is Map<String, dynamic>) {
        return response;
      }
      return <String, dynamic>{};
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
