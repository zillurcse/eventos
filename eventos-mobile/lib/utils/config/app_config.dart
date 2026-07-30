import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

/// Central API / event / Reverb settings for the mobile app.
///
/// Physical phone on same Wi‑Fi:
/// ```
/// flutter run --dart-define=API_BASE_URL=http://192.168.0.103:8088/api/v1/
/// ```
///
/// Android emulator (host loopback):
/// ```
/// flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8088/api/v1/
/// ```
class AppConfig {
  AppConfig._();

  static const String _apiBaseUrlEnv = String.fromEnvironment('API_BASE_URL');
  static const String _eventSubdomainEnv =
      String.fromEnvironment('EVENT_SUBDOMAIN');
  static const String _reverbKeyEnv = String.fromEnvironment('REVERB_KEY');
  static const String _reverbHostEnv = String.fromEnvironment('REVERB_HOST');
  static const String _reverbPortEnv = String.fromEnvironment('REVERB_PORT');
  static const String _reverbSchemeEnv = String.fromEnvironment('REVERB_SCHEME');

  /// Your PC Wi‑Fi IPv4 (same network as the phone). Update if DHCP changes it.
  static const String localLanHost = '192.168.0.103';

  /// Default local EventOS API (`eventos-api` on port 8088).
  static String get apiBaseUrl {
    if (_apiBaseUrlEnv.isNotEmpty) {
      return _apiBaseUrlEnv.endsWith('/')
          ? _apiBaseUrlEnv
          : '$_apiBaseUrlEnv/';
    }

    if (!kIsWeb && Platform.isAndroid) {
      return 'http://$localLanHost:8088/api/v1/';
    }

    return 'http://localhost:8088/api/v1/';
  }

  /// Published event subdomain sent as `X-Event-Subdomain`.
  static String get defaultEventSubdomain =>
      _eventSubdomainEnv.isNotEmpty ? _eventSubdomainEnv : 'aiexpo';

  /// Laravel Reverb (same stack as eventos-event Echo).
  static String get reverbKey =>
      _reverbKeyEnv.isNotEmpty ? _reverbKeyEnv : 'eventos-key';

  static String get reverbHost {
    if (_reverbHostEnv.isNotEmpty) return _reverbHostEnv;
    if (!kIsWeb && Platform.isAndroid) return localLanHost;
    return 'localhost';
  }

  static int get reverbPort {
    if (_reverbPortEnv.isNotEmpty) {
      return int.tryParse(_reverbPortEnv) ?? 8081;
    }
    return 8081;
  }

  static String get reverbScheme =>
      _reverbSchemeEnv.isNotEmpty ? _reverbSchemeEnv : 'http';

  static bool get reverbUseTls => reverbScheme == 'https';
}
