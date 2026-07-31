import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

/// Central API / event / Reverb settings for the mobile app.
///
/// Production (HTTPS required):
/// ```
/// flutter build apk --release \
///   --dart-define=API_BASE_URL=https://api.example.com/api/v1/ \
///   --dart-define=EVENT_SUBDOMAIN=yourevent \
///   --dart-define=REVERB_KEY=... \
///   --dart-define=REVERB_HOST=reverb.example.com \
///   --dart-define=REVERB_PORT=443 \
///   --dart-define=REVERB_SCHEME=https
/// ```
///
/// Optional cert pinning (SHA-256 of leaf DER, semicolon-separated):
/// ```
/// --dart-define=SSL_PINS=abcdef...;123456...
/// ```
///
/// Local debug (HTTP allowed only in debug/profile):
/// ```
/// flutter run --dart-define=API_BASE_URL=http://192.168.0.103:8088/api/v1/
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
  static const String _sslPinsEnv = String.fromEnvironment('SSL_PINS');

  /// Dev-only LAN host. Never used as a default in release builds.
  static const String localLanHost = String.fromEnvironment(
    'LOCAL_LAN_HOST',
    defaultValue: '192.168.0.102',
  );

  /// Default local EventOS API (`eventos-api` on port 8088).
  /// Release builds require an HTTPS [API_BASE_URL] dart-define.
  static String get apiBaseUrl {
    if (_apiBaseUrlEnv.isNotEmpty) {
      final normalized = _apiBaseUrlEnv.endsWith('/')
          ? _apiBaseUrlEnv
          : '$_apiBaseUrlEnv/';
      return normalized;
    }

    if (kReleaseMode) {
      // Fail closed: no silent HTTP fallback in production binaries.
      throw StateError(
        'Release builds require --dart-define=API_BASE_URL=https://.../api/v1/',
      );
    }

    if (!kIsWeb && Platform.isAndroid) {
      return 'http://$localLanHost:8088/api/v1/';
    }

    return 'http://localhost:8088/api/v1/';
  }

  /// Reject cleartext API bases in release; warn in debug.
  static void assertSecureBaseUrl(String url) {
    final uri = Uri.tryParse(url);
    final isHttps = uri != null && uri.scheme == 'https';
    if (kReleaseMode && !isHttps) {
      throw StateError(
        'Insecure API base URL in release build: $url. Use HTTPS.',
      );
    }
    if (kDebugMode && !isHttps) {
      debugPrint(
        'AppConfig: using cleartext API ($url). '
        'Use HTTPS for production builds.',
      );
    }
  }

  /// SHA-256 hex fingerprints for optional certificate pinning.
  static List<String> get sslPins {
    if (_sslPinsEnv.isEmpty) return const [];
    return _sslPinsEnv
        .split(';')
        .map((e) => e.trim().toLowerCase().replaceAll(':', ''))
        .where((e) => e.isNotEmpty)
        .toList(growable: false);
  }

  /// Published event subdomain sent as `X-Event-Subdomain`.
  static String get defaultEventSubdomain =>
      _eventSubdomainEnv.isNotEmpty ? _eventSubdomainEnv : 'aiexpo';

  /// Laravel Reverb app key — supply via dart-define in release builds.
  static String get reverbKey {
    if (_reverbKeyEnv.isNotEmpty) return _reverbKeyEnv;
    if (kReleaseMode) {
      debugPrint('AppConfig: REVERB_KEY missing — realtime chat disabled.');
      return '';
    }
    return 'eventos-key';
  }

  static String get reverbHost {
    if (_reverbHostEnv.isNotEmpty) return _reverbHostEnv;
    if (kReleaseMode) {
      debugPrint('AppConfig: REVERB_HOST missing — realtime chat disabled.');
      return '';
    }
    if (!kIsWeb && Platform.isAndroid) return localLanHost;
    return 'localhost';
  }

  static int get reverbPort {
    if (_reverbPortEnv.isNotEmpty) {
      return int.tryParse(_reverbPortEnv) ?? 8081;
    }
    return kReleaseMode ? 443 : 8081;
  }

  static String get reverbScheme {
    if (_reverbSchemeEnv.isNotEmpty) return _reverbSchemeEnv;
    return kReleaseMode ? 'https' : 'http';
  }

  static bool get reverbUseTls => reverbScheme == 'https';

  /// Host used for the API (LAN IP on Android, localhost on desktop).
  static String get apiHost {
    try {
      final host = Uri.parse(apiBaseUrl).host;
      if (host.isNotEmpty) return host;
    } catch (_) {}
    return (!kIsWeb && Platform.isAndroid) ? localLanHost : 'localhost';
  }

  /// MinIO / storage URLs from the API often use `localhost:9000`, which only
  /// works on the host machine. Rewrite loopback hosts to [apiHost] so the
  /// emulator / physical device can load uploaded images.
  static String resolveMediaUrl(String? url) => resolveLoopbackUrl(url);

  /// LiveKit join URLs from the API often use `ws://localhost:7880`, which only
  /// works on the host machine. Same rewrite as [resolveMediaUrl] so a phone /
  /// emulator can open the signaling WebSocket.
  static String resolveLiveKitUrl(String? url) => resolveLoopbackUrl(url);

  /// Rewrite `localhost` / `127.0.0.1` / `0.0.0.0` hosts to [apiHost].
  static String resolveLoopbackUrl(String? url) {
    if (url == null) return '';
    final trimmed = url.trim();
    if (trimmed.isEmpty) return trimmed;

    final uri = Uri.tryParse(trimmed);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) return trimmed;

    final host = uri.host.toLowerCase();
    if (host == 'localhost' || host == '127.0.0.1' || host == '0.0.0.0') {
      return uri.replace(host: apiHost).toString();
    }
    return trimmed;
  }
}
