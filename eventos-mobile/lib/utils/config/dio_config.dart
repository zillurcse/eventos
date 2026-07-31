import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';

import '../helpers/app_data_provider.dart';
import '../helpers/secure_auth_storage.dart';
import '../helpers/session_manager.dart';
import 'app_config.dart';

class DioConfig {
  DioConfig._();
  static DioConfig obj = DioConfig._();
  factory DioConfig() => obj;

  Dio? dio;

  void init({bool force = false}) {
    if (dio != null && !force) return;

    final baseUrl = AppConfig.apiBaseUrl;
    AppConfig.assertSecureBaseUrl(baseUrl);

    dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
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

    _configureTls(dio!);

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
            final token = SecureAuthStorage.instance.token;
            if (token != null && token.isNotEmpty) {
              options.headers[HttpHeaders.authorizationHeader] =
                  'Bearer $token';
            }
          }

          return handler.next(options);
        },
        onResponse: (response, handler) => handler.next(response),
        onError: (error, handler) async {
          if (error.response?.statusCode == 401) {
            final skip =
                error.requestOptions.extra['skipAuthLogout'] == true;
            if (!skip && SecureAuthStorage.instance.hasToken) {
              await SessionManager.logout();
            }
          }
          return handler.next(error);
        },
      ),
    );

    // Request logging only in debug — never log bodies/headers (tokens/PII).
    if (kDebugMode) {
      dio?.interceptors.add(
        LogInterceptor(
          requestBody: false,
          responseBody: false,
          requestHeader: false,
          responseHeader: false,
          logPrint: (obj) => debugPrint(obj.toString()),
        ),
      );
    }
  }

  /// Optional public-key / cert pinning via
  /// `--dart-define=SSL_PINS=<sha256hex;sha256hex>`.
  /// Leave empty to use system CA validation only.
  void _configureTls(Dio client) {
    final pins = AppConfig.sslPins;
    if (pins.isEmpty) return;

    final adapter = client.httpClientAdapter;
    if (adapter is! IOHttpClientAdapter) return;

    adapter.validateCertificate = (cert, host, port) {
      if (cert == null) return false;
      final fingerprint = sha256.convert(cert.der).toString();
      final matched = pins.any((p) => p.toLowerCase() == fingerprint);
      if (!matched && kDebugMode) {
        debugPrint(
          'SSL pin mismatch for $host (got $fingerprint). '
          'Rotate SSL_PINS after certificate renewal.',
        );
      }
      return matched;
    };
  }

  /// Call after subdomain changes so new requests use the updated header default.
  void refreshEventHeaders() {
    dio?.options.headers['X-Event-Subdomain'] = AppDataProvider.obj.subdomain;
  }
}
