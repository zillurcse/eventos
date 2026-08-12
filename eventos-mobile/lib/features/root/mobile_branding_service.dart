import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';

/// Public mobile brand bootstrap (platform default + optional event override).
class MobileBrandingService {
  /// Resolved at call time - AppDataProvider is constructed during DioConfig.init
  /// before `dio` exists, so this must not be captured in a field initializer.
  Dio get _dio => DioConfig.obj.dio!;

  /// Platform Expouse brand (cold start / no event).
  Future<Response> getPlatformBranding() async {
    return _dio.get('public/mobile-branding');
  }

  /// Resolved brand for an event uuid (organizer when enabled, else platform).
  Future<Response> getEventBranding(String eventUuid) async {
    return _dio.get(
      'public/mobile-branding',
      queryParameters: {'event': eventUuid},
    );
  }
}
