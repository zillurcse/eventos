import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class NotificationService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Get all notifications with pagination.
  Future<Response> getNotifications(int page) async {
    return await _dio.post('mobile/notifications/get?page=$page');
  }

  /// Get the total count of unread notifications.
  Future<Response> getUnreadCount() async {
    return await _dio.post('mobile/notifications/unread-count');
  }

  /// Mark a single notification as read.
  Future<Response> markAsRead(int id) async {
    return await _dio.post('mobile/notifications/read/$id');
  }

  /// Mark all notifications as read.
  Future<Response> markAllAsRead() async {
    return await _dio.post('mobile/notifications/read-all');
  }
}
