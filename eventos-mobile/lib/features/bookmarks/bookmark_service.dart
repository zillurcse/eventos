import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class BookmarkService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context - eventUuid missing');
    }
    return uuid;
  }

  /// GET /events/{uuid}/bookmarks - saved ids grouped by type.
  Future<Response> getBookmarks() async {
    return await _dio.get('events/$_eventUuid/bookmarks');
  }

  /// POST /events/{uuid}/bookmarks {type, id, on}
  Future<Response> toggleBookmark({
    required String type,
    required String id,
    required bool on,
  }) async {
    return await _dio.post(
      'events/$_eventUuid/bookmarks',
      data: {'type': type, 'id': id, 'on': on},
    );
  }

  /// Resolve bookmarked delegates by participation uuids.
  Future<Response> resolveDelegates(List<String> ids) async {
    if (ids.isEmpty) {
      return Response(
        requestOptions: RequestOptions(path: 'events/$_eventUuid/delegates'),
        statusCode: 200,
        data: {'data': []},
      );
    }
    return await _dio.get(
      'events/$_eventUuid/delegates',
      queryParameters: {'ids': ids.join(','), 'per_page': 100},
    );
  }

  Future<Response> getSpeakers() => _dio.get('public/speakers');

  Future<Response> getSessions() => _dio.get('public/sessions');

  Future<Response> getExhibitors() => _dio.get('public/exhibitors');
}
