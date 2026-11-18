import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';

/// Read-only markets lookup. The seller does not switch markets (prices are
/// entered in base currency); this backs the market picker on the store form,
/// where a store declares which markets it sells in.
class MarketsRepo {
  final ApiBaseHelper _helper = ApiBaseHelper();

  /// Search active markets. Returns the bare `markets/search` list shape
  /// (`[{value, text, code, name}, ...]`).
  Future<dynamic> searchMarkets({String? search, bool onlyActive = true, int limit = 20}) async {
    try {
      final queryParameters = {
        if (search != null && search.isNotEmpty) 'search': search,
        'only_active': onlyActive ? '1' : '0',
        'limit': limit.toString(),
      };

      final response = await _helper.get(ApiRoutes.marketsSearchApi, queryParameters: queryParameters);
      return response;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Pre-load specific markets by id/code (for rendering current selection on
  /// an edit form).
  Future<dynamic> findMarkets(List<String> values) async {
    try {
      final Map<String, dynamic> queryParameters = {
        'only_active': '0',
        'find_in[]': values,
      };
      final response = await _helper.get(ApiRoutes.marketsSearchApi, queryParameters: queryParameters);
      return response;
    } catch (e) {
      throw Exception(e.toString());
    }
  }
}
