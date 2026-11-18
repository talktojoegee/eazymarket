import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce/config/api_routes.dart';
import 'package:hyper_commerce/config/global.dart';
import 'package:hyper_commerce/config/security.dart';
import 'package:hyper_commerce/screens/cart_page/model/share_cart_model.dart';

import 'share_cart_event.dart';
import 'share_cart_state.dart';

class ShareCartBloc extends Bloc<CartEvent, CartState> {
  ShareCartBloc({Dio? dio})
      : _dio = dio ??
            Dio(
              BaseOptions(
                connectTimeout: const Duration(seconds: 30),
                receiveTimeout: const Duration(seconds: 30),
                sendTimeout: const Duration(seconds: 30),
              ),
            ),
        super(ShareCartInitial()) {
    on<ShareCart>(_onShareCart);
    on<ShareUserCart>(_onShareUserCart);
  }

  final Dio _dio;

  Map<String, String> _buildHeaders() {
    final Map<String, String> reqHeaders =
        Map<String, String>.from(headers ?? const {});

    // Ensure Authorization header has the Bearer token
    final String? token = Global.token ?? Global.userData?.token;
    if (token != null && token.isNotEmpty && !reqHeaders.containsKey('Authorization')) {
      reqHeaders['Authorization'] = 'Bearer $token';
    }

    return reqHeaders;
  }

  Future<void> _onShareCart(
    ShareCart event,
    Emitter<CartState> emit,
  ) async {
    await _handleShareCart(emit, payload: {});
  }

  Future<void> _onShareUserCart(
    ShareUserCart event,
    Emitter<CartState> emit,
  ) async {
    await _handleShareCart(
      emit,
      payload: event.cartId.isNotEmpty ? {'cart_id': event.cartId} : {},
    );
  }

  Future<void> _handleShareCart(
    Emitter<CartState> emit, {
    required Map<String, dynamic> payload,
  }) async {
    emit(ShareCartLoading());
    try {
      final reqHeaders = _buildHeaders();
      final response = await _dio.post(
        ApiRoutes.shareCart,
        data: payload,
        options: Options(headers: reqHeaders),
      );

      final data = response.data;
      if (data is Map<String, dynamic>) {
        final model = ShareCartModel.fromJson(data);
        if (model.success == true) {
          emit(ShareCartSuccess(model));
        } else {
          emit(ShareCartFailure(model.message ?? 'Failed to share cart'));
        }
      } else if (data is Map) {
        final model = ShareCartModel.fromJson(Map<String, dynamic>.from(data));
        if (model.success == true) {
          emit(ShareCartSuccess(model));
        } else {
          emit(ShareCartFailure(model.message ?? 'Failed to share cart'));
        }
      } else {
        emit(ShareCartFailure('Invalid response from server'));
      }
    } on DioException catch (e) {
      String errorMessage = 'Failed to share cart';
      if (e.response != null && e.response?.data != null) {
        final errorData = e.response!.data;
        if (errorData is Map) {
          if (errorData['message'] != null &&
              errorData['message'].toString().isNotEmpty) {
            errorMessage = errorData['message'].toString();
          } else if (errorData['errors'] is Map &&
              (errorData['errors'] as Map).isNotEmpty) {
            final firstError = (errorData['errors'] as Map).values.first;
            if (firstError is List && firstError.isNotEmpty) {
              errorMessage = firstError.first.toString();
            } else {
              errorMessage = firstError.toString();
            }
          }
        }
      } else if (e.message != null && e.message!.isNotEmpty) {
        errorMessage = e.message!;
      }
      emit(ShareCartFailure(errorMessage));
    } catch (e) {
      emit(ShareCartFailure(e.toString()));
    }
  }
}
