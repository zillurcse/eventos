import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class SpeakerService {
  final Dio _dio = DioConfig.obj.dio!;

  String? get _eventUuid => AppDataProvider.obj.eventUuid;

  /// GET /public/speakers
  Future<Response> getSpeakers() async {
    return await _dio.get('public/speakers');
  }

  /// Used to attach sessions on the speaker detail screen.
  Future<Response> getSessions() async {
    return await _dio.get('public/sessions');
  }

  /// POST /events/{uuid}/notes - create or update a speaker note.
  Future<Response> addOrUpdateSpeakerNote(String targetUuid, String note) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.post(
      'events/$uuid/notes',
      data: {
        'type': 'speaker',
        'target_id': targetUuid,
        'text': note,
      },
    );
  }

  /// POST /events/{uuid}/bookmarks - toggle speaker bookmark.
  Future<Response> toggleSpeakerBookmark(String speakerUuid, bool on) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.post(
      'events/$uuid/bookmarks',
      data: {
        'type': 'speaker',
        'id': speakerUuid,
        'on': on,
      },
    );
  }
}
