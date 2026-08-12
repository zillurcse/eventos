import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class ChatService {
  final dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context - eventUuid missing');
    }
    return uuid;
  }

  String get _base => 'events/$_eventUuid/chat';

  Future<Response> getInbox() => dio.get(_base);

  Future<Response> getCapabilities() => dio.get('$_base/capabilities');

  Future<Response> getPartners({String? q, String? role}) {
    return dio.get(
      '$_base/partners',
      queryParameters: {
        if (q != null && q.isNotEmpty) 'q': q,
        if (role != null && role.isNotEmpty) 'role': role,
      },
    );
  }

  Future<Response> openConversation(String participantUuid) {
    return dio.post(_base, data: {'participant': participantUuid});
  }

  Future<Response> getMessages(String conversationId, {int page = 1}) {
    return dio.get(
      '$_base/$conversationId/messages',
      queryParameters: {'page': page},
    );
  }

  Future<Response> sendMessage({
    required String conversationId,
    String? body,
    List<Map<String, dynamic>>? attachments,
  }) {
    return dio.post(
      '$_base/$conversationId/messages',
      data: {
        if (body != null) 'body': body,
        if (attachments != null && attachments.isNotEmpty)
          'attachments': attachments,
      },
    );
  }

  Future<Response> markRead(String conversationId) {
    return dio.patch('$_base/$conversationId/read');
  }

  /// Upload a chat attachment → MinIO URL (collection: chat).
  Future<Response> uploadAttachment(FormData formData) {
    // Never set Content-Type to a bare `multipart/form-data` - Dio must add the boundary.
    return dio.post(
      'events/$_eventUuid/uploads',
      data: formData,
      options: Options(
        sendTimeout: const Duration(minutes: 5),
        receiveTimeout: const Duration(minutes: 5),
      ),
    );
  }
}
