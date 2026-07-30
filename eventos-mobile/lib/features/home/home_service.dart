import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class HomeService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch the home/reception page data.
  /// Token is automatically injected by DioConfig's interceptor.
  Future<Response> getReception() async {
    return await _dio.post('mobile/event/reception');
  }
}
