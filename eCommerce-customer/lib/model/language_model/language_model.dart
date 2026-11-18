class LanguageModel {
  bool? success;
  String? message;
  Languages? data;

  LanguageModel({
    this.success,
    this.message,
    this.data,
  });

  LanguageModel.fromJson(Map<String, dynamic> json) {
    success = json['success'] ?? false;
    message = json['message'] ?? '';
    data = Languages.fromJson(json['data'] ?? {});
  }

}

class Languages {
  String? defaultLanguage;
  List<LanguageData>? languages;

  Languages({
    this.defaultLanguage,
    this.languages,
  });

  Languages.fromJson(Map<String, dynamic> json) {
    defaultLanguage = json['default'] ?? 'en';
    languages = (json['languages'] as List<dynamic>?)
        ?.map((e) => LanguageData.fromJson(e))
    .toList() ??
    [];
  }
}

class LanguageData {
  String? code;
  String? name;
  String? nativeName;
  String? direction;

  LanguageData({
    this.code,
    this.name,
    this.nativeName,
    this.direction,
  });

  LanguageData.fromJson(Map<String, dynamic> json) {
    code = json['code'] ?? '';
    name = json['name'] ?? '';
    nativeName = json['native_name'] ?? '';
    direction = json['direction'] ?? 'ltr';
  }

}