import 'eligible_product_model.dart';
import 'watch_and_buy_profile_model.dart';

class WatchAndBuyReelModel {
  final int id;
  final String? uuid;
  final String? slug;
  final String? caption;
  final String? status; // 'published', 'unpublished', etc.
  final String? source;
  final String? blockReason;
  final String? blockedAt;
  final String? videoUrl;
  final String? coverUrl;
  final int? durationMs;
  final int? width;
  final int? height;
  final int? videoSizeBytes;
  final WatchAndBuyProfileModel? profile;
  final int likeCount;
  final bool likedByMe;
  final int? primaryAttachmentId;
  final List<int> attachmentIds;
  final List<EligibleProductModel> products;
  final String? publishedAt;
  final String? createdAt;
  final String? updatedAt;

  WatchAndBuyReelModel({
    required this.id,
    this.uuid,
    this.slug,
    this.caption,
    this.status,
    this.source,
    this.blockReason,
    this.blockedAt,
    this.videoUrl,
    this.coverUrl,
    this.durationMs,
    this.width,
    this.height,
    this.videoSizeBytes,
    this.profile,
    this.likeCount = 0,
    this.likedByMe = false,
    this.primaryAttachmentId,
    this.attachmentIds = const [],
    this.products = const [],
    this.publishedAt,
    this.createdAt,
    this.updatedAt,
  });

  bool get isPublished => status?.toLowerCase() == 'published';
  bool get isUnpublished => status?.toLowerCase() == 'unpublished';
  bool get isBlocked => status?.toLowerCase() == 'blocked';

  EligibleProductModel? get primaryProduct {
    return products.where((p) => p.isPrimary).firstOrNull ?? products.firstOrNull;
  }

  String get formattedDuration {
    if (durationMs == null || durationMs! <= 0) return '0:00';
    final totalSeconds = (durationMs! / 1000).round();
    final minutes = totalSeconds ~/ 60;
    final seconds = totalSeconds % 60;
    return '$minutes:${seconds.toString().padLeft(2, '0')}';
  }

  factory WatchAndBuyReelModel.fromJson(Map<String, dynamic> json) {
    // Handle products list if returned
    final rawProducts = json['products'] as List<dynamic>? ?? [];
    final List<EligibleProductModel> parsedProducts = rawProducts
        .whereType<Map<String, dynamic>>()
        .map((p) => EligibleProductModel.fromJson(p))
        .toList();

    // Handle attachment_ids if returned
    final rawAttachmentIds = json['attachment_ids'] as List<dynamic>? ?? [];
    final List<int> parsedAttachmentIds = rawAttachmentIds
        .map((e) => int.tryParse(e.toString()) ?? 0)
        .where((id) => id > 0)
        .toList();

    // Profile object if nested
    WatchAndBuyProfileModel? parsedProfile;
    if (json['profile'] is Map<String, dynamic>) {
      parsedProfile = WatchAndBuyProfileModel.fromJson(json['profile']);
    }

    final rawPrimaryId = json['primary_attachment_id'] ?? json['primary_product_id'];
    int? parsedPrimaryId = rawPrimaryId is int ? rawPrimaryId : int.tryParse(rawPrimaryId?.toString() ?? '');
    if (parsedPrimaryId == null && parsedProducts.isNotEmpty) {
      final primary = parsedProducts.where((p) => p.isPrimary).firstOrNull;
      if (primary != null) {
        parsedPrimaryId = primary.id;
      }
    }

    return WatchAndBuyReelModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      uuid: json['uuid']?.toString(),
      slug: json['slug']?.toString(),
      caption: json['caption']?.toString(),
      status: json['status']?.toString(),
      source: json['source']?.toString(),
      blockReason: json['block_reason']?.toString(),
      blockedAt: json['blocked_at']?.toString(),
      videoUrl: json['video_url']?.toString(),
      coverUrl: json['cover_url']?.toString(),
      durationMs: json['duration_ms'] is int ? json['duration_ms'] : int.tryParse(json['duration_ms']?.toString() ?? ''),
      width: json['width'] is int ? json['width'] : (json['video_width'] is int ? json['video_width'] : int.tryParse(json['width']?.toString() ?? '')),
      height: json['height'] is int ? json['height'] : (json['video_height'] is int ? json['video_height'] : int.tryParse(json['height']?.toString() ?? '')),
      videoSizeBytes: json['video_size_bytes'] is int ? json['video_size_bytes'] : int.tryParse(json['video_size_bytes']?.toString() ?? ''),
      profile: parsedProfile,
      likeCount: json['like_count'] is int ? json['like_count'] : int.tryParse(json['like_count']?.toString() ?? '0') ?? 0,
      likedByMe: json['liked_by_me'] == true || json['liked_by_me'] == 1,
      primaryAttachmentId: parsedPrimaryId,
      attachmentIds: parsedAttachmentIds,
      products: parsedProducts,
      publishedAt: json['published_at']?.toString(),
      createdAt: json['created_at']?.toString(),
      updatedAt: json['updated_at']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'uuid': uuid,
      'slug': slug,
      'caption': caption,
      'status': status,
      'source': source,
      'block_reason': blockReason,
      'blocked_at': blockedAt,
      'video_url': videoUrl,
      'cover_url': coverUrl,
      'duration_ms': durationMs,
      'width': width,
      'height': height,
      'video_size_bytes': videoSizeBytes,
      'profile': profile?.toJson(),
      'like_count': likeCount,
      'liked_by_me': likedByMe,
      'primary_attachment_id': primaryAttachmentId,
      'attachment_ids': attachmentIds,
      'products': products.map((p) => p.toJson()).toList(),
      'published_at': publishedAt,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  WatchAndBuyReelModel copyWith({
    int? id,
    String? uuid,
    String? slug,
    String? caption,
    String? status,
    String? source,
    String? blockReason,
    String? blockedAt,
    String? videoUrl,
    String? coverUrl,
    int? durationMs,
    int? width,
    int? height,
    int? videoSizeBytes,
    WatchAndBuyProfileModel? profile,
    int? likeCount,
    bool? likedByMe,
    int? primaryAttachmentId,
    List<int>? attachmentIds,
    List<EligibleProductModel>? products,
    String? publishedAt,
    String? createdAt,
    String? updatedAt,
  }) {
    return WatchAndBuyReelModel(
      id: id ?? this.id,
      uuid: uuid ?? this.uuid,
      slug: slug ?? this.slug,
      caption: caption ?? this.caption,
      status: status ?? this.status,
      source: source ?? this.source,
      blockReason: blockReason ?? this.blockReason,
      blockedAt: blockedAt ?? this.blockedAt,
      videoUrl: videoUrl ?? this.videoUrl,
      coverUrl: coverUrl ?? this.coverUrl,
      durationMs: durationMs ?? this.durationMs,
      width: width ?? this.width,
      height: height ?? this.height,
      videoSizeBytes: videoSizeBytes ?? this.videoSizeBytes,
      profile: profile ?? this.profile,
      likeCount: likeCount ?? this.likeCount,
      likedByMe: likedByMe ?? this.likedByMe,
      primaryAttachmentId: primaryAttachmentId ?? this.primaryAttachmentId,
      attachmentIds: attachmentIds ?? this.attachmentIds,
      products: products ?? this.products,
      publishedAt: publishedAt ?? this.publishedAt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }
}

class ReelsPagePagination {
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  ReelsPagePagination({
    this.currentPage = 1,
    this.lastPage = 1,
    this.perPage = 15,
    this.total = 0,
  });

  bool get hasNextPage => currentPage < lastPage;

  factory ReelsPagePagination.fromJson(Map<String, dynamic> json) {
    return ReelsPagePagination(
      currentPage: json['current_page'] is int ? json['current_page'] : int.tryParse(json['current_page']?.toString() ?? '1') ?? 1,
      lastPage: json['last_page'] is int ? json['last_page'] : int.tryParse(json['last_page']?.toString() ?? '1') ?? 1,
      perPage: json['per_page'] is int ? json['per_page'] : int.tryParse(json['per_page']?.toString() ?? '15') ?? 15,
      total: json['total'] is int ? json['total'] : int.tryParse(json['total']?.toString() ?? '0') ?? 0,
    );
  }
}

class ReelsResponse {
  final List<WatchAndBuyReelModel> items;
  final ReelsPagePagination pagination;

  ReelsResponse({
    required this.items,
    required this.pagination,
  });

  factory ReelsResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic> ? json['data'] : json;
    final itemsList = data['items'] as List<dynamic>? ?? [];
    final paginationMap = data['pagination'] as Map<String, dynamic>? ?? {};

    return ReelsResponse(
      items: itemsList.map((e) => WatchAndBuyReelModel.fromJson(e as Map<String, dynamic>)).toList(),
      pagination: ReelsPagePagination.fromJson(paginationMap),
    );
  }
}
