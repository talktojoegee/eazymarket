import 'dart:developer';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:curl_logger_dio_interceptor/curl_logger_dio_interceptor.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/bloc/store_switcher/store_switcher_cubit.dart';
import 'package:hyper_commerce_seller/config/global_keys.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/service/security.dart';
import 'package:pretty_dio_logger/pretty_dio_logger.dart';

enum ApiStatus { initial, loading, success, failure, loadingMore }

class ApiException implements Exception {
  ApiException(this.errorMessage);

  final String errorMessage;

  @override
  String toString() {
    return errorMessage;
  }
}

class ApiBaseHelper {
  final Dio _dio = Dio();

  static bool _isLoggingOut = false;

  static const Set<String> _preAuthRoutes = {
    '/', // splash
    '/login',
    '/signup',
    '/awaiting-approval',
    '/forgot-password',
    '/otp',
    '/otp-login',
  };

  static bool _isOnPreAuthRoute() {
    try {
      final context = GlobalKeys.navigatorKey.currentContext;
      if (context == null) return false;
      final location =
          GoRouter.of(context).routerDelegate.currentConfiguration.uri.path;
      return _preAuthRoutes.contains(location);
    } catch (_) {
      return false;
    }
  }

  ApiBaseHelper() {
    // Enable request logging interceptor
    // _dio.interceptors.add(
    //   PrettyDioLogger(
    //     requestHeader: true,
    //     requestBody: true,
    //     responseBody: true,
    //     responseHeader: false,
    //     error: true,
    //     compact: true,
    //     maxWidth: 120,
    //   ),
    // );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final headers = await Security.headers;
          headers.forEach((k, v) => options.headers.putIfAbsent(k, () => v));
          return handler.next(options);
        },
        onError: (DioException e, handler) async {
          if (e.response?.statusCode == 401 &&
              !_isLoggingOut &&
              !_isOnPreAuthRoute()) {
            _isLoggingOut = true;
            log('401 Unauthorized — clearing user data and redirecting to login');

            // Clear all stored user data (token, profile, subscription, etc.)
            await HiveStorage.clearAll();

            // Navigate to login page
            final context = GlobalKeys.navigatorKey.currentContext;
            if (context != null && context.mounted) {
              GoRouter.of(context).go('/login');
            }

            _isLoggingOut = false;
            return handler.next(e);
          }

          // Handle "Store not found" error globally when a store ID is active
          final responseData = e.response?.data;
          String? errorMessage;
          if (responseData is Map<String, dynamic>) {
            errorMessage = responseData['message']?.toString();
          } else if (responseData is String) {
            errorMessage = responseData;
          }

          final isStoreNotFound = errorMessage != null &&
              errorMessage.toLowerCase().contains('store not found');

          if (isStoreNotFound && HiveStorage.selectedStoreId != null) {
            final failedStoreId = HiveStorage.selectedStoreId;
            log('ApiBaseHelper: "Store not found" for storeId $failedStoreId — resetting selected store');
            await HiveStorage.setSelectedStoreId(null);

            final context = GlobalKeys.navigatorKey.currentContext;
            if (context != null && context.mounted) {
              try {
                context.read<StoreSwitcherCubit>().loadStores();
              } catch (_) {}
            }

            // Retry request once without the invalid X-Store-Id header
            try {
              final options = e.requestOptions;
              options.headers.remove('X-Store-Id');
              final response = await _dio.fetch(options);
              return handler.resolve(response);
            } catch (_) {
              // If retry fails, proceed with standard error
            }
          }

          return handler.next(e);
        },
      ),
    );
  }

  Future<dynamic> post(String url, Map<String, dynamic> body) async {
    try {
      final response = await _dio.post(url, data: body);
      return _returnResponse(response);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> get(
    String url, {
    Map<String, dynamic>? queryParameters,
    Map<String, dynamic>? headers,
  }) async {
    try {
      final response = await _dio.get(
        url,
        queryParameters: queryParameters,
        options: headers != null ? Options(headers: headers) : null,
      );
      return _returnResponse(response);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> put(String url, Map<String, dynamic> body) async {
    try {
      final response = await _dio.put(url, data: body);
      return _returnResponse(response);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> delete(String url, {Map<String, dynamic>? body}) async {
    try {
      final response = await _dio.delete(url, data: body);
      return _returnResponse(response);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> postMultipart(String url, FormData formData) async {
    try {
      final response = await _dio.post(url, data: formData);
      return _returnResponse(response);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  dynamic _returnResponse(Response response) {
    switch (response.statusCode) {
      case 200:
      case 201:
        final responseBody = response.data;
        // Robust check for { success, message, data } structure
        if (responseBody is Map<String, dynamic>) {
          bool success =
              responseBody['success'] ?? false; // Default to false if missing
          String message = responseBody['message'] ?? "Unknown error";

          if (success) {
            // Return full response body to allow access to root level fields like 'access_token'
            return responseBody;
          } else {
            if (message.toLowerCase().contains('store not found') &&
                HiveStorage.selectedStoreId != null) {
              HiveStorage.setSelectedStoreId(null);
              final context = GlobalKeys.navigatorKey.currentContext;
              if (context != null && context.mounted) {
                try {
                  context.read<StoreSwitcherCubit>().loadStores();
                } catch (_) {}
              }
            }
            // If success is false, throw the message
            throw ApiException(message);
          }
        }

        // Fallback if structure is completely different (plain json)
        return responseBody;

      case 400:
        throw BadRequestException(response.data.toString());
      case 401:
      case 403:
        throw UnauthorisedException(response.data.toString());
      case 500:
      default:
        throw FetchDataException(
          'Error occurred while Communication with Server with StatusCode : ${response.statusCode}',
        );
    }
  }

  Exception _handleError(DioException error) {
    if (error.response != null) {
      final data = error.response?.data;
      final int? statusCode = error.response?.statusCode;

      if (data is Map<String, dynamic>) {
        // Handle validation errors (422)
        if (statusCode == 422 && data.containsKey('errors')) {
          final errors = data['errors'] as Map<String, dynamic>;
          final List<String> errorMessages = [];

          // Collect all error messages
          errors.forEach((key, value) {
            if (value is List) {
              errorMessages.addAll(value.map((e) => e.toString()));
            } else {
              errorMessages.add(value.toString());
            }
          });

          // If only one error, use the message field
          if (errorMessages.length == 1 && data.containsKey('message')) {
            return ApiException(data['message']);
          }

          // If multiple errors, show all detailed errors
          return ApiException(errorMessages.join('\n'));
        }

        // Handle other errors with message
        if (data.containsKey('message')) {
          return ApiException(data['message']); // Return backend message
        }
      }
      return ApiException("${error.response?.statusMessage}");
    } else {
      switch (error.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          return ApiException("Connection timeout");
        case DioExceptionType.unknown:
          if (error.error is SocketException) {
            return ApiException("No Internet Connection");
          }
          return ApiException("Unexpected error occurred");
        default:
          return ApiException("Something went wrong");
      }
    }
  }
}

class AppException implements Exception {
  final String? _message;
  final String? _prefix;

  AppException([this._message, this._prefix]);

  @override
  String toString() {
    return "$_prefix$_message";
  }
}

class FetchDataException extends AppException {
  FetchDataException([String? message])
      : super(message, "Error During Communication: ");
}

class BadRequestException extends AppException {
  BadRequestException([message]) : super(message, "Invalid Request: ");
}

class UnauthorisedException extends AppException {
  UnauthorisedException([message]) : super(message, "Unauthorised: ");
}

class InvalidInputException extends AppException {
  InvalidInputException([String? message]) : super(message, "Invalid Input: ");
}
