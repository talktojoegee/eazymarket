import 'dart:io';
import 'package:dio/dio.dart';
import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';

import '../model/eligible_product_model.dart';
import '../model/seller_status_model.dart';
import '../model/watch_and_buy_profile_model.dart';
import '../model/watch_and_buy_reel_model.dart';

class WatchAndBuyRepo {
  final ApiBaseHelper _helper = ApiBaseHelper();

  // ─── Profile ──────────────────────────────────────────────────

  /// Get seller Watch & Buy profile
  Future<WatchAndBuyProfileModel?> getProfile() async {
    try {
      final response = await _helper.get(ApiRoutes.watchAndBuyProfileApi);
      if (response != null && (response['success'] == true || response.containsKey('data'))) {
        return WatchAndBuyProfileModel.fromJson(response);
      }
      return null;
    } catch (e) {
      // If profile not found / not created yet, return null
      return null;
    }
  }

  /// Create or update Watch & Buy profile
  Future<WatchAndBuyProfileModel> saveProfile({
    required String username,
    required String bio,
    File? photo,
  }) async {
    try {
      final Map<String, dynamic> map = {
        'username': username,
        'bio': bio,
      };

      if (photo != null && photo.existsSync()) {
        map['photo'] = await MultipartFile.fromFile(
          photo.path,
          filename: photo.path.split('/').last,
        );
      }

      final formData = FormData.fromMap(map);
      final response = await _helper.postMultipart(
        ApiRoutes.watchAndBuyProfileApi,
        formData,
      );

      if (response != null && (response['success'] == true || response.containsKey('data'))) {
        return WatchAndBuyProfileModel.fromJson(response);
      }
      throw ApiException(response?['message'] ?? 'Failed to save profile');
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  // ─── Reels ────────────────────────────────────────────────────

  /// Get paginated reels
  Future<ReelsResponse> getReels({
    int page = 1,
    int perPage = 15,
    String? status,
  }) async {
    try {
      final Map<String, dynamic> queryParams = {
        'page': page.toString(),
        'per_page': perPage.toString(),
      };

      if (status != null && status.isNotEmpty && status.toLowerCase() != 'all') {
        queryParams['status'] = status.toLowerCase();
      }

      final response = await _helper.get(
        ApiRoutes.watchAndBuyReelsApi,
        queryParameters: queryParams,
      );

      return ReelsResponse.fromJson(response);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Get single reel by ID
  Future<WatchAndBuyReelModel> getSingleReel(int reelId) async {
    try {
      final response = await _helper.get(ApiRoutes.watchAndBuyReelDetailApi(reelId));
      final data = response['data'] is Map<String, dynamic> ? response['data'] : response;
      return WatchAndBuyReelModel.fromJson(data);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Create a new reel
  Future<dynamic> createReel({
    String? caption,
    String source = 'seller',
    int? profileId,
    File? video,
    File? cover,
    required int primaryAttachmentId,
    required List<int> attachmentIds,
  }) async {
    try {
      final Map<String, dynamic> map = {
        'source': source,
        'primary_attachment_id': primaryAttachmentId,
      };

      if (caption != null && caption.isNotEmpty) {
        map['caption'] = caption;
      }
      if (profileId != null) {
        map['profile_id'] = profileId;
      }

      // Add attachment IDs
      final uniqueAttachmentIds = attachmentIds.where((id) => id > 0).toSet().toList();
      for (int i = 0; i < uniqueAttachmentIds.length; i++) {
        map['attachment_ids[$i]'] = uniqueAttachmentIds[i];
      }

      if (video != null && video.existsSync()) {
        map['video'] = await MultipartFile.fromFile(
          video.path,
          filename: video.path.split('/').last,
        );
      }

      if (cover != null && cover.existsSync()) {
        map['cover'] = await MultipartFile.fromFile(
          cover.path,
          filename: cover.path.split('/').last,
        );
      }

      final formData = FormData.fromMap(map);
      return await _helper.postMultipart(ApiRoutes.watchAndBuyReelsApi, formData);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Update an existing reel
  Future<dynamic> updateReel({
    required int reelId,
    String? caption,
    String source = 'seller',
    int? profileId,
    File? video,
    File? cover,
    required int primaryAttachmentId,
    required List<int> attachmentIds,
  }) async {
    try {
      final Map<String, dynamic> map = {
        'source': source,
        'primary_attachment_id': primaryAttachmentId,
      };

      if (caption != null) {
        map['caption'] = caption;
      }
      if (profileId != null) {
        map['profile_id'] = profileId;
      }

      final uniqueAttachmentIds = attachmentIds.where((id) => id > 0).toSet().toList();
      for (int i = 0; i < uniqueAttachmentIds.length; i++) {
        map['attachment_ids[$i]'] = uniqueAttachmentIds[i];
      }

      if (video != null && video.existsSync()) {
        map['video'] = await MultipartFile.fromFile(
          video.path,
          filename: video.path.split('/').last,
        );
      }

      if (cover != null && cover.existsSync()) {
        map['cover'] = await MultipartFile.fromFile(
          cover.path,
          filename: cover.path.split('/').last,
        );
      }

      final formData = FormData.fromMap(map);
      return await _helper.postMultipart(ApiRoutes.watchAndBuyReelDetailApi(reelId), formData);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Delete a reel
  Future<dynamic> deleteReel(int reelId) async {
    try {
      return await _helper.delete(ApiRoutes.watchAndBuyReelDetailApi(reelId));
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Publish or unpublish a reel
  Future<dynamic> togglePublishReel(int reelId, bool published) async {
    try {
      return await _helper.put(
        ApiRoutes.watchAndBuyReelPublishApi(reelId),
        {'published': published},
      );
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  // ─── Eligible Products ────────────────────────────────────────

  /// Fetch eligible products with search & cursor pagination
  Future<EligibleProductsResponse> getEligibleProducts({
    String? cursor,
    int perPage = 15,
    String? search,
  }) async {
    try {
      final Map<String, dynamic> queryParams = {
        'per_page': perPage.toString(),
      };
      if (cursor != null && cursor.isNotEmpty) {
        queryParams['cursor'] = cursor;
      }
      if (search != null && search.trim().isNotEmpty) {
        queryParams['search'] = search.trim();
      }

      final response = await _helper.get(
        ApiRoutes.watchAndBuyEligibleProductsApi,
        queryParameters: queryParams,
      );

      return EligibleProductsResponse.fromJson(response);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  // ─── Statuses ─────────────────────────────────────────────────

  /// Fetch seller statuses
  Future<SellerStatusesResponse> getStatuses({
    String? cursor,
    int perPage = 15,
  }) async {
    try {
      final Map<String, dynamic> queryParams = {
        'per_page': perPage.toString(),
      };
      if (cursor != null && cursor.isNotEmpty) {
        queryParams['cursor'] = cursor;
      }

      final response = await _helper.get(
        ApiRoutes.watchAndBuyStatusesApi,
        queryParameters: queryParams,
      );

      return SellerStatusesResponse.fromJson(response);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Create a status
  Future<dynamic> createStatus({
    String? text,
    File? media,
    String source = 'seller',
    int? profileId,
    List<int>? attachmentIds,
  }) async {
    try {
      final Map<String, dynamic> map = {
        'source': source,
      };

      if (text != null && text.trim().isNotEmpty) {
        map['text'] = text.trim();
      }
      if (profileId != null) {
        map['profile_id'] = profileId;
      }
      if (attachmentIds != null && attachmentIds.isNotEmpty) {
        for (int i = 0; i < attachmentIds.length; i++) {
          map['attachment_ids[$i]'] = attachmentIds[i];
        }
      }

      if (media != null && media.existsSync()) {
        map['media'] = await MultipartFile.fromFile(
          media.path,
          filename: media.path.split('/').last,
        );
      }

      final formData = FormData.fromMap(map);
      return await _helper.postMultipart(ApiRoutes.watchAndBuyStatusesApi, formData);
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  /// Delete a status
  Future<dynamic> deleteStatus(int statusId) async {
    try {
      return await _helper.delete(ApiRoutes.watchAndBuyStatusDetailApi(statusId));
    } catch (e) {
      throw Exception(e.toString());
    }
  }
}
