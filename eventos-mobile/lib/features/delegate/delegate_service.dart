import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class DelegateService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch the delegates list for the current event.
  /// Token is automatically injected by DioConfig's interceptor.
  Future<Response> getDelegates({String? s, String? sortBy}) async {
    final Map<String, dynamic> data = {};
    if (s != null && s.trim().isNotEmpty) data['s'] = s.trim();
    if (sortBy != null && sortBy.trim().isNotEmpty) data['sort_by'] = sortBy.trim();

    return await _dio.post(
      'mobile/event/delegates',
      data: data.isEmpty ? null : data,
    );
  }

  /// Fetch full detail for a single delegate by [id].
  Future<Response> getDelegateDetail(int id) async {
    return await _dio.post('mobile/event/delegates/$id');
  }

  /// Like/Unlike (bookmark/unbookmark) a delegate by [id].
  Future<Response> toggleDelegateBookmark(int id) async {
    return await _dio.post('mobile/delegates/$id/like');
  }
}
