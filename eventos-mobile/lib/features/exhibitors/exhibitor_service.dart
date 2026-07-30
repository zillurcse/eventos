import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class ExhibitorService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch the exhibitors list for the current event.
  /// Token is automatically injected by DioConfig's interceptor.
  Future<Response> getExhibitors({String? type, String? s}) async {
    final Map<String, dynamic> data = {};
    if (type != null) data['type'] = type;
    if (s != null && s.trim().isNotEmpty) data['s'] = s.trim();

    return await _dio.post(
      'mobile/event/exhibitors',
      data: data.isEmpty ? null : data,
    );
  }

  /// Fetch single exhibitor details by [slug].
  Future<Response> getExhibitorDetails(String slug) async {
    return await _dio.post('mobile/event/exhibitors/show/$slug');
  }

  /// Like/Unlike (bookmark/unbookmark) an exhibitor by [id].
  Future<Response> toggleExhibitorBookmark(int id) async {
    return await _dio.post('mobile/bookmark/event/exhibitors/$id');
  }
}
