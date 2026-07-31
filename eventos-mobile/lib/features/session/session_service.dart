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

  /// GET /events/{uuid}/sessions/{sessionUuid}/rating
  Future<Response> getSessionRating(String sessionUuid) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.get('events/$uuid/sessions/$sessionUuid/rating');
  }

  /// POST /events/{uuid}/sessions/{sessionUuid}/rating
  Future<Response> submitSessionRating(String sessionUuid, int score) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.post(
      'events/$uuid/sessions/$sessionUuid/rating',
      data: {'score': score},
    );
  }

  /// GET /public/sessions/{uuid}/zoom-signature
  Future<Response> getZoomSignature(String sessionUuid) async {
    return await _dio.get('public/sessions/$sessionUuid/zoom-signature');
  }

  /// GET /events/{uuid}/sessions/{sessionUuid}/jitsi-token
  Future<Response> getJitsiToken(String sessionUuid) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.get('events/$uuid/sessions/$sessionUuid/jitsi-token');
  }

  /// GET /events/{uuid}/sessions/{sessionUuid}/agora-token
  Future<Response> getAgoraToken(String sessionUuid) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.get('events/$uuid/sessions/$sessionUuid/agora-token');
  }

  // ── Engagement ────────────────────────────────────────────────────────────

  Future<Response> getChat(String sessionUuid) async {
    final uuid = _requireEvent();
    return await _dio.get('events/$uuid/sessions/$sessionUuid/chat');
  }

  Future<Response> sendChat(String sessionUuid, String body) async {
    final uuid = _requireEvent();
    return await _dio.post(
      'events/$uuid/sessions/$sessionUuid/chat',
      data: {'body': body},
    );
  }

  Future<Response> getQuestions(String sessionUuid) async {
    final uuid = _requireEvent();
    return await _dio.get('events/$uuid/sessions/$sessionUuid/questions');
  }

  Future<Response> askQuestion(String sessionUuid, String body) async {
    final uuid = _requireEvent();
    return await _dio.post(
      'events/$uuid/sessions/$sessionUuid/questions',
      data: {'body': body},
    );
  }

  Future<Response> upvoteQuestion(String sessionUuid, int messageId) async {
    final uuid = _requireEvent();
    return await _dio.post(
      'events/$uuid/sessions/$sessionUuid/questions/$messageId/upvote',
    );
  }

  Future<Response> replyToQuestion(
    String sessionUuid,
    int messageId,
    String body,
  ) async {
    final uuid = _requireEvent();
    return await _dio.post(
      'events/$uuid/sessions/$sessionUuid/questions/$messageId/replies',
      data: {'body': body},
    );
  }

  Future<Response> getPolls(String sessionUuid) async {
    final uuid = _requireEvent();
    return await _dio.get('events/$uuid/sessions/$sessionUuid/polls');
  }

  Future<Response> votePoll(
    String sessionUuid,
    int pollId,
    String optionId,
  ) async {
    final uuid = _requireEvent();
    return await _dio.post(
      'events/$uuid/sessions/$sessionUuid/polls/$pollId/vote',
      data: {'option_id': optionId},
    );
  }

  Future<Response> getAttendees(String sessionUuid) async {
    final uuid = _requireEvent();
    return await _dio.get('events/$uuid/sessions/$sessionUuid/attendees');
  }

  String _requireEvent() {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return uuid;
  }
}
