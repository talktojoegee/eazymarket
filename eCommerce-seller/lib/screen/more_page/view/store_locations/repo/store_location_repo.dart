import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';

/// Ship-from locations CRUD. Store scope is carried by the X-Store-Id header
/// (see Security.headers); pass `all: true` to list across every store.
class StoreLocationRepo {
  final ApiBaseHelper _helper = ApiBaseHelper();

  /// Fetch ship-from locations.
  ///
  /// * [all]: when true, sends `all=1` and returns every store's locations
  ///   the seller can see (the server ignores X-Store-Id in this mode).
  /// * [storeId]: when set, overrides the app-wide store scope by sending
  ///   an explicit `X-Store-Id: $storeId` header for this request only —
  ///   `HiveStorage.selectedStoreId` is not touched. If both [all] and
  ///   [storeId] are set, [storeId] wins.
  Future<dynamic> getLocations({bool all = false, int? storeId}) async {
    try {
      final useStoreOverride = storeId != null;
      return await _helper.get(
        ApiRoutes.locationsApi,
        queryParameters: {if (all && !useStoreOverride) 'all': '1'},
        headers: useStoreOverride
            ? {'X-Store-Id': storeId.toString()}
            : null,
      );
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

  Future<dynamic> getLocation(int id) async {
    try {
      return await _helper.get(ApiRoutes.locationDetailApi(id));
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> createLocation(Map<String, dynamic> body) async {
    try {
      return await _helper.post(ApiRoutes.locationsApi, body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> updateLocation(int id, Map<String, dynamic> body) async {
    try {
      return await _helper.post(ApiRoutes.locationDetailApi(id), body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> deleteLocation(int id) async {
    try {
      return await _helper.delete(ApiRoutes.locationDetailApi(id));
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> promoteDefault(int id) async {
    try {
      return await _helper.post(ApiRoutes.promoteLocationApi(id), {});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> shiprocketSync(int id) async {
    try {
      return await _helper.post(ApiRoutes.locationShiprocketSyncApi(id), {});
    } catch (e) {
      throw Exception(e.toString());
    }
  }
}
