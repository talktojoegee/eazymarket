class LanguageLabelsModel {
  bool? success;
  String? message;
  LanguageLabels? data;

  LanguageLabelsModel({this.success, this.message, this.data});

  LanguageLabelsModel.fromJson(Map<String, dynamic> json) {
    success = json['success'];
    message = json['message'];
    data = json['data'] != null ? LanguageLabels.fromJson(json['data']) : null;
  }
}

/// Backward compatibility alias
typedef LanguageListModel = LanguageLabelsModel;

class LanguageLabels {
  String? locale;
  String? direction;
  Map<String, String>? labels;

  LanguageLabels({this.locale, this.direction, this.labels});

  factory LanguageLabels.fromJson(Map<String, dynamic> json) {
    final rawLabels = json['labels'] as Map<String, dynamic>? ?? {};

    return LanguageLabels(
      locale: json['locale'],
      direction: json['direction'],
      labels: rawLabels.map((key, val) => MapEntry(key, val.toString())),
    );
  }
}

