import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../storage/secure_storage.dart';
import 'api_exception.dart';

final appConfigProvider = Provider<AppConfig>((ref) => AppConfig.development);

final apiClientProvider = Provider<ApiClient>((ref) {
  final config = ref.watch(appConfigProvider);
  final storage = ref.watch(secureStorageProvider);
  return ApiClient(config: config, storage: storage);
});

class ApiClient {
  ApiClient({
    required AppConfig config,
    required this._storage,
  }) : _dio = Dio(
          BaseOptions(
            baseUrl: config.apiV1,
            connectTimeout: const Duration(seconds: 15),
            receiveTimeout: const Duration(seconds: 30),
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.readToken();
          final subdomain = await _storage.readSubdomain();

          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          if (subdomain != null && subdomain.isNotEmpty) {
            options.headers['X-Event-Subdomain'] = subdomain;
          }

          handler.next(options);
        },
      ),
    );
  }

  final Dio _dio;
  final SecureStorageService _storage;

  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      return await _dio.get<T>(path, queryParameters: queryParameters);
    } on DioException catch (e) {
      throw _mapException(e);
    }
  }

  Future<Response<T>> post<T>(
    String path, {
    Object? data,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      return await _dio.post<T>(
        path,
        data: data,
        queryParameters: queryParameters,
      );
    } on DioException catch (e) {
      throw _mapException(e);
    }
  }

  ApiException _mapException(DioException error) {
    final response = error.response;
    final data = response?.data;
    String message = error.message ?? 'Network request failed';

    if (data is Map && data['message'] is String) {
      message = data['message'] as String;
    } else if (data is Map && data['error'] is String) {
      message = data['error'] as String;
    }

    return ApiException(message, statusCode: response?.statusCode);
  }
}
