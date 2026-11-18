import 'package:dio/dio.dart';
import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';

class OrdersRepo {
  final ApiBaseHelper _helper = ApiBaseHelper();

  Future<dynamic> getOrders({
    int? page,
    int? perPage,
    String? search,
    String? paymentType,
    String? range,
    String? sortBy,
    String? sortDir,
    String? status,
    String? fulfillmentMethod,
    int? storeId,
  }) async {
    try {
      final queryParameters = {
        if (page != null) 'page': page.toString(),
        if (perPage != null) 'per_page': perPage.toString(),
        if (search != null && search.isNotEmpty) 'search': search,
        if (paymentType != null && paymentType.isNotEmpty)
          'payment_type': paymentType,
        if (range != null && range.isNotEmpty) 'range': range,
        if (sortBy != null && sortBy.isNotEmpty) 'sort_by': sortBy,
        if (sortDir != null && sortDir.isNotEmpty) 'sort_dir': sortDir,
        if (status != null && status.isNotEmpty) 'status': status,
        if (fulfillmentMethod != null && fulfillmentMethod.isNotEmpty)
          'fulfillment_method': fulfillmentMethod,
        'store_id': storeId ?? HiveStorage.selectedStoreId,
      };

      final response = await _helper.get(
        ApiRoutes.ordersApi,
        queryParameters: queryParameters,
      );

      return response;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> getOrderDetails(int sellerOrderId) async {
    try {
      final response = await _helper.get(
        '${ApiRoutes.ordersApi}/$sellerOrderId',
      );
      return response;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  // ── Fulfillment / shipments (markets model) ──────────────────────────────

  /// Panel-parity fulfillment card: lines, shipments with capability flags,
  /// method label, ship-by, package defaults.
  Future<dynamic> getOrderFulfillment(int sellerOrderId) async {
    try {
      return await _helper.get(ApiRoutes.orderFulfillmentApi(sellerOrderId));
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Earnings & settlement breakdown for one seller order.
  Future<dynamic> getOrderEarnings(int sellerOrderId) async {
    try {
      return await _helper.get(ApiRoutes.orderEarningsApi(sellerOrderId));
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Returns raised against this seller order only.
  Future<dynamic> getOrderReturns(int sellerOrderId) async {
    try {
      return await _helper.get(ApiRoutes.orderReturnsForOrderApi(sellerOrderId));
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> getOrderItems(int sellerOrderId) async {
    try {
      return await _helper.get(ApiRoutes.orderItemsApi(sellerOrderId));
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Reject a single order line. The line is keyed by [orderItemId] in the
  /// path; the backend requires a non-empty [reason].
  Future<dynamic> cancelOrderItem(int orderItemId, {required String reason}) async {
    try {
      return await _helper.post(ApiRoutes.cancelOrderItemApi(orderItemId), {'reason': reason});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> fulfillOrder(int sellerOrderId, Map<String, dynamic> body) async {
    try {
      return await _helper.post(ApiRoutes.fulfillOrderApi(sellerOrderId), body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> setFulfillmentStatus(int sellerOrderId, String status) async {
    try {
      return await _helper.post(ApiRoutes.orderFulfillmentStatusApi(sellerOrderId), {'status': status});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> restockOrder(int sellerOrderId, List<int> itemIds) async {
    try {
      return await _helper.post(ApiRoutes.restockOrderApi(sellerOrderId), {'item_ids': itemIds});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> transitionShipment(int shipmentId, String status, {Map<String, dynamic>? extra}) async {
    try {
      return await _helper.post(ApiRoutes.shipmentTransitionApi(shipmentId), {'status': status, ...?extra});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> syncShipment(int shipmentId) async {
    try {
      return await _helper.post(ApiRoutes.shipmentSyncApi(shipmentId), {});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> retryShipmentBooking(int shipmentId) async {
    try {
      return await _helper.post(ApiRoutes.shipmentRetryBookingApi(shipmentId), {});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> editShipment(int shipmentId, Map<String, dynamic> body) async {
    try {
      return await _helper.post(ApiRoutes.shipmentEditApi(shipmentId), body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Re-pick the lines/dims of a PENDING Shiprocket parcel. `items` is
  /// required here (unlike fulfill, where an empty body ships everything).
  Future<dynamic> updateShipmentLines(int shipmentId, Map<String, dynamic> body) async {
    try {
      return await _helper.post(ApiRoutes.shipmentUpdateLinesApi(shipmentId), body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> ndrShipmentAction(int shipmentId, Map<String, dynamic> body) async {
    try {
      return await _helper.post(ApiRoutes.shipmentNdrActionApi(shipmentId), body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  // ── Returns ──────────────────────────────────────────────────────────────

  Future<dynamic> getReturns({int? page, int? perPage, String? returnStatus, String? search}) async {
    try {
      final queryParameters = {
        if (page != null) 'page': page.toString(),
        if (perPage != null) 'per_page': perPage.toString(),
        if (returnStatus != null && returnStatus.isNotEmpty) 'return_status': returnStatus,
        if (search != null && search.isNotEmpty) 'search': search,
      };
      return await _helper.get(ApiRoutes.orderReturnsApi, queryParameters: queryParameters);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> getReturnDetail(int returnId) async {
    try {
      return await _helper.get(ApiRoutes.orderReturnDetailApi(returnId));
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> approveReturn(int returnId, Map<String, dynamic> body) async {
    try {
      return await _helper.post(ApiRoutes.orderReturnApproveApi(returnId), body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Approve with an attached return label. The panel accepts pdf/png/jpg up
  /// to 5 MB on `return_label`, so the request has to go out as multipart.
  Future<dynamic> approveReturnWithLabel(
    int returnId,
    Map<String, dynamic> body,
    String labelPath,
  ) async {
    try {
      final fields = <String, dynamic>{};
      body.forEach((key, value) {
        if (value == null) return;
        fields[key] = value is bool ? (value ? 1 : 0) : value;
      });
      fields['return_label'] = await MultipartFile.fromFile(labelPath);

      return await _helper.postMultipart(
        ApiRoutes.orderReturnApproveApi(returnId),
        FormData.fromMap(fields),
      );
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> declineReturn(int returnId, {String? reason}) async {
    try {
      return await _helper.post(ApiRoutes.orderReturnDeclineApi(returnId), {
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> getOrderFilters() async {
    try {
      final response = await _helper.get(ApiRoutes.ordersEnumsApi);
      return response;
    } catch (e) {
      throw Exception(e.toString());
    }
  }
}
