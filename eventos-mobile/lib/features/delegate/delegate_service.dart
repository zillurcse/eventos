import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class DelegateService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context — eventUuid missing');
    }
    return uuid;
  }

  String get _base => 'events/$_eventUuid';

  /// GET /events/{uuid}/delegates
  Future<Response> getDelegates({
    String? q,
    String? sort,
    int page = 1,
    int perPage = 60,
  }) {
    return _dio.get(
      '$_base/delegates',
      queryParameters: {
        if (q != null && q.trim().isNotEmpty) 'q': q.trim(),
        if (sort != null && sort.trim().isNotEmpty) 'sort': sort.trim(),
        'page': page,
        'per_page': perPage,
      },
    );
  }

  /// GET /events/{uuid}/delegates/{delegateUuid}
  Future<Response> getDelegateDetail(String delegateUuid) {
    return _dio.get('$_base/delegates/$delegateUuid');
  }

  /// GET /public/ads?page=delegates
  Future<Response> getAds() {
    return _dio.get('public/ads', queryParameters: {'page': 'delegates'});
  }
}
