import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class SpeakerService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch the speakers list for the current event.
  /// Token is automatically injected by DioConfig's interceptor.
  Future<Response> getSpeakers({String? s, String? sortBy}) async {
    final Map<String, dynamic> data = {};
    if (s != null && s.trim().isNotEmpty) data['s'] = s.trim();
    if (sortBy != null && sortBy.trim().isNotEmpty) data['sort_by'] = sortBy.trim();

    return await _dio.post(
      'mobile/event/speakers',
      data: data.isEmpty ? null : data,
    );
  }

  /// Fetch full detail for a single speaker by [id].
  Future<Response> getSpeakerDetail(int id) async {
    return await _dio.post('mobile/event/speakers/$id');
  }

  /// Add or update a note for a speaker by [id].
  Future<Response> addOrUpdateSpeakerNote(int id, String note) async {
    return await _dio.post(
      'mobile/notes/speaker/$id',
      data: {'note': note},
    );
  }

  /// Like/Unlike (bookmark/unbookmark) a speaker by [id].
  Future<Response> toggleSpeakerBookmark(int id) async {
    return await _dio.post('mobile/like/speaker/$id');
  }
}
