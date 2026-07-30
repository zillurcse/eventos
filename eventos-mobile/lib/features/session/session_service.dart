import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class SessionService {
  final Dio _dio = DioConfig.obj.dio!;

  String? get _eventUuid => AppDataProvider.obj.eventUuid;

  /// GET /public/sessions — full agenda + facets for X-Event-Subdomain.
  Future<Response> getSessions() async {
    return await _dio.get('public/sessions');
  }

  /// GET /events/{uuid}/bookmarks — saved ids by type.
  Future<Response> getBookmarks() async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.get('events/$uuid/bookmarks');
  }

  /// POST /events/{uuid}/notes — create or update a session note.
  Future<Response> addOrUpdateSessionNote(String targetUuid, String note) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.post(
      'events/$uuid/notes',
      data: {
        'type': 'session',
        'target_id': targetUuid,
        'text': note,
      },
    );
  }

  /// POST /events/{uuid}/bookmarks — toggle session bookmark.
  Future<Response> toggleSessionBookmark(String sessionUuid, bool on) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.post(
      'events/$uuid/bookmarks',
      data: {
        'type': 'session',
        'id': sessionUuid,
        'on': on,
      },
    );
  }
}
