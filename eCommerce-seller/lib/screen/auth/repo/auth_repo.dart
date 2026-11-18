// ignore_for_file: unused_local_variable

import 'dart:developer';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/auth/model/registration_field_model.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';

class AuthRepository {
  final ApiBaseHelper _helper = ApiBaseHelper();

  Future<dynamic> login({
    String email = '',
    String phoneNumber = '',
    required String password,
  }) async {
    try {
      final digits = phoneNumber.replaceAll(RegExp(r'[^\d]'), '');
      final body = {
        if (email.isNotEmpty) "email": email,
        if (phoneNumber.isNotEmpty) "mobile": int.tryParse(digits) ?? phoneNumber,
        "password": password,
        "fcm_token": HiveStorage.fcmToken.toString(),
        "device_type": Platform.isAndroid ? 'android' : 'ios',
      };

      final responseData = await _helper.post(ApiRoutes.loginApi, body);

      await _extractAndSaveToken(responseData);

      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<void> delete() async {
    try {
      await _helper.delete(ApiRoutes.deleteProfileApi);
      await HiveStorage.clearAll();
    } catch (e) {
      log("Delete Error $e");
      rethrow;
      // Ignore Delete errors
    }
  }

  Future<RegistrationFieldsResponse> getRegistrationFields(String countryIso2) async {
    try {
      final responseData = await _helper.get(
        ApiRoutes.registrationFieldsApi,
        queryParameters: {"country_iso2": countryIso2},
      );
      if (responseData is Map<String, dynamic>) {
        return RegistrationFieldsResponse.fromJson(responseData);
      } else if (responseData is Map) {
        return RegistrationFieldsResponse.fromJson(Map<String, dynamic>.from(responseData));
      }
      throw Exception("Unexpected response format from registration fields API");
    } catch (e) {
      log("Error fetching registration fields: $e");
      rethrow;
    }
  }

  Future<dynamic> signUp({
    required String name,
    required String email,
    required String phone,
    required String password,
    required String address,
    required String city,
    required String state,
    required String landmark,
    required String zipcode,
    required String country,
    required String latitude,
    required String longitude,
    required String businessLicensePath,
    required String articlesOfIncorporationPath,
    required String nationalIdCardPath,
    required String authorizedSignaturePath,
    String addressProofPath = '',
    String voidedCheckPath = '',
    String bankName = '',
    String bankBranchCode = '',
    String accountHolderName = '',
    String accountNumber = '',
    String routingNumber = '',
    String bankAccountType = '',
    Map<String, dynamic>? dynamicFields,
    Map<String, String>? dynamicFiles,
  }) async {
    try {
      final Map<String, dynamic> fields = {
        "name": name,
        "email": email,
        "mobile": phone,
        "password": password,
        "user_id": "",
        "address": address,
        "city": city,
        "state": state,
        "landmark": landmark,
        "zipcode": zipcode,
        "country": country,
        "latitude": latitude,
        "longitude": longitude,
        "bank_name": bankName,
        "bank_branch_code": bankBranchCode,
        "account_holder_name": accountHolderName,
        "account_number": accountNumber,
        "routing_number": routingNumber,
        "bank_account_type": bankAccountType,
        "verification_status": "approved",
        "visibility_status": "visible",
        "fcm_token": HiveStorage.fcmToken.toString(),
        "device_type": Platform.isAndroid ? 'android' : 'ios',
      };

      if (dynamicFields != null && dynamicFields.isNotEmpty) {
        for (final entry in dynamicFields.entries) {
          fields["dynamic_fields[${entry.key}]"] = entry.value;
        }
      }

      final Map<String, dynamic> files = {
        "business_license": businessLicensePath.isNotEmpty ? await MultipartFile.fromFile(businessLicensePath) : null,
        "articles_of_incorporation": articlesOfIncorporationPath.isNotEmpty
            ? await MultipartFile.fromFile(articlesOfIncorporationPath)
            : null,
        "national_identity_card": nationalIdCardPath.isNotEmpty ? await MultipartFile.fromFile(nationalIdCardPath) : null,
        "authorized_signature": authorizedSignaturePath.isNotEmpty ? await MultipartFile.fromFile(authorizedSignaturePath) : null,
        "address_proof": addressProofPath.isNotEmpty ? await MultipartFile.fromFile(addressProofPath) : null,
        "voided_check": voidedCheckPath.isNotEmpty ? await MultipartFile.fromFile(voidedCheckPath) : null,
      };

      if (dynamicFiles != null && dynamicFiles.isNotEmpty) {
        for (final entry in dynamicFiles.entries) {
          if (entry.value.isNotEmpty) {
            files["dynamic_fields[${entry.key}]"] = await MultipartFile.fromFile(entry.value);
          }
        }
      }

      // Remove null files
      files.removeWhere((key, value) => value == null);

      final formData = FormData.fromMap({...fields, ...files});

      for (var field in formData.fields) {}

      for (var file in formData.files) {}

      final responseData = await _helper.postMultipart(ApiRoutes.registerApi, formData);

      await _extractAndSaveToken(responseData);

      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<void> logout() async {
    try {
      await _helper.post(ApiRoutes.logoutApi, {"fcm_token": HiveStorage.fcmToken.toString()});
    } catch (_) {
      // Ignore logout errors
    } finally {
      await HiveStorage.clearAll();
    }
  }

  Future<dynamic> forgotPassword(String email) async {
    try {
      final body = {"email": email};

      final responseData = await _helper.post(ApiRoutes.forgetPasswordApi, body);

      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<void> _extractAndSaveToken(dynamic data) async {
    if (data is Map<String, dynamic>) {
      // Save Access Token
      if (data.containsKey('access_token')) {
        await HiveStorage.setAccessToken(data['access_token'].toString());
      }

      // Save User Data
      if (data.containsKey('data') && data['data'] is Map) {
        final Map<dynamic, dynamic> userDataToSave = Map.from(data['data']);

        // Inject assigned permissions into the userData map. Accept the
        // payload at either the top level (`data.assigned_permissions`) or
        // nested under `data.data.assigned_permissions` — different login
        // endpoints have delivered it at different levels historically, and
        // if we miss it, PermissionChecker.hasPermission returns false for
        // everything and the whole roles feature reads as broken. Also
        // guarantee the key is always a List<String> so downstream code
        // can trust the shape.
        final topLevelPerms = data['assigned_permissions'];
        final nestedPerms = userDataToSave['assigned_permissions'];
        final perms = topLevelPerms ?? nestedPerms;
        if (perms is List) {
          userDataToSave['assigned_permissions'] =
              perms.map((e) => e.toString()).toList();
        } else {
          userDataToSave['assigned_permissions'] = <String>[];
        }

        await HiveStorage.setUserData(userDataToSave);
      }

      // Legacy fallback (optional, but good for safety if API varies)
      if (data.containsKey('token')) {
        await HiveStorage.setAccessToken(data['token'].toString());
      }
    }
  }

  Future<dynamic> loginSendOtp({required String identifier}) async {
    try {
      return await _helper.post(ApiRoutes.loginSendOtpApi, {"identifier": identifier});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> loginVerifyOtp({required String identifier, required String otp}) async {
    try {
      final body = {
        "identifier": identifier,
        "otp": otp,
        "fcm_token": HiveStorage.fcmToken.toString(),
        "device_type": Platform.isAndroid ? 'android' : 'ios',
      };
      final responseData = await _helper.post(ApiRoutes.loginVerifyOtpApi, body);
      await _extractAndSaveToken(responseData);
      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Firebase gateway: the server sends no SMS, so the client completes phone
  /// auth itself and posts the resulting idToken. No `otp` is sent — the
  /// account is identified entirely by the verified token.
  Future<dynamic> loginVerifyFirebase({required String idToken}) async {
    try {
      final body = {
        "idToken": idToken,
        "fcm_token": HiveStorage.fcmToken.toString(),
        "device_type": Platform.isAndroid ? 'android' : 'ios',
      };
      final responseData = await _helper.post(ApiRoutes.loginVerifyOtpApi, body);
      await _extractAndSaveToken(responseData);
      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> forgotPasswordVerifyFirebase({required String idToken, String? identifier}) async {
    try {
      final body = {
        "idToken": idToken,
        if (identifier != null && identifier.isNotEmpty) "identifier": identifier,
      };
      return await _helper.post(ApiRoutes.forgetPasswordVerifyOtpApi, body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> forgotPasswordSendOtp({required String identifier}) async {
    try {
      return await _helper.post(ApiRoutes.forgetPasswordSendOtpApi, {"identifier": identifier});
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> forgotPasswordVerifyOtp({required String identifier, required String otp}) async {
    try {
      final body = {"identifier": identifier, "otp": otp};
      return await _helper.post(ApiRoutes.forgetPasswordVerifyOtpApi, body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> resetPassword({
    required String identifier,
    required String token,
    required String password,
  }) async {
    try {
      final body = {
        "identifier": identifier,
        "token": token,
        "password": password,
        "password_confirmation": password,
      };
      return await _helper.post(ApiRoutes.resetPasswordApi, body);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> phoneCallback({required String idToken}) async {
    try {
      final body = {
        "idToken": idToken,
        "fcm_token": HiveStorage.fcmToken.toString(),
        "device_type": Platform.isAndroid ? 'android' : 'ios',
      };

      final responseData = await _helper.post(ApiRoutes.callbackApi, body);

      await _extractAndSaveToken(responseData);

      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> verifyUser(String type, String value) async {
    try {
      final body = {"type": type, "value": value};

      final responseData = await _helper.post(ApiRoutes.verifyUserApi, body);

      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> customSendOtp({required String phone}) async {
    try {
      final body = {"mobile": phone};
      final responseData = await _helper.post(ApiRoutes.customSendOtpApi, body);
      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> customVerifyOtp({required String phone, required String otp}) async {
    try {
      final body = {
        "mobile": phone,
        "otp": otp,
        "fcm_token": HiveStorage.fcmToken.toString(),
        "device_type": Platform.isAndroid ? 'android' : 'ios',
      };
      final responseData = await _helper.post(ApiRoutes.customVerifyOtpApi, body);
      await _extractAndSaveToken(responseData);
      return responseData;
    } catch (e) {
      throw Exception(e.toString());
    }
  }
}
