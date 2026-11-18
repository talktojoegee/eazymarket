class WatchAndBuyProfileModel {
  final int? id;
  final String? type;
  final int? sellerId;
  final String? username;
  final String? bio;
  final String? photoUrl;
  final String? status;
  final String? suspensionReason;
  final String? suspendedAt;
  final bool hasActiveStatus;
  final bool hasUnseenStatus;
  final String? createdAt;
  final String? updatedAt;

  WatchAndBuyProfileModel({
    this.id,
    this.type,
    this.sellerId,
    this.username,
    this.bio,
    this.photoUrl,
    this.status,
    this.suspensionReason,
    this.suspendedAt,
    this.hasActiveStatus = false,
    this.hasUnseenStatus = false,
    this.createdAt,
    this.updatedAt,
  });

  bool get isActive => status?.toLowerCase() == 'active';

  factory WatchAndBuyProfileModel.fromJson(Map<String, dynamic> json) {
    // Handle nested 'data' or flat payload
    final data = json['data'] is Map<String, dynamic> ? json['data'] : json;

    return WatchAndBuyProfileModel(
      id: data['id'] is int ? data['id'] : int.tryParse(data['id']?.toString() ?? ''),
      type: data['type']?.toString() ?? data['profile_type']?.toString(),
      sellerId: data['seller_id'] is int ? data['seller_id'] : int.tryParse(data['seller_id']?.toString() ?? ''),
      username: data['username']?.toString(),
      bio: data['bio']?.toString(),
      photoUrl: data['photo_url']?.toString() ?? data['photo']?.toString(),
      status: data['status']?.toString(),
      suspensionReason: data['suspension_reason']?.toString(),
      suspendedAt: data['suspended_at']?.toString(),
      hasActiveStatus: data['has_active_status'] == true || data['has_active_status'] == 1,
      hasUnseenStatus: data['has_unseen_status'] == true || data['has_unseen_status'] == 1,
      createdAt: data['created_at']?.toString(),
      updatedAt: data['updated_at']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type,
      'seller_id': sellerId,
      'username': username,
      'bio': bio,
      'photo_url': photoUrl,
      'status': status,
      'suspension_reason': suspensionReason,
      'suspended_at': suspendedAt,
      'has_active_status': hasActiveStatus,
      'has_unseen_status': hasUnseenStatus,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }
}
