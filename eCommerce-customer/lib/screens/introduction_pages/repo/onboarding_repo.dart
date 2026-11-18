import 'package:hyper_commerce/config/api_base_helper.dart';
import 'package:hyper_commerce/config/api_routes.dart';
import 'package:hyper_commerce/config/helper.dart';

class OnboardingRepo {
  Future<Map<String, dynamic>> fetchOnboarding () async {
    try{
      final response = await AppHelpers.apiBaseHelper.getAPICall(
          '${ApiRoutes.fetchOnboardingPages}?surface=customer_app',
          {},
      );
      return response.data;
    }catch(e) {
      throw ApiException(e.toString());
    }
  }
}