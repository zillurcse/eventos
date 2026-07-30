import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class SessionService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch the session days and schedules.
  /// Token is automatically injected by DioConfig's interceptor.
  /// Passes filter options in the request payload body.
  Future<Response> getSessions({
    int? trackId,
    String? tag,
    String? timezone,
    int? speakerId,
    String? s,
  }) async {
    final Map<String, dynamic> data = {};
    if (trackId != null) {
      data['tracks'] = [trackId];
    }
    if (tag != null) {
      data['tags'] = [tag];
    }
    if (timezone != null) {
      data['timezone'] = timezone.split("|").last;
    }
    if (speakerId != null) {
      data['speaker_ids'] = [speakerId];
    }
    if (s != null && s.trim().isNotEmpty) {
      data['s'] = s.trim();
    }

    return await _dio.post('mobile/event/sessions', data: data);
  }

  /// Fetch details for a single session by [scheduleId].
  Future<Response> getSessionDetails(int scheduleId) async {
    return await _dio.post('mobile/event/sessions/$scheduleId');
  }

  /// Add or update a note for a session/schedule by [id].
  Future<Response> addOrUpdateSessionNote(int id, String note) async {
    return await _dio.post(
      'mobile/notes/schedule/$id',
      data: {'note': note},
    );
  }

  /// Bookmark or unbookmark a session/schedule by [scheduleId].
  Future<Response> toggleSessionBookmark(int scheduleId) async {
    return await _dio.post('mobile/schedule/$scheduleId/bookmark');
  }
}
