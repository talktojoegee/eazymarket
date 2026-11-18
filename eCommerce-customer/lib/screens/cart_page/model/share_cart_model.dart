class ShareCartModel {
  bool? success;
  String? message;
  Data? data;

  ShareCartModel({this.success, this.message, this.data});

  ShareCartModel.fromJson(Map<String, dynamic> json) {
    success = json['success'];
    message = json['message'];
    data = json['data'] != null ? Data.fromJson(json['data']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['success'] = success;
    data['message'] = message;
    if (this.data != null) {
      data['data'] = this.data!.toJson();
    }
    return data;
  }
}

class Data {
  String? token;
  String? code;
  String? shareUrl;
  String? expiresAt;

  Data({this.token, this.code, this.shareUrl, this.expiresAt});

  Data.fromJson(Map<String, dynamic> json) {
    code = json['code']?.toString() ?? json['token']?.toString();
    token = code;
    shareUrl = json['url']?.toString() ??
        json['share_url']?.toString() ??
        json['link']?.toString();
    expiresAt = json['expires_at']?.toString();
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['token'] = token;
    data['code'] = code;
    data['share_url'] = shareUrl;
    data['url'] = shareUrl;
    data['expires_at'] = expiresAt;
    return data;
  }
}
