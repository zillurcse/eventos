import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class BookmarkService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch bookmarked items (speakers, sessions, exhibitors, delegates).
  /// Token is automatically injected by DioConfig's interceptor.
  Future<Response> getBookmarks() async {
    return await _dio.post('mobile/event/bookmarks');
  }
}
