import 'dart:convert';

import 'package:flutter/services.dart';

/// Lazily loads country → states from a JSON asset instead of embedding a
/// multi-thousand-line Dart map in the app isolate.
class CountryStateData {
  CountryStateData._();

  static Map<String, List<String>>? _cache;
  static Future<Map<String, List<String>>>? _loading;

  static bool get isReady => _cache != null;

  static Map<String, List<String>> get map =>
      _cache ?? const <String, List<String>>{};

  static List<String> get countries => map.keys.toList()..sort();

  static List<String> statesFor(String? country) {
    if (country == null) return const [];
    return map[country] ?? const [];
  }

  /// Loads once; safe to call from profile onInit.
  static Future<Map<String, List<String>>> ensureLoaded() {
    if (_cache != null) return Future.value(_cache!);
    return _loading ??= _load();
  }

  static Future<Map<String, List<String>>> _load() async {
    final raw =
        await rootBundle.loadString('assets/data/country_states.json');
    final decoded = jsonDecode(raw) as Map<String, dynamic>;
    final mapped = <String, List<String>>{
      for (final e in decoded.entries)
        e.key: (e.value as List).map((v) => v.toString()).toList(),
    };
    _cache = mapped;
    _loading = null;
    return mapped;
  }
}

/// Backward-compatible getter used by older call sites.
@Deprecated('Use CountryStateData.ensureLoaded() / CountryStateData.map')
Map<String, List<String>> get countryStateMap => CountryStateData.map;
