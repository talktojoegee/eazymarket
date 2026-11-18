import '../../../config/helper.dart';

class SharedCartImportResponseModel {
  final bool? success;
  final String? message;
  final SharedCartImportData? data;

  SharedCartImportResponseModel({
    this.success,
    this.message,
    this.data,
  });

  factory SharedCartImportResponseModel.fromJson(Map<String, dynamic> json) {
    return SharedCartImportResponseModel(
      success: parseBool(json['success']),
      message: parseString(json['message']),
      data: json['data'] != null && json['data'] is Map<String, dynamic>
          ? SharedCartImportData.fromJson(json['data'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'success': success,
        'message': message,
        if (data != null) 'data': data?.toJson(),
      };
}

class SharedCartImportData {
  final bool? success;
  final String? message;
  final int? importedCount;
  final List<dynamic> failedItems;

  SharedCartImportData({
    this.success,
    this.message,
    this.importedCount,
    this.failedItems = const [],
  });

  factory SharedCartImportData.fromJson(Map<String, dynamic> json) {
    return SharedCartImportData(
      success: parseBool(json['success']),
      message: parseString(json['message']),
      importedCount: parseInt(json['imported_count']),
      failedItems: json['failed_items'] is List
          ? (json['failed_items'] as List)
          : const [],
    );
  }

  Map<String, dynamic> toJson() => {
        'success': success,
        'message': message,
        'imported_count': importedCount,
        'failed_items': failedItems,
      };
}
