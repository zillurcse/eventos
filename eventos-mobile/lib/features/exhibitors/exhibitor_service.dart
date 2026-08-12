import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class ExhibitorService {
  final Dio _dio = DioConfig.obj.dio!;

  String? get _eventUuid => AppDataProvider.obj.eventUuid;

  /// GET /public/exhibitors
  Future<Response> getExhibitors() async {
    return await _dio.get('public/exhibitors');
  }

  /// GET /public/exhibitors/{uuid}
  Future<Response> getExhibitorDetails(String uuid) async {
    return await _dio.get('public/exhibitors/$uuid');
  }

  /// POST /events/{uuid}/bookmarks - toggle exhibitor bookmark.
  Future<Response> toggleExhibitorBookmark(String exhibitorUuid, bool on) async {
    final uuid = _eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set');
    }
    return await _dio.post(
      'events/$uuid/bookmarks',
      data: {
        'type': 'exhibitor',
        'id': exhibitorUuid,
        'on': on,
      },
    );
  }
}
