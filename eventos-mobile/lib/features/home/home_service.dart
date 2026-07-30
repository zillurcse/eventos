import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';

class HomeService {
  final Dio _dio = DioConfig.obj.dio!;

  /// GET /public/reception — attendee home content for X-Event-Subdomain.
  Future<Response> getReception() async {
    return await _dio.get('public/reception');
  }

  /// GET /public/site — branding + welcome video (not in reception payload).
  Future<Response> getSite() async {
    return await _dio.get('public/site');
  }
}
