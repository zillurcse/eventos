import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

/// Central API / event / Reverb / CDN / LiveKit settings for the mobile app.
///
/// Defaults to **live Expouse production** in all build modes:
///   API      → https://api.expouse.com/api/v1/
///   CDN      → https://cdn.expouse.com
///   Reverb   → reverb.expouse.com:443 (https)
///   LiveKit  → wss://livekit.expouse.com
///   Event    → aiexpo.expouse.com
///
/// Local LAN stack (opt-in only):
/// ```
/// flutter run --dart-define=USE_LOCAL_API=true
/// # optional LAN IP override:
/// flutter run --dart-define=USE_LOCAL_API=true --dart-define=LOCAL_LAN_HOST=192.168.0.103
/// ```
///
/// Or point at any URL:
/// ```
/// flutter run --dart-define=API_BASE_URL=http://192.168.0.103:8088/api/v1/
/// ```
class AppConfig {
  AppConfig._();

  // ── Live Expouse production (cPanel as-built) ───────────────────────────

  static const String liveApiBaseUrl = 'https://api.expouse.com/api/v1/';
  static const String liveCdnBaseUrl = 'https://cdn.expouse.com';
  static const String liveLiveKitUrl = 'wss://livekit.expouse.com';
  static const String liveEventBaseDomain = 'expouse.com';
  static const String liveEventSubdomain = 'aiexpo';
  static const String liveReverbHost = 'reverb.expouse.com';
  static const String liveReverbKey = '079b06263e138eab0f2614b5fb5ac143';
  static const int liveReverbPort = 443;
  static const String liveReverbScheme = 'https';
  static const String liveJitsiDomain = 'meet.jit.si';

  // ── dart-define overrides ───────────────────────────────────────────────

  static const String _apiBaseUrlEnv = String.fromEnvironment('API_BASE_URL');
  static const String _cdnBaseUrlEnv = String.fromEnvironment('CDN_BASE_URL');
  static const String _liveKitUrlEnv = String.fromEnvironment('LIVEKIT_URL');
  static const String _eventBaseDomainEnv =
      String.fromEnvironment('EVENT_BASE_DOMAIN');
  static const String _eventSubdomainEnv =
      String.fromEnvironment('EVENT_SUBDOMAIN');
  static const String _reverbKeyEnv = String.fromEnvironment('REVERB_KEY');
  static const String _reverbHostEnv = String.fromEnvironment('REVERB_HOST');
  static const String _reverbPortEnv = String.fromEnvironment('REVERB_PORT');
  static const String _reverbSchemeEnv = String.fromEnvironment('REVERB_SCHEME');
  static const String _jitsiDomainEnv = String.fromEnvironment('JITSI_DOMAIN');
  static const String _sslPinsEnv = String.fromEnvironment('SSL_PINS');
  static const bool useLocalApi = bool.fromEnvironment(
    'USE_LOCAL_API',
    defaultValue: false,
  );

  /// Dev-only LAN host when [useLocalApi] is true.
  static const String localLanHost = String.fromEnvironment(
    'LOCAL_LAN_HOST',
    defaultValue: '192.168.0.102',
  );

  // ── API ─────────────────────────────────────────────────────────────────

  /// API base: dart-define → local (opt-in) → live.
  static String get apiBaseUrl {
    if (_apiBaseUrlEnv.isNotEmpty) {
      return _normalizeTrailingSlash(_apiBaseUrlEnv);
    }
    if (useLocalApi) {
      if (!kIsWeb && Platform.isAndroid) {
        return 'http://$localLanHost:8088/api/v1/';
      }
      return 'http://localhost:8088/api/v1/';
    }
    return liveApiBaseUrl;
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

  // ── Event ───────────────────────────────────────────────────────────────

  /// Platform apex (`aiexpo.expouse.com` → domain `expouse.com`).
  static String get eventBaseDomain {
    if (_eventBaseDomainEnv.isNotEmpty) return _eventBaseDomainEnv;
    return liveEventBaseDomain;
  }

  /// Published event subdomain sent as `X-Event-Subdomain`.
  static String get defaultEventSubdomain {
    if (_eventSubdomainEnv.isNotEmpty) return _eventSubdomainEnv;
    return liveEventSubdomain;
  }

  // ── CDN / storage ───────────────────────────────────────────────────────

  /// Public object-storage base (MinIO via cdn.expouse.com in production).
  static String get cdnBaseUrl {
    if (_cdnBaseUrlEnv.isNotEmpty) {
      return _stripTrailingSlash(_cdnBaseUrlEnv);
    }
    if (useLocalApi) {
      if (!kIsWeb && Platform.isAndroid) {
        return 'http://$localLanHost:9000';
      }
      return 'http://localhost:9000';
    }
    return liveCdnBaseUrl;
  }

  /// Prefix for relative legacy `/storage/` paths.
  static String get storageBaseUrl => cdnBaseUrl;

  // ── LiveKit ─────────────────────────────────────────────────────────────

  /// Signaling URL for breakout rooms / lounge video.
  static String get liveKitUrl {
    if (_liveKitUrlEnv.isNotEmpty) return _liveKitUrlEnv;
    if (useLocalApi) {
      if (!kIsWeb && Platform.isAndroid) {
        return 'ws://$localLanHost:7880';
      }
      return 'ws://localhost:7880';
    }
    return liveLiveKitUrl;
  }

  // ── Jitsi ───────────────────────────────────────────────────────────────

  static String get jitsiDomain {
    if (_jitsiDomainEnv.isNotEmpty) return _jitsiDomainEnv;
    return liveJitsiDomain;
  }

  // ── Reverb ──────────────────────────────────────────────────────────────

  static String get reverbKey {
    if (_reverbKeyEnv.isNotEmpty) return _reverbKeyEnv;
    if (useLocalApi) return 'eventos-key';
    return liveReverbKey;
  }

  static String get reverbHost {
    if (_reverbHostEnv.isNotEmpty) return _reverbHostEnv;
    if (useLocalApi) {
      if (!kIsWeb && Platform.isAndroid) return localLanHost;
      return 'localhost';
    }
    return liveReverbHost;
  }

  static int get reverbPort {
    if (_reverbPortEnv.isNotEmpty) {
      return int.tryParse(_reverbPortEnv) ?? liveReverbPort;
    }
    return useLocalApi ? 8081 : liveReverbPort;
  }

  static String get reverbScheme {
    if (_reverbSchemeEnv.isNotEmpty) return _reverbSchemeEnv;
    return useLocalApi ? 'http' : liveReverbScheme;
  }

  static bool get reverbUseTls => reverbScheme == 'https';

  // ── Host helpers ────────────────────────────────────────────────────────

  /// Host used for the API.
  static String get apiHost {
    try {
      final host = Uri.parse(apiBaseUrl).host;
      if (host.isNotEmpty) return host;
    } catch (_) {}
    if (useLocalApi && !kIsWeb && Platform.isAndroid) return localLanHost;
    return useLocalApi ? 'localhost' : 'api.expouse.com';
  }

  static String get cdnHost {
    try {
      final host = Uri.parse(cdnBaseUrl).host;
      if (host.isNotEmpty) return host;
    } catch (_) {}
    return apiHost;
  }

  static String get liveKitHost {
    try {
      final host = Uri.parse(liveKitUrl).host;
      if (host.isNotEmpty) return host;
    } catch (_) {}
    return apiHost;
  }

  // ── URL resolvers ───────────────────────────────────────────────────────

  /// Absolute media URL, or relative path → [storageBaseUrl]/path].
  /// Also rewrites loopback MinIO hosts for device/emulator access.
  /// Flutter bundled assets (`assets/...`) are left unchanged.
  static String resolveMediaUrl(String? url) {
    if (url == null) return '';
    final trimmed = url.trim();
    if (trimmed.isEmpty) return trimmed;
    if (trimmed.startsWith('assets/')) return trimmed;

    final asset = resolveAssetUrl(trimmed);
    return _rewriteLoopbackHost(
      asset,
      replacementHost: cdnHost,
      ports: const {9000},
    );
  }

  /// Relative storage path → CDN URL; absolute URLs pass through.
  /// Does not touch Flutter asset paths.
  static String resolveAssetUrl(String? path) {
    if (path == null) return '';
    final trimmed = path.trim();
    if (trimmed.isEmpty) return trimmed;

    if (trimmed.startsWith('assets/')) return trimmed;

    if (trimmed.startsWith('http://') ||
        trimmed.startsWith('https://') ||
        trimmed.startsWith('ws://') ||
        trimmed.startsWith('wss://')) {
      return trimmed;
    }

    final cleaned = trimmed.startsWith('/') ? trimmed.substring(1) : trimmed;
    // Legacy Laravel disk paths often include a leading `storage/`.
    final withoutStorage = cleaned.startsWith('storage/')
        ? cleaned.substring('storage/'.length)
        : cleaned;
    return '$storageBaseUrl/$withoutStorage';
  }

  /// LiveKit join URL with loopback → [liveKitHost] rewrite for devices.
  static String resolveLiveKitUrl(String? url) {
    if (url == null || url.trim().isEmpty) return liveKitUrl;
    return _rewriteLoopbackHost(
      url.trim(),
      replacementHost: liveKitHost,
      ports: const {7880},
    );
  }

  /// Rewrite `localhost` / `127.0.0.1` / `0.0.0.0` hosts (any port).
  static String resolveLoopbackUrl(String? url) {
    return _rewriteLoopbackHost(url, replacementHost: apiHost);
  }

  static String _rewriteLoopbackHost(
    String? url, {
    required String replacementHost,
    Set<int>? ports,
  }) {
    if (url == null) return '';
    final trimmed = url.trim();
    if (trimmed.isEmpty) return trimmed;

    final uri = Uri.tryParse(trimmed);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) return trimmed;

    final host = uri.host.toLowerCase();
    final isLoopback =
        host == 'localhost' || host == '127.0.0.1' || host == '0.0.0.0';
    if (!isLoopback) return trimmed;

    // When [ports] is set, only rewrite matching service ports (e.g. 9000 / 7880).
    if (ports != null && uri.hasPort && !ports.contains(uri.port)) {
      return trimmed;
    }

    return uri.replace(host: replacementHost).toString();
  }

  static String _normalizeTrailingSlash(String url) =>
      url.endsWith('/') ? url : '$url/';

  static String _stripTrailingSlash(String url) =>
      url.endsWith('/') ? url.substring(0, url.length - 1) : url;
}
