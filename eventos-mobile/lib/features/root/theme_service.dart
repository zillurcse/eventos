import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class ThemeService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch theme configuration
  Future<Response> getThemeConfiguration() async {
    return await _dio.post('mobile/event/theme-configuration');
  }
}
