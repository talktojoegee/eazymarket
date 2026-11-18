class RegistrationField {
  final String key;
  final String label;
  final String type; // 'text', 'select', 'image'
  final bool required;
  final String? placeholder;
  final dynamic defaultValue;
  final List<String> options;

  RegistrationField({
    required this.key,
    required this.label,
    required this.type,
    this.required = false,
    this.placeholder,
    this.defaultValue,
    this.options = const [],
  });

  factory RegistrationField.fromJson(Map<String, dynamic> json) {
    return RegistrationField(
      key: json['key']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      type: json['type']?.toString().toLowerCase() ?? 'text',
      required: json['required'] == true,
      placeholder: json['placeholder']?.toString(),
      defaultValue: json['default_value'],
      options: (json['options'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
    );
  }
}

class RegistrationSection {
  final String label;
  final List<RegistrationField> fields;

  RegistrationSection({
    required this.label,
    required this.fields,
  });

  factory RegistrationSection.fromJson(Map<String, dynamic> json) {
    final rawFields = json['fields'];
    final List<RegistrationField> fieldsList = [];
    if (rawFields is List) {
      for (var f in rawFields) {
        if (f is Map<String, dynamic>) {
          fieldsList.add(RegistrationField.fromJson(f));
        } else if (f is Map) {
          fieldsList.add(RegistrationField.fromJson(Map<String, dynamic>.from(f)));
        }
      }
    }
    return RegistrationSection(
      label: json['label']?.toString() ?? '',
      fields: fieldsList,
    );
  }
}

class RegistrationFieldsResponse {
  final bool success;
  final String message;
  final String? countryName;
  final String? countryIso2;
  final RegistrationSection? bankDetails;
  final RegistrationSection? businessDocuments;

  RegistrationFieldsResponse({
    required this.success,
    required this.message,
    this.countryName,
    this.countryIso2,
    this.bankDetails,
    this.businessDocuments,
  });

  factory RegistrationFieldsResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>?;
    final country = data?['country'] as Map<String, dynamic>?;
    final sections = data?['sections'] as Map<String, dynamic>?;

    return RegistrationFieldsResponse(
      success: json['success'] == true,
      message: json['message']?.toString() ?? '',
      countryName: country?['name']?.toString(),
      countryIso2: country?['iso2']?.toString(),
      bankDetails: sections?['bank_details'] != null
          ? RegistrationSection.fromJson(Map<String, dynamic>.from(sections!['bank_details']))
          : null,
      businessDocuments: sections?['business_documents'] != null
          ? RegistrationSection.fromJson(Map<String, dynamic>.from(sections!['business_documents']))
          : null,
    );
  }
}
