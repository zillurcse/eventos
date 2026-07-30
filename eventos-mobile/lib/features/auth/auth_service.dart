import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';

class AuthService {
  final dio = DioConfig.obj.dio!;

  Future<Response> emailValidationCheck({
    required bool agreedTc,
    required String email,
  }) async {
    return await dio.post(
      "validate-email-for-mobile/",
      data: {"agree_to_terms": agreedTc, "email": email},
    );
  }

  Future<Response> loginWithPass({
    required String email,
    required String password,
  }) async {
    return await dio.post(
      "auth/login-with-password/",
      data: {"email": email, "password": password},
    );
  }

  Future<Response> getRegisterComponents() async {
    return await dio.post(
      "event/user/profile/config/",
      data: {"page": "registration"},
    );
  }

  Future<Response> registerUser({
    required List<Map<String, dynamic>> formData,
  }) async {
    return await dio.post(
      "event/user/profile/config/update/",
      data: {"page": "registration", "form": formData},
    );
  }
}
