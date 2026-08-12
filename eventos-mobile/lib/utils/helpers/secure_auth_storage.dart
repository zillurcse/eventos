import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_storage/get_storage.dart';

import 'local_key.dart';

/// Auth token persistence via platform secure storage
/// (Android Keystore-backed EncryptedSharedPreferences / iOS Keychain).
///
/// Falls back to GetStorage if secure storage is unavailable (e.g. some
/// desktop/emulator setups) so sessions still survive app restarts.
///
/// Keeps an in-memory cache so Dio interceptors can attach the Bearer token
/// synchronously. Call [init] once at app startup before routing.
class SecureAuthStorage {
  SecureAuthStorage._();
  static final SecureAuthStorage instance = SecureAuthStorage._();
  factory SecureAuthStorage() => instance;

  static const _tokenKey = 'auth_token';

  final FlutterSecureStorage _secure = const FlutterSecureStorage(
    aOptions: AndroidOptions(),
    iOptions: IOSOptions(
      accessibility: KeychainAccessibility.first_unlock_this_device,
    ),
  );

  String? _cachedToken;
  bool _initialized = false;

  /// Cached token for sync readers (Dio interceptor). Null until [init].
  String? get token => _cachedToken;

  bool get hasToken => _cachedToken != null && _cachedToken!.isNotEmpty;

  Future<void> init() async {
    if (_initialized) return;
    try {
      _cachedToken = await _secure.read(key: _tokenKey);
    } catch (e) {
      debugPrint('SecureAuthStorage.init secure read failed: $e');
      _cachedToken = null;
    }

    await _migrateLegacyTokenIfNeeded();

    // Last-resort fallback when Keystore/Keychain is empty or unreadable.
    if (_cachedToken == null || _cachedToken!.isEmpty) {
      final fallback = GetStorage().read(LocalKeyHelper.token);
      if (fallback is String && fallback.isNotEmpty) {
        _cachedToken = fallback;
      }
    }

    _initialized = true;
  }

  /// Moves plaintext tokens previously stored in GetStorage into Keystore/Keychain.
  Future<void> _migrateLegacyTokenIfNeeded() async {
    final box = GetStorage();
    final legacy = box.read(LocalKeyHelper.token);
    if (legacy is! String || legacy.isEmpty) return;

    if (_cachedToken == null || _cachedToken!.isEmpty) {
      _cachedToken = legacy;
      try {
        await _secure.write(key: _tokenKey, value: legacy);
        // Successfully in secure storage - drop plaintext copy.
        await box.remove(LocalKeyHelper.token);
      } catch (e) {
        // Keep GetStorage copy so the session still persists.
        debugPrint('SecureAuthStorage.migrate write failed: $e');
      }
    } else {
      await box.remove(LocalKeyHelper.token);
    }
  }

  Future<void> saveToken(String value) async {
    if (value.isEmpty) {
      await clearToken();
      return;
    }
    _cachedToken = value;
    var secureOk = false;
    try {
      await _secure.write(key: _tokenKey, value: value);
      secureOk = true;
    } catch (e) {
      debugPrint('SecureAuthStorage.saveToken failed: $e');
    }

    final box = GetStorage();
    if (secureOk) {
      // Prefer Keystore/Keychain only - remove any plaintext leftover.
      try {
        await box.remove(LocalKeyHelper.token);
      } catch (_) {}
    } else {
      // Secure storage unavailable: persist so the next cold start still works.
      await box.write(LocalKeyHelper.token, value);
    }
  }

  Future<void> clearToken() async {
    _cachedToken = null;
    try {
      await _secure.delete(key: _tokenKey);
    } catch (e) {
      debugPrint('SecureAuthStorage.clearToken failed: $e');
    }
    try {
      await GetStorage().remove(LocalKeyHelper.token);
    } catch (_) {}
  }
}
