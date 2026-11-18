import 'eligible_product_model.dart';

class SellerStatusCreator {
  final int? id;
  final String? name;

  SellerStatusCreator({this.id, this.name});

  factory SellerStatusCreator.fromJson(Map<String, dynamic> json) {
    return SellerStatusCreator(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? ''),
      name: json['name']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {'id': id, 'name': name};
}

class SellerStatusModel {
  final int id;
  final String? uuid;
  final String? source;
  final String? contentType; // 'text', 'image', 'video'
  final String? text;
  final String? mediaUrl;
  final String? mediaMime;
  final int? mediaSizeBytes;
  final int? width;
  final int? height;
  final int? durationMs;
  final List<EligibleProductModel> products;
  final SellerStatusCreator? creator;
  final DateTime? createdAt;
  final DateTime? expiresAt;
  final bool seenByMe;

  SellerStatusModel({
    required this.id,
    this.uuid,
    this.source,
    this.contentType,
    this.text,
    this.mediaUrl,
    this.mediaMime,
    this.mediaSizeBytes,
    this.width,
    this.height,
    this.durationMs,
    this.products = const [],
    this.creator,
    this.createdAt,
    this.expiresAt,
    this.seenByMe = false,
  });

  bool get isVideo =>
      contentType == 'video' || (mediaMime != null && mediaMime!.startsWith('video/'));

  bool get isImage =>
      contentType == 'image' || (mediaMime != null && mediaMime!.startsWith('image/'));

  bool get isTextOnly => (mediaUrl == null || mediaUrl!.isEmpty) && (text != null && text!.isNotEmpty);

  bool get isExpired {
    if (expiresAt == null) return false;
    return DateTime.now().isAfter(expiresAt!);
  }

  String get timeRemainingFormatted {
    if (expiresAt == null) return '';
    final diff = expiresAt!.difference(DateTime.now());
    if (diff.isNegative) return 'Expired';
    if (diff.inHours > 0) return '${diff.inHours}h ${diff.inMinutes.remainder(60)}m left';
    if (diff.inMinutes > 0) return '${diff.inMinutes}m left';
    return '${diff.inSeconds}s left';
  }

  factory SellerStatusModel.fromJson(Map<String, dynamic> json) {
    final rawProducts = json['products'] as List<dynamic>? ?? [];
    final List<EligibleProductModel> parsedProducts = rawProducts
        .whereType<Map<String, dynamic>>()
        .map((p) => EligibleProductModel.fromJson(p))
        .toList();

    DateTime? parseDate(dynamic value) {
      if (value == null) return null;
      return DateTime.tryParse(value.toString());
    }

    return SellerStatusModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      uuid: json['uuid']?.toString(),
      source: json['source']?.toString(),
      contentType: json['content_type']?.toString() ?? (json['media_url'] != null ? 'image' : 'text'),
      text: json['text']?.toString(),
      mediaUrl: json['media_url']?.toString(),
      mediaMime: json['media_mime']?.toString(),
      mediaSizeBytes: json['media_size_bytes'] is int ? json['media_size_bytes'] : int.tryParse(json['media_size_bytes']?.toString() ?? ''),
      width: json['width'] is int ? json['width'] : int.tryParse(json['width']?.toString() ?? ''),
      height: json['height'] is int ? json['height'] : int.tryParse(json['height']?.toString() ?? ''),
      durationMs: json['duration_ms'] is int ? json['duration_ms'] : int.tryParse(json['duration_ms']?.toString() ?? ''),
      products: parsedProducts,
      creator: json['creator'] is Map<String, dynamic> ? SellerStatusCreator.fromJson(json['creator']) : null,
      createdAt: parseDate(json['created_at']),
      expiresAt: parseDate(json['expires_at']),
      seenByMe: json['seen_by_me'] == true || json['seen_by_me'] == 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'uuid': uuid,
      'source': source,
      'content_type': contentType,
      'text': text,
      'media_url': mediaUrl,
      'media_mime': mediaMime,
      'media_size_bytes': mediaSizeBytes,
      'width': width,
      'height': height,
      'duration_ms': durationMs,
      'products': products.map((p) => p.toJson()).toList(),
      'creator': creator?.toJson(),
      'created_at': createdAt?.toIso8601String(),
      'expires_at': expiresAt?.toIso8601String(),
      'seen_by_me': seenByMe,
    };
  }
}

class SellerStatusesResponse {
  final List<SellerStatusModel> items;
  final CursorPaginationModel pagination;

  SellerStatusesResponse({
    required this.items,
    required this.pagination,
  });

  factory SellerStatusesResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic> ? json['data'] : json;
    final itemsList = data['items'] as List<dynamic>? ?? [];
    final paginationMap = data['pagination'] as Map<String, dynamic>? ?? {};

    return SellerStatusesResponse(
      items: itemsList.map((e) => SellerStatusModel.fromJson(e as Map<String, dynamic>)).toList(),
      pagination: CursorPaginationModel.fromJson(paginationMap),
    );
  }
}
