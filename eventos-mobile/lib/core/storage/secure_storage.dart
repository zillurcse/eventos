import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

const _tokenKey = 'eventos_token';
const _subdomainKey = 'event_subdomain';
const _onboardingDoneKey = 'onboarding_done';

final secureStorageProvider = Provider<SecureStorageService>((ref) {
  return SecureStorageService(const FlutterSecureStorage());
});

class SecureStorageService {
  SecureStorageService(this._storage);

  final FlutterSecureStorage _storage;

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> writeToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> deleteToken() => _storage.delete(key: _tokenKey);

  Future<String?> readSubdomain() => _storage.read(key: _subdomainKey);

  Future<void> writeSubdomain(String subdomain) =>
      _storage.write(key: _subdomainKey, value: subdomain);

  Future<void> deleteSubdomain() => _storage.delete(key: _subdomainKey);

  Future<bool> readOnboardingDone() async {
    final value = await _storage.read(key: _onboardingDoneKey);
    return value == '1';
  }

  Future<void> writeOnboardingDone() =>
      _storage.write(key: _onboardingDoneKey, value: '1');

  Future<void> clearSession() async {
    await deleteToken();
    await deleteSubdomain();
  }
}
