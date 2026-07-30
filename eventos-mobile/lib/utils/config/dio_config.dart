import 'dart:io';

import 'package:dio/dio.dart';
import 'package:get_storage/get_storage.dart';

import '../helpers/app_data_provider.dart';
import '../helpers/local_key.dart';

class DioConfig {
  DioConfig._();
  static DioConfig obj = DioConfig._();
  factory DioConfig() => obj;

  Dio? dio;
  final localDb = GetStorage();

  void init() {
    if(dio != null) return;

    dio = Dio(
      BaseOptions(
        baseUrl: "https://${AppDataProvider.obj.subdomain}.expouse.com/api/",
        connectTimeout: Duration(seconds: 15),
        receiveTimeout: Duration(seconds: 15),
        sendTimeout: Duration(seconds: 15),
        followRedirects: true,
        maxRedirects: 5,
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "subdomain": AppDataProvider.obj.subdomain,
        },
      ),
    );

    dio?.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final excludedRoutes = <String>[
            "validate-email-for-mobile/",
            "auth/login-with-password/",
            "event/user/profile/config/",
            "event/user/profile/config/update/",


          ];

          final shouldAddToken = !excludedRoutes.any(options.path.contains);
          if (shouldAddToken) {
            final token = localDb.read(LocalKeyHelper.token);
            if (token != null) {
              options.headers[HttpHeaders.authorizationHeader] = "Bearer $token";
            }
          }
          return handler.next(options);
        },
        onResponse: (response, handler) {
          return handler.next(response);
        },
        onError: (error, handler) {
          return handler.next(error);
        },
      ),
    );
  }
}
