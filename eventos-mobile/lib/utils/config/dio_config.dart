import 'dart:io';

import 'package:dio/dio.dart';
import 'package:get_storage/get_storage.dart';

import '../helpers/app_data_provider.dart';
import '../helpers/local_key.dart';
import 'app_config.dart';

class DioConfig {
  DioConfig._();
  static DioConfig obj = DioConfig._();
  factory DioConfig() => obj;

  Dio? dio;
  final localDb = GetStorage();

  void init({bool force = false}) {
    if (dio != null && !force) return;

    dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        sendTimeout: const Duration(seconds: 15),
        followRedirects: true,
        maxRedirects: 5,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Event-Subdomain': AppDataProvider.obj.subdomain,
        },
      ),
    );

    dio?.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          // Always keep event context in sync (subdomain may change after login).
          options.headers['X-Event-Subdomain'] = AppDataProvider.obj.subdomain;

          const publicAuthPaths = <String>[
            'auth/login',
            'auth/register',
            'public/check-email',
            'public/auth/otp',
            'public/site',
          ];

          final path = options.path;
          final isPublicAuth = publicAuthPaths.any(path.contains);
          if (!isPublicAuth) {
            final token = localDb.read(LocalKeyHelper.token);
            if (token != null) {
              options.headers[HttpHeaders.authorizationHeader] =
                  'Bearer $token';
            }
          }

          return handler.next(options);
        },
        onResponse: (response, handler) => handler.next(response),
        onError: (error, handler) => handler.next(error),
      ),
    );
  }

  /// Call after subdomain changes so new requests use the updated header default.
  void refreshEventHeaders() {
    dio?.options.headers['X-Event-Subdomain'] = AppDataProvider.obj.subdomain;
  }
}
