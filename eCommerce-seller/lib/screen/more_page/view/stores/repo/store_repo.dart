import 'dart:developer';

import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';

class StoresRepo {
  final ApiBaseHelper _helper = ApiBaseHelper();

  Future<dynamic> getStores({
    int? page,
    int? perPage,
    String? search,
    String? visibilityStatus,
    String? verificationStatus,
  }) async {
    try {
      final queryParameters = {
        if (page != null) 'page': page.toString(),
        if (perPage != null) 'per_page': perPage.toString(),
        if (search != null && search.isNotEmpty) 'search': search,
        if (visibilityStatus != null) 'visibility_status': visibilityStatus,
        if (verificationStatus != null) 'verification_status': verificationStatus,
      };

      final response = await _helper.get(ApiRoutes.storesApi, queryParameters: queryParameters);

      return response;
    } catch (e) {

      throw Exception(e.toString());
    }
  }

  /// Active store + the stores the seller can switch to (already scoped to the
  /// staff member's assigned stores server-side). Sends X-Store-Id via the
  /// central interceptor.
  Future<dynamic> getContext() async {
    try {
      final response = await _helper.get(ApiRoutes.contextApi);
      return response;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Validate + adopt a store the seller wants to scope to. Server rejects a
  /// store the seller does not own/assign with 403.
  Future<dynamic> switchStore(int storeId) async {
    try {
      final response = await _helper.post(
        ApiRoutes.switchStoreApi,
        {"store_id": storeId},
      );
      return response;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> getStoreFilters() async {
    try {
      final response = await _helper.get(ApiRoutes.storesEnumsApi);
      return response;
    } catch (e) {

      throw Exception(e.toString());
    }
  }

  Future<dynamic> deleteStore(int storeId) async {
    try {
      final responseData = await _helper.delete(
        '${ApiRoutes.storesApi}/$storeId',
      );
      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Fetch stores with nested active locations, optionally filtered by [componentVariantIds].
  /// Calls GET /api/seller/stores/with-locations?component_variant_ids[]=...
  Future<dynamic> getStoresWithLocations({List<int>? componentVariantIds}) async {
    try {
      final Map<String, dynamic> queryParameters = {};
      if (componentVariantIds != null && componentVariantIds.isNotEmpty) {
        queryParameters['component_variant_ids[]'] =
            componentVariantIds.map((id) => id.toString()).toList();
      }
      return await _helper.get(
        ApiRoutes.storesWithLocationsApi,
        queryParameters: queryParameters.isNotEmpty ? queryParameters : null,
      );
    } catch (e) {
      throw Exception(e.toString());
    }
  }

}
