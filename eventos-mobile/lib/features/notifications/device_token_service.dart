import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class DeviceTokenService {
  final Dio _dio = DioConfig.obj.dio!;

  Future<Response> register({
    required String platform,
    required String token,
  }) {
    return _dio.post('device-tokens', data: {
      'platform': platform,
      'token': token,
    });
  }

  Future<Response> unregister(String token) {
    return _dio.delete('device-tokens', data: {'token': token});
  }
}
