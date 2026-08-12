import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class LoungeService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context - eventUuid missing');
    }
    return uuid;
  }

  String get _base => 'events/$_eventUuid/lounge';

  /// GET /events/{event}/lounge/tables
  Future<Response> getTables() {
    return _dio.get('$_base/tables');
  }

  /// POST /events/{event}/lounge/tables/{table}/join
  Future<Response> joinTable({
    required String tableId,
    int? seat,
    String? avatarUrl,
  }) {
    return _dio.post(
      '$_base/tables/${Uri.encodeComponent(tableId)}/join',
      data: {
        if (seat != null) 'seat': seat,
        if (avatarUrl != null && avatarUrl.isNotEmpty) 'avatar_url': avatarUrl,
      },
    );
  }
}
