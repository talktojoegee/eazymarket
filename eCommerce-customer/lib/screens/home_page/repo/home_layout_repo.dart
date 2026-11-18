import 'package:hyper_commerce/config/api_base_helper.dart';

import '../../../../config/api_routes.dart';
import '../../../../config/helper.dart';

class HomeLayoutRepository {
  /// Fetches the server-driven home layout, paginated by section
  /// ([page] / [perPage]). Pass [categorySlug] for a category-scoped layout;
  /// empty fetches the global layout.
  Future<Map<String, dynamic>> fetchHomeLayout({
    String categorySlug = '',
    Map<String, dynamic>? layoutQuery,
    int page = 1,
    int perPage = 5,
  }) async {
    try {
      final query = <String, dynamic>{
        'page': page,
        'per_page': perPage,
      };
      if (layoutQuery != null && layoutQuery.isNotEmpty) {
        query.addAll(layoutQuery);
      } else if (categorySlug.isNotEmpty) {
        query['category_slug'] = categorySlug;
      }
      final response = await AppHelpers.apiBaseHelper.getAPICall(
        ApiRoutes.homeLayoutApi,
        query,
      );
      return response.data as Map<String, dynamic>;
    } catch (e) {
      throw ApiException('Failed to fetch home layout');
    }
  }
}
