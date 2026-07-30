import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';

class ThemeService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Theme + nav come from the public site bootstrap for this event subdomain.
  Future<Response> getThemeConfiguration() async {
    return await _dio.get('public/site');
  }
}
