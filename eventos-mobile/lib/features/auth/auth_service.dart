import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';

class AuthService {
  final dio = DioConfig.obj.dio!;

  /// POST /public/check-email — does this person already have a login?
  Future<Response> emailValidationCheck({
    required String email,
  }) async {
    return await dio.post(
      'public/check-email',
      data: {'email': email},
    );
  }

  /// POST /auth/login — email + password → Sanctum token.
  Future<Response> loginWithPass({
    required String email,
    required String password,
  }) async {
    return await dio.post(
      'auth/login',
      data: {'email': email, 'password': password},
    );
  }

  /// GET /auth/my-events — events this login can open (sets subdomain).
  Future<Response> myEvents() async {
    return await dio.get('auth/my-events');
  }

  Future<Response> getRegisterComponents() async {
    return await dio.get('public/site');
  }

  Future<Response> registerUser({
    required List<Map<String, dynamic>> formData,
  }) async {
    // Registration against EventOS uses event UUID + form fields.
    // Kept as a stub path until signup is wired to /events/{uuid}/register.
    return await dio.post(
      'auth/register',
      data: {'form': formData},
    );
  }
}
