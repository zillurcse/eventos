import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/storage/secure_storage.dart';

class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
  });

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
    );
  }

  final String id;
  final String name;
  final String email;
}

class AuthState {
  const AuthState({
    this.token,
    this.user,
    this.isBootstrapping = true,
  });

  final String? token;
  final AuthUser? user;
  final bool isBootstrapping;

  bool get isAuthenticated => token != null && token!.isNotEmpty;

  AuthState copyWith({
    String? token,
    AuthUser? user,
    bool? isBootstrapping,
    bool clearUser = false,
  }) {
    return AuthState(
      token: token ?? this.token,
      user: clearUser ? null : (user ?? this.user),
      isBootstrapping: isBootstrapping ?? this.isBootstrapping,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier(this._ref) : super(const AuthState()) {
    bootstrap();
  }

  final Ref _ref;

  SecureStorageService get _storage => _ref.read(secureStorageProvider);
  ApiClient get _api => _ref.read(apiClientProvider);

  Future<void> bootstrap() async {
    final token = await _storage.readToken();
    if (token == null || token.isEmpty) {
      state = state.copyWith(isBootstrapping: false, token: null, clearUser: true);
      return;
    }

    state = state.copyWith(token: token, isBootstrapping: true);

    try {
      final response = await _api.get<Map<String, dynamic>>('/auth/me');
      final userJson = response.data?['data'] ?? response.data;
      if (userJson is Map<String, dynamic>) {
        state = state.copyWith(
          user: AuthUser.fromJson(userJson),
          isBootstrapping: false,
        );
        return;
      }
    } catch (_) {
      await _storage.deleteToken();
    }

    state = state.copyWith(
      token: null,
      isBootstrapping: false,
      clearUser: true,
    );
  }

  Future<void> login(String email, String password) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/auth/login',
      data: {'email': email, 'password': password},
    );
    final data = response.data;
    if (data == null) throw Exception('Empty login response');

    final token = data['token'] as String?;
    final userJson = data['user'];
    if (token == null) throw Exception('Missing auth token');

    await _storage.writeToken(token);
    state = state.copyWith(
      token: token,
      user: userJson is Map<String, dynamic>
          ? AuthUser.fromJson(userJson)
          : null,
      isBootstrapping: false,
    );
  }

  Future<void> logout() async {
    try {
      await _api.post<void>('/auth/logout');
    } catch (_) {
      // Best-effort server logout.
    }
    await _storage.deleteToken();
    state = state.copyWith(token: null, clearUser: true, isBootstrapping: false);
  }
}

final authProvider =
    StateNotifierProvider<AuthNotifier, AuthState>((ref) => AuthNotifier(ref));
