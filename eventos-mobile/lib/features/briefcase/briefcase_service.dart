import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class BriefcaseService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context — eventUuid missing');
    }
    return uuid;
  }

  String get _base => 'events/$_eventUuid';

  /// GET /events/{uuid}/briefcase
  Future<Response> getBriefcase() => _dio.get('$_base/briefcase');

  /// POST /events/{uuid}/briefcase {title, url, kind}
  Future<Response> addFile({
    required String title,
    required String url,
    String kind = 'file',
  }) {
    return _dio.post(
      '$_base/briefcase',
      data: {'title': title, 'url': url, 'kind': kind},
    );
  }

  /// DELETE /events/{uuid}/briefcase/{item}
  Future<Response> removeFile(String itemId) {
    return _dio.delete('$_base/briefcase/$itemId');
  }

  /// GET /events/{uuid}/notes
  Future<Response> getNotes() => _dio.get('$_base/notes');

  /// POST /events/{uuid}/notes {type, target_id, text}
  Future<Response> saveNote({
    required String type,
    required String targetId,
    required String text,
  }) {
    return _dio.post(
      '$_base/notes',
      data: {
        'type': type,
        'target_id': targetId,
        'text': text,
      },
    );
  }

  /// DELETE /events/{uuid}/notes/{type}/{targetId}
  Future<Response> removeNote({
    required String type,
    required String targetId,
  }) {
    return _dio.delete('$_base/notes/$type/$targetId');
  }
}
