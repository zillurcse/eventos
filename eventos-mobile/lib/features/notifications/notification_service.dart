import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class NotificationService {
  final Dio _dio = DioConfig.obj.dio!;

  /// V1 in-app inbox (latest 50 + unread count).
  Future<Response> getNotifications() {
    return _dio.get('notifications');
  }

  Future<Response> markAsRead(String uuid) {
    return _dio.patch('notifications/$uuid/read');
  }

  Future<Response> markAllAsRead() {
    return _dio.post('notifications/read-all');
  }
}
