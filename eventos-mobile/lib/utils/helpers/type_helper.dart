import 'dart:convert';

class TypeHelper {
  static int toInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) {
      if (value.isEmpty) return 0;
      final parsed = int.tryParse(value);
      if (parsed != null) return parsed;
      // EventOS uses UUID strings - stable positive int for UI keys.
      return value.hashCode & 0x7fffffff;
    }
    return 0;
  }

  static bool toBool(dynamic value) {
    if (value == null) return false;
    if (value is bool) return value;
    if (value is int) return value == 1;
    if (value is String) return value.toLowerCase() == 'true' || value == '1';
    return false;
  }

  static List<dynamic> toList(dynamic value) {
    if (value == null) return const [];
    if (value is List) return value;
    if (value is String) {
      if (value.trim().isEmpty || value == 'null') return const [];
      try {
        final decoded = jsonDecode(value);
        if (decoded is List) return decoded;
      } catch (_) {}
    }
    return const [];
  }
}
