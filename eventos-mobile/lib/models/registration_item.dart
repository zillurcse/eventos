class RegistrationItem {
  final String fieldLabel;
  final String fieldType;
  final List<String> options;
  final bool required;
  final String fieldPreviewOnPage;
  final bool isNumber;
  final String value;
  final String newValue;
  final bool isMultiSelect;
  final int hasConstantInDatabase;
  final String databaseConstantColumn;
  final String className;

  RegistrationItem({
    this.fieldLabel = '',
    this.fieldType = '',
    this.options = const [],
    this.required = false,
    this.fieldPreviewOnPage = '',
    this.isNumber = false,
    this.value = '',
    this.newValue = '',
    this.isMultiSelect = false,
    this.hasConstantInDatabase = 0,
    this.databaseConstantColumn = '',
    this.className = '',
  });

  factory RegistrationItem.fromJson(Map<String, dynamic> json) {
    return RegistrationItem(
      fieldLabel: json['field_label'] ?? '',
      fieldType: json['field_type'] ?? '',
      options: (json['options'] is List)
          ? List<String>.from(json['options'].map((e) => e["option_name"]?.toString() ?? ''))
          : [],
      required: json['required'] ?? false,
      fieldPreviewOnPage: json['field_preview_on_page'] ?? '',
      isNumber: json['is_number'] ?? false,
      value: json['value']?.toString() ?? '',
      newValue: json['new_value']?.toString() ?? '',
      isMultiSelect: json['is_multi_select'] ?? false,
      hasConstantInDatabase: json['has_constant_in_database'] ?? 0,
      databaseConstantColumn: json['database_constant_column'] ?? '',
      className: json['class'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'field_label': fieldLabel,
      'field_type': fieldType,
      'value': value,
      'has_constant_in_database': hasConstantInDatabase,
      'database_constant_column': databaseConstantColumn,
    };
  }
}