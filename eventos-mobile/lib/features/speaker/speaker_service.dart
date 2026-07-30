import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';

class SpeakerService {
  final Dio _dio = DioConfig.obj.dio!;

  /// GET /public/speakers
  Future<Response> getSpeakers() async {
    return await _dio.get('public/speakers');
  }

  /// Used to attach sessions on the speaker detail screen.
  Future<Response> getSessions() async {
    return await _dio.get('public/sessions');
  }

  Future<Response> addOrUpdateSpeakerNote(int id, String note) async {
    return Response(
      requestOptions: RequestOptions(path: 'notes/speaker/$id'),
      statusCode: 501,
      data: {'status': 'error', 'message': 'Notes not available yet'},
    );
  }

  Future<Response> toggleSpeakerBookmark(int id) async {
    return Response(
      requestOptions: RequestOptions(path: 'bookmarks/speaker/$id'),
      statusCode: 501,
      data: {'status': 'error', 'message': 'Bookmarks not available yet'},
    );
  }
}
